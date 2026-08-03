<script setup lang="ts">
import FieldEditor from '@/components/builder/FieldEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
    Calendar,
    CheckSquare,
    ChevronDown,
    CircleDot,
    EyeOff,
    ExternalLink,
    FileUp,
    Hash,
    ImageIcon,
    Inbox,
    LinkIcon,
    ListPlus,
    Mail,
    Plus,
    Save,
    Settings2,
    TextCursorInput,
    Trash2,
    Type,
    Users,
} from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';
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

const markDirty = () => {
    if (!syncing.value) {
        structureDirty.value = true;
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

const fieldPalette: { type: FieldType; label: string; icon: typeof Type }[] = [
    { type: 'text', label: 'Short text', icon: TextCursorInput },
    { type: 'textarea', label: 'Long text', icon: AlignLeft },
    { type: 'email', label: 'Email', icon: Mail },
    { type: 'number', label: 'Number', icon: Hash },
    { type: 'date', label: 'Date', icon: Calendar },
    { type: 'choice', label: 'Single choice', icon: CircleDot },
    { type: 'checkboxes', label: 'Multiple choice', icon: CheckSquare },
    { type: 'dropdown', label: 'Dropdown', icon: ChevronDown },
    { type: 'file', label: 'File upload', icon: FileUp },
    { type: 'info', label: 'Text block', icon: Type },
];

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
    sections.push({ id: null, title: '', description: null, fields: [] });
    markDirty();
};

const removeSection = (index: number) => {
    sections.splice(index, 1);
    markDirty();
};

const saveStructure = () => {
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
            onFinish: () => (savingStructure.value = false),
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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
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
                    <span class="text-sm text-muted-foreground">{{ form.responses_count }} {{ $t('response(s)') }}</span>
                    <span v-if="form.is_shared_with_me" class="text-sm text-muted-foreground">
                        {{ $t('Shared by :name', { name: form.owner.name }) }}
                    </span>
                    <span v-else-if="!form.is_owner" class="text-sm text-muted-foreground">
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
                        {{ structureDirty ? $t('Save') : $t('Saved') }}
                    </Button>
                </div>
            </div>

            <p v-if="structureError" class="text-sm text-red-600">{{ structureError }}</p>

            <!-- Header card: logo + title + description -->
            <Card>
                <CardContent class="flex flex-col gap-4 pt-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted/40">
                            <img v-if="form.logo_url" :src="form.logo_url" alt="Logo" class="h-full w-full object-contain" />
                            <ImageIcon v-else class="h-6 w-6 text-muted-foreground" />
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
                            <p class="text-xs text-muted-foreground">{{ $t('PNG, JPG, SVG or WebP — max 2 MB.') }}</p>
                            <input ref="logoInput" type="file" accept=".png,.jpg,.jpeg,.webp,.svg" class="hidden" @change="uploadLogo" />
                            <InputError :message="logoError" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="form-title">{{ $t('Title') }}</Label>
                        <Input id="form-title" v-model="settingsForm.title" class="text-lg font-semibold" @change="saveSettings" />
                        <InputError :message="settingsForm.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="form-description">{{ $t('Description (optional)') }}</Label>
                        <textarea
                            id="form-description"
                            v-model="settingsForm.description"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            :placeholder="$t('Shown at the top of the public form')"
                            @change="saveSettings"
                        ></textarea>
                        <InputError :message="settingsForm.errors.description" />
                    </div>
                </CardContent>
            </Card>

            <!-- Sections -->
            <draggable :list="sections" item-key="id" handle=".section-drag-handle" class="flex flex-col gap-6" @end="markDirty">
                <template #item="{ element: section, index: sectionIndex }">
                    <Card>
                        <CardHeader class="flex flex-row items-start gap-3 space-y-0">
                            <button
                                type="button"
                                class="section-drag-handle mt-2.5 cursor-grab text-muted-foreground hover:text-foreground"
                                :aria-label="$t('Reorder')"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="6" r="1" />
                                    <circle cx="15" cy="6" r="1" />
                                    <circle cx="9" cy="12" r="1" />
                                    <circle cx="15" cy="12" r="1" />
                                    <circle cx="9" cy="18" r="1" />
                                    <circle cx="15" cy="18" r="1" />
                                </svg>
                            </button>
                            <div class="grid flex-1 gap-2">
                                <Input
                                    v-model="section.title"
                                    class="border-0 px-0 text-base font-semibold shadow-none focus-visible:ring-0"
                                    :class="sectionErrors(sectionIndex).title ? 'ring-1 ring-red-500' : ''"
                                    :placeholder="$t('Section title (optional)')"
                                    @input="markDirty"
                                />
                                <InputError :message="sectionErrors(sectionIndex).title" />
                                <Input
                                    v-model="section.description"
                                    class="h-8 border-0 px-0 text-sm shadow-none focus-visible:ring-0"
                                    :class="sectionErrors(sectionIndex).description ? 'ring-1 ring-red-500' : ''"
                                    :placeholder="$t('Section description (optional)')"
                                    @input="markDirty"
                                />
                                <InputError :message="sectionErrors(sectionIndex).description" />
                            </div>
                            <Button
                                v-if="sections.length > 1"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:text-red-600"
                                @click="removeSection(sectionIndex)"
                            >
                                {{ $t('Remove section') }}
                            </Button>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
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
                                        :max-upload-kb="maxUploadKb"
                                        :conditionable-fields="conditionableFields"
                                        :field-errors="fieldErrorsFor(sectionIndex, fieldIndex)"
                                        @remove="removeField(section, fieldIndex)"
                                    />
                                </template>
                            </draggable>

                            <p
                                v-if="section.fields.length === 0"
                                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                            >
                                {{ $t('Empty section — add a question below.') }}
                            </p>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" class="w-fit">
                                        <Plus class="mr-1 h-4 w-4" />
                                        {{ $t('Add a question') }}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" class="w-56">
                                    <DropdownMenuLabel>{{ $t('Question type') }}</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem v-for="item in fieldPalette" :key="item.type" @click="addField(section, item.type)">
                                        <component :is="item.icon" class="mr-2 h-4 w-4 text-muted-foreground" />
                                        {{ $t(item.label) }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </CardContent>
                    </Card>
                </template>
            </draggable>

            <Button variant="outline" class="w-fit" @click="addSection">
                <ListPlus class="mr-1 h-4 w-4" />
                {{ $t('Add a section') }}
            </Button>
        </div>

        <!-- Settings dialog -->
        <Dialog v-model:open="settingsOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('Form settings') }}</DialogTitle>
                    <DialogDescription>{{ $t('Access rules, notifications and GDPR retention.') }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-5 py-2">
                    <div class="grid gap-2">
                        <Label for="form-slug">{{ $t('Public link') }}</Label>
                        <div
                            class="flex items-center rounded-md border border-input bg-background px-3 py-2 text-sm focus-within:ring-2 focus-within:ring-ring"
                        >
                            <span class="whitespace-nowrap text-muted-foreground">{{ publicBaseUrl }}</span>
                            <input
                                id="form-slug"
                                v-model="settingsForm.slug"
                                class="min-w-0 flex-1 bg-transparent outline-none"
                                pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                maxlength="32"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Lowercase letters, numbers and hyphens only. Changing this breaks previously shared links.') }}
                        </p>
                        <InputError :message="settingsForm.errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Theme color') }}</Label>
                        <div class="flex items-center gap-3">
                            <input
                                v-model="settingsForm.primary_color"
                                type="color"
                                class="h-9 w-14 cursor-pointer rounded border bg-background p-1"
                            />
                            <span class="text-sm text-muted-foreground">{{ settingsForm.primary_color }}</span>
                        </div>
                        <InputError :message="settingsForm.errors.primary_color" />
                    </div>

                    <div class="flex items-start gap-2">
                        <Checkbox id="require-email" v-model:checked="settingsForm.require_email_verification" class="mt-0.5" />
                        <div class="grid gap-1">
                            <Label for="require-email" class="font-normal">{{ $t('Require email verification') }}</Label>
                            <p class="text-xs text-muted-foreground">
                                {{ $t('Respondents must confirm their email address with a code before submitting.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <Checkbox id="notify" v-model:checked="settingsForm.notify_on_response" class="mt-0.5" />
                        <div class="grid gap-1">
                            <Label for="notify" class="font-normal">{{ $t('Email me on new response') }}</Label>
                        </div>
                    </div>

                    <div v-if="settingsForm.notify_on_response" class="grid gap-2 pl-6">
                        <Label>{{ $t('Notification recipients') }}</Label>
                        <div v-for="(email, index) in settingsForm.notification_emails" :key="index" class="flex items-center gap-2">
                            <Input v-model="settingsForm.notification_emails[index]" type="email" class="h-9" :placeholder="$t('your@email.com')" />
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 shrink-0 text-muted-foreground"
                                @click="removeNotificationEmail(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <Button variant="outline" size="sm" class="w-fit" @click="addNotificationEmail">
                            <Plus class="mr-1 h-4 w-4" />
                            {{ $t('Add recipient') }}
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Add at least one recipient, otherwise no notification will be sent.') }}
                        </p>
                        <InputError :message="settingsForm.errors.notification_emails" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
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

                    <div class="grid gap-2">
                        <Label for="retention">{{ $t('Retention period (days)') }}</Label>
                        <Input
                            id="retention"
                            v-model.number="settingsForm.retention_days"
                            type="number"
                            min="1"
                            max="3650"
                            :placeholder="String(defaultRetentionDays)"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t('Responses and files are automatically deleted after this period (GDPR). Empty = default (:days days).', {
                                    days: String(defaultRetentionDays),
                                })
                            }}
                        </p>
                        <InputError :message="settingsForm.errors.retention_days" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="success-message">{{ $t('Thank you message') }}</Label>
                        <textarea
                            id="success-message"
                            v-model="settingsForm.success_message"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            :placeholder="$t('Shown after a response is submitted')"
                        ></textarea>
                        <InputError :message="settingsForm.errors.success_message" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="settingsOpen = false">{{ $t('Cancel') }}</Button>
                    <Button :disabled="settingsForm.processing" @click="saveSettings">{{ $t('Save') }}</Button>
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
                            <span class="text-xs text-muted-foreground">{{ share.user.email }}</span>
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
                <p v-else class="text-sm text-muted-foreground">{{ $t('Not shared with anyone yet.') }}</p>

                <div v-if="form.can_transfer_ownership" class="grid gap-2 border-t pt-4">
                    <Label>{{ $t('Transfer ownership') }}</Label>
                    <p class="text-xs text-muted-foreground">
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
