<script setup lang="ts">
import FieldEditor from '@/components/builder/FieldEditor.vue';
import VisibilityEditor from '@/components/builder/VisibilityEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type FieldType, type FormData, type FormFieldData, type FormSectionData } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import {
    AlignLeft,
    Bell,
    Calendar,
    CheckSquare,
    ChevronDown,
    CircleDot,
    ClipboardCheck,
    Clock,
    ExternalLink,
    EyeOff,
    FileText,
    FileUp,
    Gauge,
    GripVertical,
    Hash,
    ImageIcon,
    Inbox,
    LinkIcon,
    ListPlus,
    Mail,
    Palette,
    Phone,
    Plus,
    Save,
    Settings2,
    ShieldCheck,
    Star,
    TextCursorInput,
    Trash2,
    Type,
    Users,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps<{
    form: FormData;
    fieldTypes: FieldType[];
    defaultRetentionDays: number;
    maxUploadKb: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: trans('Forms'), href: '/forms' },
    { title: props.form.title, href: route('forms.edit', props.form.id) },
];

/* ------------------------------------------------------------------ */
/* Structure (sections + fields)                                       */
/* ------------------------------------------------------------------ */

const sections = reactive<FormSectionData[]>(JSON.parse(JSON.stringify(props.form.sections)));

const structureDirty = ref(false);
const savingStructure = ref(false);
const syncing = ref(false);
const structureError = ref<string | null>(null);
const structureFieldErrors = ref<Record<string, string>>({});

// True for the duration of the structure-save request itself (including the
// resync that follows it), so the "leave page?" guard below doesn't mistake
// the save's own outgoing request for a real navigation away from the page.
const savingInProgress = ref(false);

const AUTOSAVE_DELAY_MS = 1500;
let autoSaveTimeout: ReturnType<typeof setTimeout> | undefined;

// A question's label is always required server-side. Autosaving a newly
// added, still-empty question would just bounce off a validation error, so
// hold off until every question actually has some text in it — the manual
// Save button still works (and still reports the error) in the meantime.
const hasEmptyLabel = () => sections.some((section) => section.fields.some((field) => !field.label.trim()));

const scheduleAutoSave = () => {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        autoSaveTimeout = undefined;

        if (structureDirty.value && !savingInProgress.value && !hasEmptyLabel()) {
            saveStructure();
        }
    }, AUTOSAVE_DELAY_MS);
};

const markDirty = () => {
    if (!syncing.value) {
        structureDirty.value = true;
        scheduleAutoSave();
    }
};

// Errors for a given section (e.g. "sections.1.title" → "title").
const sectionErrors = (sectionIndex: number): Record<string, string> => {
    const prefix = `sections.${sectionIndex}.`;
    const result: Record<string, string> = {};

    for (const [key, message] of Object.entries(structureFieldErrors.value)) {
        if (key.startsWith(prefix) && !key.startsWith(`${prefix}fields.`)) {
            result[key.slice(prefix.length)] = message;
        }
    }

    return result;
};

// Visibility errors for a given section (e.g. "sections.1.visibility.mode" → "mode").
const sectionVisibilityErrors = (sectionIndex: number): Record<string, string> =>
    Object.fromEntries(
        Object.entries(sectionErrors(sectionIndex))
            .filter(([key]) => key.startsWith('visibility.'))
            .map(([key, message]) => [key.slice('visibility.'.length), message]),
    );

// A section can only depend on questions outside of it: hiding the section
// hides its own questions, so they could never satisfy the condition.
const sectionConditionableFields = (section: FormSectionData) => conditionableFields.value.filter((field) => !section.fields.includes(field));

// Errors for a given field (e.g. "sections.1.fields.2.label" → "label").
const fieldErrorsFor = (sectionIndex: number, fieldIndex: number): Record<string, string> => {
    const prefix = `sections.${sectionIndex}.fields.${fieldIndex}.`;
    const result: Record<string, string> = {};

    for (const [key, message] of Object.entries(structureFieldErrors.value)) {
        if (key.startsWith(prefix)) {
            result[key.slice(prefix.length)] = message;
        }
    }

    return result;
};

// Any edit inside the tree (labels, options, order…) marks the structure dirty.
watch(sections, markDirty, { deep: true });

const resyncSections = async (fresh: FormSectionData[]) => {
    syncing.value = true;
    sections.splice(0, sections.length, ...(JSON.parse(JSON.stringify(fresh)) as FormSectionData[]));
    await nextTick();
    syncing.value = false;
};

const fieldPaletteItems: { type: FieldType; label: string; icon: typeof Type }[] = [
    { type: 'text', label: 'Short text', icon: TextCursorInput },
    { type: 'textarea', label: 'Long text', icon: AlignLeft },
    { type: 'email', label: 'Email', icon: Mail },
    { type: 'phone', label: 'Phone number', icon: Phone },
    { type: 'number', label: 'Number', icon: Hash },
    { type: 'date', label: 'Date', icon: Calendar },
    { type: 'time', label: 'Time', icon: Clock },
    { type: 'choice', label: 'Single choice', icon: CircleDot },
    { type: 'checkboxes', label: 'Multiple choice', icon: CheckSquare },
    { type: 'dropdown', label: 'Dropdown', icon: ChevronDown },
    { type: 'file', label: 'File upload', icon: FileUp },
    { type: 'rating_star', label: 'Star rating', icon: Star },
    { type: 'rating_number', label: 'Rating (0 to 10)', icon: Gauge },
    { type: 'info', label: 'Text block', icon: Type },
];

// Sorted by the translated label so the menu reads alphabetically in whichever
// language is active — re-sorts automatically when the user switches locale.
const fieldPalette = computed(() => [...fieldPaletteItems].sort((a, b) => trans(a.label).localeCompare(trans(b.label))));

const addField = (section: FormSectionData, type: FieldType) => {
    section.fields.push({
        id: null,
        type,
        label: '',
        description: null,
        required: false,
        options: ['choice', 'checkboxes', 'dropdown'].includes(type) ? { choices: [trans('Option') + ' 1'] } : null,
        visibility: null,
    });
    markDirty();
};

// Fields other questions can set a visibility condition on. Only already-saved
// fields (real ids) are eligible, since a brand-new field's id doesn't exist
// yet until the structure is saved once.
const conditionableFields = computed(() =>
    sections
        .flatMap((section) => section.fields)
        .filter((field): field is FormFieldData & { id: number } => field.id !== null && field.type !== 'info'),
);

const removeField = (section: FormSectionData, index: number) => {
    section.fields.splice(index, 1);
    markDirty();
};

const addSection = () => {
    sections.push({ id: null, title: '', description: null, visibility: null, fields: [] });
    markDirty();
};

// Warn before losing unsaved structure edits: closing the tab, a full-page
// navigation (the Responses/Preview links are plain <a> tags), or an Inertia
// SPA navigation (e.g. clicking a sidebar link).
const beforeUnloadHandler = (event: BeforeUnloadEvent) => {
    if (structureDirty.value) {
        event.preventDefault();
    }
};

let stopInertiaGuard: (() => void) | undefined;

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnloadHandler);
    stopInertiaGuard = router.on('before', () => {
        // The structure-save request (manual or auto-saved) is itself an
        // Inertia visit — don't let it trip its own "leave page?" guard.
        if (!structureDirty.value || savingInProgress.value) {
            return true;
        }

        return confirm(trans('You have unsaved changes. Leave this page anyway?'));
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnloadHandler);
    stopInertiaGuard?.();
    clearTimeout(autoSaveTimeout);
});

const sectionToRemove = ref<number | null>(null);

const confirmRemoveSection = () => {
    if (sectionToRemove.value === null) {
        return;
    }

    sections.splice(sectionToRemove.value, 1);
    sectionToRemove.value = null;
    markDirty();
};

const saveStructure = () => {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = undefined;

    savingInProgress.value = true;
    savingStructure.value = true;
    structureError.value = null;
    structureFieldErrors.value = {};

    router.put(
        route('forms.structure.update', props.form.id),
        { sections: sections as unknown as Record<string, unknown>[] },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                // Pick up the ids assigned by the server so the next save updates
                // fields instead of recreating them.
                resyncSections((page.props.form as FormData).sections);
                structureDirty.value = false;
            },
            onError: (errors) => {
                structureFieldErrors.value = errors as Record<string, string>;
                structureError.value = Object.values(errors)[0] ?? trans('An error occurred while saving.');
            },
            onFinish: () => {
                savingStructure.value = false;
                savingInProgress.value = false;

                // Edits made while this request was in flight (or a failed save)
                // leave the structure dirty — make sure that isn't stranded
                // without a pending autosave.
                if (structureDirty.value) {
                    scheduleAutoSave();
                }
            },
        },
    );
};

/* ------------------------------------------------------------------ */
/* Form settings                                                       */
/* ------------------------------------------------------------------ */

const settingsOpen = ref(false);

const settingsForm = useForm({
    title: props.form.title,
    slug: props.form.slug,
    description: props.form.description ?? '',
    primary_color: props.form.primary_color ?? '#2563eb',
    require_email_verification: props.form.require_email_verification,
    notify_on_response: props.form.notify_on_response,
    notification_emails: [...props.form.notification_emails],
    max_responses: props.form.max_responses,
    expires_at: props.form.expires_at ?? '',
    retention_days: props.form.retention_days,
    success_message: props.form.success_message ?? '',
});

// Quick picks for the theme color; any other color stays available via the picker.
const THEME_PRESETS = ['#2563eb', '#0891b2', '#059669', '#65a30d', '#d97706', '#dc2626', '#db2777', '#7c3aed', '#334155'];

const addNotificationEmail = () => settingsForm.notification_emails.push('');
const removeNotificationEmail = (index: number) => settingsForm.notification_emails.splice(index, 1);

// Keep the editable slug in sync after a successful rename (the dialog is
// closed on success, so this never clobbers an in-progress edit).
watch(
    () => props.form.slug,
    (slug) => (settingsForm.slug = slug),
);

const publicBaseUrl = computed(() => props.form.public_url.replace(`/f/${props.form.slug}`, '/f/'));

const saveSettings = () => {
    settingsForm
        .transform((data) => ({
            ...data,
            expires_at: data.expires_at || null,
            description: data.description || null,
            success_message: data.success_message || null,
            notification_emails: data.notification_emails.map((email) => email.trim()).filter((email) => email !== ''),
        }))
        .put(route('forms.update', props.form.id), {
            preserveScroll: true,
            onSuccess: () => (settingsOpen.value = false),
        });
};

// Discard any in-progress dialog edits (slug, color, notifications…) instead
// of leaving them sitting in the shared form object, where they'd otherwise
// get silently submitted next time the auto-saving header title/description
// fields fire a save.
const closeSettings = () => {
    settingsForm.reset();
    settingsForm.clearErrors();
    settingsOpen.value = false;
};

/* ------------------------------------------------------------------ */
/* Sharing                                                             */
/* ------------------------------------------------------------------ */

const shareOpen = ref(false);

const shareForm = useForm({
    email: '',
});

const submitShare = () => {
    shareForm.post(route('forms.shares.store', props.form.id), {
        preserveScroll: true,
        onSuccess: () => shareForm.reset(),
    });
};

const removeShare = (shareId: number) => {
    router.delete(route('forms.shares.destroy', [props.form.id, shareId]), { preserveScroll: true });
};

/* ------------------------------------------------------------------ */
/* Ownership transfer                                                  */
/* ------------------------------------------------------------------ */

const transferForm = useForm({
    email: '',
});

const transferConfirmOpen = ref(false);

const askTransferConfirmation = () => {
    if (transferForm.email.trim() !== '') {
        transferConfirmOpen.value = true;
    }
};

const confirmTransfer = () => {
    transferForm.post(route('forms.owner.update', props.form.id), {
        preserveScroll: true,
        onSuccess: () => {
            transferForm.reset();
            transferConfirmOpen.value = false;
        },
    });
};

/* ------------------------------------------------------------------ */
/* Logo                                                                */
/* ------------------------------------------------------------------ */

const logoInput = ref<HTMLInputElement | null>(null);
const logoError = ref<string | null>(null);

const uploadLogo = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    logoError.value = null;

    router.post(
        route('forms.logo.upload', props.form.id),
        { logo: file },
        {
            preserveScroll: true,
            forceFormData: true,
            onError: (errors) => {
                logoError.value = errors.logo ?? trans('An error occurred while saving.');
            },
            onFinish: () => (input.value = ''),
        },
    );
};

const removeLogo = () => {
    logoError.value = null;

    router.delete(route('forms.logo.delete', props.form.id), { preserveScroll: true });
};

/* ------------------------------------------------------------------ */
/* Status                                                              */
/* ------------------------------------------------------------------ */

const setStatus = (status: 'draft' | 'published' | 'closed') => {
    router.post(route('forms.status.update', props.form.id), { status }, { preserveScroll: true });
};

const copyPublicLink = async () => {
    await navigator.clipboard.writeText(props.form.public_url);
};

const statusLabel = computed(() => {
    return {
        draft: trans('Draft'),
        published: trans('Published'),
        closed: trans('Closed'),
    }[props.form.status];
});
</script>

<template>
    <Head :title="form.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
            <!-- Toolbar -->
            <div class="bg-background/95 sticky top-16 z-10 -mx-6 flex flex-wrap items-center justify-between gap-3 px-6 py-3 backdrop-blur">
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-muted text-muted-foreground': form.status === 'draft',
                            'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': form.status === 'published',
                            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': form.status === 'closed',
                        }"
                    >
                        {{ statusLabel }}
                    </span>
                    <span class="text-muted-foreground text-sm">{{ form.responses_count }} {{ $t('response(s)') }}</span>
                    <span v-if="form.is_shared_with_me" class="text-muted-foreground text-sm">
                        {{ $t('Shared by :name', { name: form.owner.name }) }}
                    </span>
                    <span v-else-if="!form.is_owner" class="text-muted-foreground text-sm">
                        {{ $t('Owned by :name', { name: form.owner.name }) }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a :href="route('forms.responses.index', form.id)">
                            <Inbox class="mr-1 h-4 w-4" />
                            {{ $t('Responses') }}
                        </a>
                    </Button>

                    <Button v-if="form.can_manage_shares" variant="outline" size="sm" @click="shareOpen = true">
                        <Users class="mr-1 h-4 w-4" />
                        {{ $t('Share') }}
                    </Button>

                    <Button variant="outline" size="sm" @click="settingsOpen = true">
                        <Settings2 class="mr-1 h-4 w-4" />
                        {{ $t('Settings') }}
                    </Button>

                    <Button variant="outline" size="sm" as-child>
                        <a :href="form.public_url" target="_blank">
                            <ExternalLink class="mr-1 h-4 w-4" />
                            {{ $t('Preview') }}
                        </a>
                    </Button>

                    <Separator orientation="vertical" class="h-6" />

                    <template v-if="form.status === 'published'">
                        <Button variant="outline" size="sm" @click="copyPublicLink">
                            <LinkIcon class="mr-1 h-4 w-4" />
                            {{ $t('Copy public link') }}
                        </Button>
                        <Button variant="outline" size="sm" @click="setStatus('closed')">{{ $t('Close form') }}</Button>
                    </template>
                    <Button v-if="form.status !== 'draft'" variant="outline" size="sm" @click="setStatus('draft')">
                        <EyeOff class="mr-1 h-4 w-4" />
                        {{ $t('Unpublish') }}
                    </Button>
                    <Button v-if="form.status !== 'published'" size="sm" variant="secondary" @click="setStatus('published')">
                        {{ $t('Publish') }}
                    </Button>

                    <Button size="sm" :disabled="!structureDirty || savingStructure" @click="saveStructure">
                        <Save class="mr-1 h-4 w-4" />
                        {{ savingStructure ? $t('Saving…') : structureDirty ? $t('Save') : $t('Saved') }}
                    </Button>
                </div>
            </div>

            <p v-if="structureError" class="text-sm text-red-600">{{ structureError }}</p>

            <!-- Header card: logo + title + description. Topped with the form's theme
                 color so it reads as the form's cover, distinct from the sections. -->
            <Card class="overflow-hidden border-t-8 shadow-sm" :style="{ borderTopColor: form.primary_color || 'hsl(var(--primary))' }">
                <CardContent class="flex flex-col gap-4 pt-5">
                    <p class="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                        <FileText class="h-3.5 w-3.5" />
                        {{ $t('Form header') }}
                    </p>

                    <div class="flex items-center gap-4">
                        <div class="bg-muted/40 flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border">
                            <img v-if="form.logo_url" :src="form.logo_url" alt="Logo" class="h-full w-full object-contain" />
                            <ImageIcon v-else class="text-muted-foreground h-6 w-6" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="logoInput?.click()">
                                    {{ form.logo_url ? $t('Change logo') : $t('Add logo') }}
                                </Button>
                                <Button v-if="form.logo_url" variant="ghost" size="sm" class="text-red-600" @click="removeLogo">
                                    {{ $t('Remove') }}
                                </Button>
                            </div>
                            <p class="text-muted-foreground text-xs">{{ $t('PNG, JPG, SVG or WebP — max 2 MB.') }}</p>
                            <input ref="logoInput" type="file" accept=".png,.jpg,.jpeg,.webp,.svg" class="hidden" @change="uploadLogo" />
                            <InputError :message="logoError" />
                        </div>
                    </div>

                    <div class="grid gap-1">
                        <Label for="form-title" class="sr-only">{{ $t('Title') }}</Label>
                        <Input
                            id="form-title"
                            v-model="settingsForm.title"
                            class="hover:border-input focus-visible:border-input h-auto border-transparent px-2 py-1 text-2xl font-bold shadow-none md:text-2xl"
                            :class="settingsForm.errors.title ? 'border-red-500' : ''"
                            :placeholder="$t('Title')"
                            @change="saveSettings"
                        />
                        <InputError :message="settingsForm.errors.title" />
                    </div>

                    <div class="grid gap-1">
                        <Label for="form-description" class="sr-only">{{ $t('Description (optional)') }}</Label>
                        <textarea
                            id="form-description"
                            v-model="settingsForm.description"
                            rows="2"
                            class="ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring hover:border-input focus-visible:border-input text-muted-foreground flex w-full rounded-md border border-transparent bg-transparent px-2 py-1 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                            :class="settingsForm.errors.description ? 'border-red-500' : ''"
                            :placeholder="$t('Description (optional)') + ' — ' + $t('Shown at the top of the public form')"
                            @change="saveSettings"
                        ></textarea>
                        <InputError :message="settingsForm.errors.description" />
                    </div>
                </CardContent>
            </Card>

            <!-- Sections -->
            <draggable :list="sections" item-key="id" handle=".section-drag-handle" class="flex flex-col gap-6" @end="markDirty">
                <template #item="{ element: section, index: sectionIndex }">
                    <Card class="border-l-primary overflow-hidden border-l-4 shadow-sm">
                        <CardHeader class="bg-primary/5 flex flex-row items-start gap-3 space-y-0 border-b py-4">
                            <button
                                type="button"
                                class="section-drag-handle text-muted-foreground hover:text-foreground mt-1 cursor-grab"
                                :aria-label="$t('Reorder')"
                            >
                                <GripVertical class="h-4 w-4" />
                            </button>
                            <div class="grid flex-1 gap-1">
                                <p class="text-primary text-xs font-semibold tracking-wide uppercase">
                                    {{ $t('Section :number of :total', { number: String(sectionIndex + 1), total: String(sections.length) }) }}
                                    <span class="text-muted-foreground font-normal normal-case">
                                        · {{ $t(':count question(s)', { count: String(section.fields.length) }) }}
                                    </span>
                                </p>
                                <Input
                                    v-model="section.title"
                                    class="h-auto border-0 bg-transparent px-0 py-0.5 text-lg font-semibold shadow-none focus-visible:ring-0 md:text-lg"
                                    :class="sectionErrors(sectionIndex).title ? 'ring-1 ring-red-500' : ''"
                                    :placeholder="$t('Section title (optional)')"
                                    @input="markDirty"
                                />
                                <InputError :message="sectionErrors(sectionIndex).title" />
                                <Input
                                    v-model="section.description"
                                    class="h-8 border-0 bg-transparent px-0 text-sm shadow-none focus-visible:ring-0"
                                    :class="sectionErrors(sectionIndex).description ? 'ring-1 ring-red-500' : ''"
                                    :placeholder="$t('Section description (optional)')"
                                    @input="markDirty"
                                />
                                <InputError :message="sectionErrors(sectionIndex).description" />
                                <VisibilityEditor
                                    :target="section"
                                    :conditionable-fields="sectionConditionableFields(section)"
                                    :errors="sectionVisibilityErrors(sectionIndex)"
                                    empty-hint="Save a question in another section first, then come back here to set a condition."
                                />
                            </div>
                            <Button
                                v-if="sections.length > 1"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:text-red-600"
                                @click="sectionToRemove = sectionIndex"
                            >
                                {{ $t('Remove section') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="bg-muted/30 flex flex-col gap-3 py-4">
                            <draggable
                                :list="section.fields"
                                item-key="id"
                                handle=".drag-handle"
                                group="fields"
                                class="flex flex-col gap-3"
                                @end="markDirty"
                            >
                                <template #item="{ element: field, index: fieldIndex }">
                                    <FieldEditor
                                        :field="field"
                                        :number="`${sectionIndex + 1}.${fieldIndex + 1}`"
                                        :max-upload-kb="maxUploadKb"
                                        :conditionable-fields="conditionableFields"
                                        :field-errors="fieldErrorsFor(sectionIndex, fieldIndex)"
                                        @remove="removeField(section, fieldIndex)"
                                    />
                                </template>
                            </draggable>

                            <p
                                v-if="section.fields.length === 0"
                                class="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm"
                            >
                                {{ $t('Empty section — add a question below.') }}
                            </p>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="outline"
                                        class="hover:border-primary/50 hover:bg-primary/5 hover:text-primary w-full border-dashed"
                                    >
                                        <Plus class="mr-1 h-4 w-4" />
                                        {{ $t('Add a question') }}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" class="w-56">
                                    <DropdownMenuLabel>{{ $t('Question type') }}</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem v-for="item in fieldPalette" :key="item.type" @click="addField(section, item.type)">
                                        <component :is="item.icon" class="text-muted-foreground mr-2 h-4 w-4" />
                                        {{ $t(item.label) }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </CardContent>
                    </Card>
                </template>
            </draggable>

            <Button variant="outline" class="hover:border-primary/50 hover:bg-primary/5 hover:text-primary w-full border-dashed" @click="addSection">
                <ListPlus class="mr-1 h-4 w-4" />
                {{ $t('Add a section') }}
            </Button>
        </div>

        <!-- Settings dialog: fixed header/footer, scrollable body grouped by topic -->
        <Dialog :open="settingsOpen" @update:open="(open: boolean) => (open ? (settingsOpen = true) : closeSettings())">
            <DialogContent class="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader class="border-b px-6 pt-6 pb-4">
                    <DialogTitle>{{ $t('Form settings') }}</DialogTitle>
                    <DialogDescription>{{ $t('Access rules, notifications and GDPR retention.') }}</DialogDescription>
                </DialogHeader>

                <div class="grid min-w-0 gap-4 overflow-y-auto px-6 py-5">
                    <!-- Link & appearance -->
                    <section class="grid min-w-0 gap-4 rounded-lg border p-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <Palette class="text-muted-foreground h-4 w-4" />
                            {{ $t('Link & appearance') }}
                        </h3>

                        <div class="grid min-w-0 gap-2">
                            <Label for="form-slug">{{ $t('Public link') }}</Label>
                            <div
                                class="border-input bg-background focus-within:ring-ring flex min-w-0 items-center overflow-hidden rounded-md border text-sm focus-within:ring-2"
                            >
                                <span
                                    class="bg-muted/50 text-muted-foreground max-w-[60%] shrink truncate border-r px-3 py-2"
                                    :title="publicBaseUrl"
                                    dir="rtl"
                                >
                                    <bdi>{{ publicBaseUrl }}</bdi>
                                </span>
                                <input
                                    id="form-slug"
                                    v-model="settingsForm.slug"
                                    class="min-w-0 flex-1 bg-transparent px-3 py-2 outline-none"
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    maxlength="32"
                                />
                            </div>
                            <p class="text-muted-foreground text-xs">
                                {{ $t('Lowercase letters, numbers and hyphens only. Changing this breaks previously shared links.') }}
                            </p>
                            <InputError :message="settingsForm.errors.slug" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="theme-color">{{ $t('Theme color') }}</Label>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-for="color in THEME_PRESETS"
                                    :key="color"
                                    type="button"
                                    class="h-7 w-7 rounded-full border-2 transition-transform hover:scale-110"
                                    :class="settingsForm.primary_color.toLowerCase() === color ? 'border-foreground' : 'border-transparent'"
                                    :style="{ backgroundColor: color }"
                                    :aria-label="color"
                                    :aria-pressed="settingsForm.primary_color.toLowerCase() === color"
                                    @click="settingsForm.primary_color = color"
                                />
                                <label class="hover:bg-muted ml-1 flex cursor-pointer items-center gap-2 rounded-md border px-2 py-1 text-sm">
                                    <input
                                        id="theme-color"
                                        v-model="settingsForm.primary_color"
                                        type="color"
                                        class="h-5 w-5 cursor-pointer rounded border-0 bg-transparent p-0"
                                    />
                                    <span class="text-muted-foreground font-mono text-xs">{{ settingsForm.primary_color }}</span>
                                </label>
                            </div>
                            <InputError :message="settingsForm.errors.primary_color" />
                        </div>
                    </section>

                    <!-- Access -->
                    <section class="grid gap-4 rounded-lg border p-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <ShieldCheck class="text-muted-foreground h-4 w-4" />
                            {{ $t('Access') }}
                        </h3>

                        <div class="flex items-start gap-2">
                            <Checkbox id="require-email" v-model:checked="settingsForm.require_email_verification" class="mt-0.5" />
                            <div class="grid gap-1">
                                <Label for="require-email" class="font-normal">{{ $t('Require email verification') }}</Label>
                                <p class="text-muted-foreground text-xs">
                                    {{ $t('Respondents must confirm their email address with a code before submitting.') }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="max-responses">{{ $t('Response limit') }}</Label>
                                <Input
                                    id="max-responses"
                                    v-model.number="settingsForm.max_responses"
                                    type="number"
                                    min="1"
                                    :placeholder="$t('Unlimited')"
                                />
                                <InputError :message="settingsForm.errors.max_responses" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="expires-at">{{ $t('Closes on') }}</Label>
                                <Input id="expires-at" v-model="settingsForm.expires_at" type="datetime-local" />
                                <InputError :message="settingsForm.errors.expires_at" />
                            </div>
                        </div>
                    </section>

                    <!-- Notifications -->
                    <section class="grid gap-4 rounded-lg border p-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <Bell class="text-muted-foreground h-4 w-4" />
                            {{ $t('Notifications') }}
                        </h3>

                        <div class="flex items-start gap-2">
                            <Checkbox id="notify" v-model:checked="settingsForm.notify_on_response" class="mt-0.5" />
                            <Label for="notify" class="font-normal">{{ $t('Email me on new response') }}</Label>
                        </div>

                        <div v-if="settingsForm.notify_on_response" class="grid gap-2 border-l-2 pl-4">
                            <Label>{{ $t('Notification recipients') }}</Label>
                            <div v-for="(email, index) in settingsForm.notification_emails" :key="index" class="flex items-center gap-2">
                                <Input
                                    v-model="settingsForm.notification_emails[index]"
                                    type="email"
                                    class="h-9"
                                    :placeholder="$t('your@email.com')"
                                />
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground h-8 w-8 shrink-0"
                                    :aria-label="$t('Remove')"
                                    @click="removeNotificationEmail(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                            <Button variant="outline" size="sm" class="w-fit" @click="addNotificationEmail">
                                <Plus class="mr-1 h-4 w-4" />
                                {{ $t('Add recipient') }}
                            </Button>
                            <p class="text-muted-foreground text-xs">
                                {{ $t('Add at least one recipient, otherwise no notification will be sent.') }}
                            </p>
                            <InputError :message="settingsForm.errors.notification_emails" />
                        </div>
                    </section>

                    <!-- After submission & GDPR -->
                    <section class="grid gap-4 rounded-lg border p-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <ClipboardCheck class="text-muted-foreground h-4 w-4" />
                            {{ $t('After submission & GDPR') }}
                        </h3>

                        <div class="grid gap-2">
                            <Label for="success-message">{{ $t('Thank you message') }}</Label>
                            <textarea
                                id="success-message"
                                v-model="settingsForm.success_message"
                                rows="2"
                                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                :placeholder="$t('Shown after a response is submitted')"
                            ></textarea>
                            <InputError :message="settingsForm.errors.success_message" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="retention">{{ $t('Retention period (days)') }}</Label>
                            <Input
                                id="retention"
                                v-model.number="settingsForm.retention_days"
                                type="number"
                                min="1"
                                max="3650"
                                class="sm:w-48"
                                :placeholder="String(defaultRetentionDays)"
                            />
                            <p class="text-muted-foreground text-xs">
                                {{
                                    $t('Responses and files are automatically deleted after this period (GDPR). Empty = default (:days days).', {
                                        days: String(defaultRetentionDays),
                                    })
                                }}
                            </p>
                            <InputError :message="settingsForm.errors.retention_days" />
                        </div>
                    </section>
                </div>

                <DialogFooter class="bg-muted/30 gap-2 border-t px-6 py-4">
                    <Button variant="outline" @click="closeSettings">{{ $t('Cancel') }}</Button>
                    <Button :disabled="settingsForm.processing" @click="saveSettings">{{ $t('Save') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Remove section confirmation -->
        <Dialog :open="sectionToRemove !== null" @update:open="(value: boolean) => !value && (sectionToRemove = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('Remove this section?') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('The section and all of its questions will be removed once you save the form.') }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="sectionToRemove = null">{{ $t('Cancel') }}</Button>
                    <Button variant="destructive" @click="confirmRemoveSection">{{ $t('Remove section') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Share dialog -->
        <Dialog v-model:open="shareOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Share this form') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('People you add can edit this form and view its responses.') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="flex items-start gap-2" @submit.prevent="submitShare">
                    <div class="flex-1">
                        <Input v-model="shareForm.email" type="email" required :placeholder="$t('Email address')" />
                        <InputError :message="shareForm.errors.email" />
                    </div>
                    <Button type="submit" :disabled="shareForm.processing">{{ $t('Add') }}</Button>
                </form>

                <div v-if="form.shares && form.shares.length > 0" class="flex flex-col divide-y rounded-md border">
                    <div v-for="share in form.shares" :key="share.id" class="flex items-center justify-between gap-3 px-3 py-2">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium">{{ share.user.name }}</span>
                            <span class="text-muted-foreground text-xs">{{ share.user.email }}</span>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-red-600"
                            :aria-label="$t('Remove')"
                            @click="removeShare(share.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <p v-else class="text-muted-foreground text-sm">{{ $t('Not shared with anyone yet.') }}</p>

                <div v-if="form.can_transfer_ownership" class="grid gap-2 border-t pt-4">
                    <Label>{{ $t('Transfer ownership') }}</Label>
                    <p class="text-muted-foreground text-xs">
                        {{ $t("You'll keep access to the form as a collaborator after the transfer.") }}
                    </p>
                    <form class="flex items-start gap-2" @submit.prevent="askTransferConfirmation">
                        <div class="flex-1">
                            <Input v-model="transferForm.email" type="email" required :placeholder="$t('Email address')" />
                            <InputError :message="transferForm.errors.email" />
                        </div>
                        <Button type="submit" variant="outline">{{ $t('Transfer') }}</Button>
                    </form>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="shareOpen = false">{{ $t('Close') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Ownership transfer confirmation -->
        <Dialog v-model:open="transferConfirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Transfer ownership?') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('This form will belong to :email. You will keep access as a collaborator.', { email: transferForm.email }) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="transferConfirmOpen = false">{{ $t('Cancel') }}</Button>
                    <Button :disabled="transferForm.processing" @click="confirmTransfer">{{ $t('Transfer') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
