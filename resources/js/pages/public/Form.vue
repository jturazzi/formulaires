<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type FieldVisibility, type SharedData, type VisibilityCondition, type VisibilityOperator } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, MailCheck } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';

interface PublicField {
    id: number;
    type: string;
    label: string;
    description: string | null;
    required: boolean;
    options: {
        choices?: string[];
        max_length?: number;
        min?: number;
        max?: number;
        min_date?: string;
        max_date?: string;
        allow_other?: boolean;
    } | null;
    visibility: FieldVisibility | null;
}

interface PublicSection {
    id: number;
    title: string | null;
    description: string | null;
    fields: PublicField[];
}

const props = defineProps<{
    form: {
        slug: string;
        title: string;
        description: string | null;
        logo_url: string | null;
        primary_color: string | null;
        require_email_verification: boolean;
        retention_days: number;
        sections: PublicSection[];
    };
    closed: boolean;
    preview: boolean;
}>();

const page = usePage<SharedData>();

const accent = computed(() => props.form.primary_color ?? '#2563eb');

const submission = useForm<{
    email: string;
    code: string;
    consent: boolean;
    answers: Record<number, string | string[] | File | null>;
}>({
    email: '',
    code: '',
    consent: false,
    answers: {},
});

/* Email verification -------------------------------------------------- */

const codeForm = useForm({ email: '' });
const codeSent = ref(false);

const sendCode = () => {
    codeForm.email = submission.email;
    codeForm.post(route('public.forms.email-code', props.form.slug), {
        preserveScroll: true,
        onSuccess: () => (codeSent.value = true),
    });
};

/* Answers ------------------------------------------------------------- */

const setFile = (fieldId: number, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    submission.answers[fieldId] = file;
};

const toggleCheckbox = (fieldId: number, choice: string, checked: boolean) => {
    const current = (submission.answers[fieldId] as string[] | undefined) ?? [];
    submission.answers[fieldId] = checked ? [...current, choice] : current.filter((item) => item !== choice);
};

const isChecked = (fieldId: number, choice: string) => ((submission.answers[fieldId] as string[] | undefined) ?? []).includes(choice);

// Accent-tinted highlight for a selected radio/checkbox row (accent is always a #rrggbb hex value).
const selectedRowStyle = (selected: boolean) => (selected ? { borderColor: accent.value, backgroundColor: `${accent.value}0d` } : {});

/* "Other" free-text answers -------------------------------------------- */

const OTHER_VALUE = '__other__';

// Single-value fields (radio / dropdown): whether the respondent picked "Other".
const otherActive = reactive<Record<number, boolean>>({});

const selectChoice = (fieldId: number, choice: string) => {
    otherActive[fieldId] = false;
    submission.answers[fieldId] = choice;
};

const selectOtherChoice = (fieldId: number) => {
    if (!otherActive[fieldId]) {
        submission.answers[fieldId] = '';
    }
    otherActive[fieldId] = true;
};

const selectDropdownValue = (fieldId: number) => (otherActive[fieldId] ? OTHER_VALUE : ((submission.answers[fieldId] as string | undefined) ?? ''));

const onDropdownChange = (fieldId: number, value: string) => (value === OTHER_VALUE ? selectOtherChoice(fieldId) : selectChoice(fieldId, value));

// Checkboxes (multi-value): the "Other" entry is tracked separately then merged into the answers array.
const otherChecked = reactive<Record<number, boolean>>({});
const otherText = reactive<Record<number, string>>({});

const syncOtherCheckboxValue = (fieldId: number, previousText: string) => {
    const current = ((submission.answers[fieldId] as string[] | undefined) ?? []).filter((item) => item !== previousText);
    const text = otherText[fieldId] ?? '';
    submission.answers[fieldId] = otherChecked[fieldId] && text ? [...current, text] : current;
};

const toggleOtherCheckbox = (fieldId: number, checked: boolean) => {
    const previousText = otherChecked[fieldId] ? (otherText[fieldId] ?? '') : '';
    otherChecked[fieldId] = checked;
    syncOtherCheckboxValue(fieldId, previousText);
};

const updateOtherCheckboxText = (fieldId: number, text: string) => {
    const previousText = otherText[fieldId] ?? '';
    otherText[fieldId] = text;
    syncOtherCheckboxValue(fieldId, previousText);
};

/* Conditional visibility ------------------------------------------------ */

const fieldsById = computed(() => {
    const map = new Map<number, PublicField>();

    for (const section of props.form.sections) {
        for (const field of section.fields) {
            map.set(field.id, field);
        }
    }

    return map;
});

const isEmptyValue = (value: unknown) => value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);

const stringifyValue = (value: unknown) => (Array.isArray(value) ? value.join(', ') : String(value ?? ''));

const compareOrdered = (value: unknown, target: string): number => {
    if (typeof value !== 'object' && value !== '' && !Number.isNaN(Number(value)) && !Number.isNaN(Number(target))) {
        return Number(value) - Number(target);
    }

    const a = Date.parse(String(value ?? ''));
    const b = Date.parse(target);

    return Number.isNaN(a) || Number.isNaN(b) ? 0 : a - b;
};

const evaluateCondition = (condition: VisibilityCondition, value: unknown): boolean => {
    const target = condition.value ?? '';
    const empty = isEmptyValue(value);

    switch (condition.operator as VisibilityOperator) {
        case 'empty':
            return empty;
        case 'not_empty':
            return !empty;
        case 'equals':
            return !empty && stringifyValue(value) === target;
        case 'not_equals':
            return empty || stringifyValue(value) !== target;
        case 'contains':
            return Array.isArray(value) ? value.map(String).includes(target) : String(value ?? '').includes(target);
        case 'not_contains':
            return Array.isArray(value) ? !value.map(String).includes(target) : !String(value ?? '').includes(target);
        case 'greater_than':
            return !empty && compareOrdered(value, target) > 0;
        case 'less_than':
            return !empty && compareOrdered(value, target) < 0;
        default:
            return false;
    }
};

const isFieldVisible = (field: PublicField, seen: Set<number> = new Set()): boolean => {
    const visibility: FieldVisibility | null = field.visibility;

    if (!visibility || !visibility.mode || visibility.conditions.length === 0 || seen.has(field.id)) {
        return true;
    }

    seen.add(field.id);

    const results = visibility.conditions.map((condition) => {
        const target = fieldsById.value.get(condition.field_id);
        const targetVisible = !target || isFieldVisible(target, seen);
        const value = targetVisible ? submission.answers[condition.field_id] : undefined;

        return evaluateCondition(condition, value);
    });

    const matches = visibility.logic === 'any' ? results.some(Boolean) : results.every(Boolean);

    return visibility.mode === 'visible_if' ? matches : !matches;
};

const answerError = (fieldId: number) => (submission.errors as Record<string, string>)[`answers.${fieldId}`];

const errorClass = (fieldId: number) => (answerError(fieldId) ? 'border-red-500 ring-1 ring-red-500 focus-visible:ring-red-500' : '');

const submit = () => {
    // Never send answers for questions the respondent never actually saw.
    for (const field of fieldsById.value.values()) {
        if (!isFieldVisible(field)) {
            delete submission.answers[field.id];
        }
    }

    submission.post(route('public.forms.submit', props.form.slug), {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            const firstKey = Object.keys(errors)[0];
            const fieldId = firstKey?.match(/^answers\.(\d+)/)?.[1];
            const target = fieldId ? document.getElementById(`field-block-${fieldId}`) : document.querySelector('[data-consent-block]');

            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
    });
};
</script>

<template>
    <Head :title="form.title" />

    <div class="bg-muted/40 min-h-screen pb-16" :style="{ '--form-accent': accent }">
        <div class="h-2 w-full" :style="{ backgroundColor: accent }"></div>

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 px-4 pt-10">
            <div class="flex justify-end">
                <LanguageSwitcher />
            </div>

            <!-- Header -->
            <div class="bg-background border-border/60 rounded-2xl border p-8 shadow-sm">
                <img v-if="form.logo_url" :src="form.logo_url" alt="Logo" class="mb-6 max-h-20 object-contain" />
                <h1 class="text-2xl font-bold">{{ form.title }}</h1>
                <p v-if="form.description" class="text-muted-foreground mt-2 whitespace-pre-line">{{ form.description }}</p>
            </div>

            <!-- Preview notice -->
            <div
                v-if="preview"
                class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-center text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ $t("Preview — this form hasn't been published yet. Responses are not being collected.") }}
            </div>

            <!-- Closed notice -->
            <div v-if="closed" class="bg-background border-border/60 rounded-2xl border p-8 text-center shadow-sm">
                <p class="font-medium">{{ $t('This form is no longer accepting responses.') }}</p>
            </div>

            <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
                <!-- Email verification -->
                <div v-if="form.require_email_verification" class="bg-background border-border/60 rounded-2xl border p-6 shadow-sm">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <MailCheck class="h-5 w-5" :style="{ color: accent }" />
                        {{ $t('Verify your email address') }}
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ $t('This form requires a verified email address. Enter your email, receive a code, and type it below.') }}
                    </p>
                    <div class="mt-4 grid gap-3">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Input
                                v-model="submission.email"
                                type="email"
                                :placeholder="$t('your@email.com')"
                                class="flex-1"
                                :class="submission.errors.email || codeForm.errors.email ? 'border-red-500 ring-1 ring-red-500' : ''"
                                required
                            />
                            <Button type="button" variant="outline" :disabled="codeForm.processing || !submission.email" @click="sendCode">
                                <LoaderCircle v-if="codeForm.processing" class="mr-1 h-4 w-4 animate-spin" />
                                {{ codeSent ? $t('Send a new code') : $t('Send the code') }}
                            </Button>
                        </div>
                        <InputError :message="submission.errors.email || codeForm.errors.email" />
                        <template v-if="codeSent">
                            <p class="text-sm text-green-700 dark:text-green-400">{{ $t('Code sent! Check your inbox.') }}</p>
                            <div class="grid gap-2">
                                <Label for="verification-code">{{ $t('Verification code') }}</Label>
                                <Input
                                    id="verification-code"
                                    v-model="submission.code"
                                    inputmode="numeric"
                                    maxlength="6"
                                    class="w-40 text-center text-lg tracking-[0.4em]"
                                    placeholder="••••••"
                                />
                                <InputError :message="submission.errors.code" />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Sections -->
                <div v-for="section in form.sections" :key="section.id" class="bg-background border-border/60 rounded-2xl border p-6 shadow-sm">
                    <div v-if="section.title || section.description" class="mb-5 border-b pb-4">
                        <h2 v-if="section.title" class="text-lg font-semibold">{{ section.title }}</h2>
                        <p v-if="section.description" class="text-muted-foreground mt-1 text-sm whitespace-pre-line">{{ section.description }}</p>
                    </div>

                    <div class="flex flex-col gap-6">
                        <template v-for="field in section.fields" :key="field.id">
                            <template v-if="isFieldVisible(field)">
                                <!-- Static text block -->
                                <p v-if="field.type === 'info'" class="text-sm whitespace-pre-line">{{ field.label }}</p>

                                <div v-else :id="`field-block-${field.id}`" class="grid gap-2">
                                    <Label :for="`field-${field.id}`" class="text-base font-medium">
                                        {{ field.label }}
                                        <span v-if="field.required" class="text-red-600" aria-hidden="true">*</span>
                                    </Label>
                                    <p v-if="field.description" class="text-muted-foreground text-sm">{{ field.description }}</p>

                                    <Input
                                        v-if="field.type === 'text'"
                                        :id="`field-${field.id}`"
                                        v-model="submission.answers[field.id] as string"
                                        :maxlength="field.options?.max_length ?? 255"
                                        :required="field.required"
                                        :class="errorClass(field.id)"
                                    />

                                    <textarea
                                        v-else-if="field.type === 'textarea'"
                                        :id="`field-${field.id}`"
                                        v-model="submission.answers[field.id] as string"
                                        rows="4"
                                        :maxlength="field.options?.max_length ?? 5000"
                                        :required="field.required"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none md:text-sm"
                                        :class="errorClass(field.id)"
                                    ></textarea>

                                    <Input
                                        v-else-if="field.type === 'email'"
                                        :id="`field-${field.id}`"
                                        v-model="submission.answers[field.id] as string"
                                        type="email"
                                        :required="field.required"
                                        :class="errorClass(field.id)"
                                    />

                                    <Input
                                        v-else-if="field.type === 'number'"
                                        :id="`field-${field.id}`"
                                        v-model="submission.answers[field.id] as string"
                                        type="number"
                                        :min="field.options?.min"
                                        :max="field.options?.max"
                                        :required="field.required"
                                        :class="errorClass(field.id)"
                                    />

                                    <Input
                                        v-else-if="field.type === 'date'"
                                        :id="`field-${field.id}`"
                                        v-model="submission.answers[field.id] as string"
                                        type="date"
                                        :min="field.options?.min_date"
                                        :max="field.options?.max_date"
                                        :required="field.required"
                                        :class="errorClass(field.id)"
                                    />

                                    <div v-else-if="field.type === 'choice'" class="grid gap-2">
                                        <label
                                            v-for="choice in field.options?.choices ?? []"
                                            :key="choice"
                                            class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
                                            :class="errorClass(field.id)"
                                            :style="selectedRowStyle(!otherActive[field.id] && submission.answers[field.id] === choice)"
                                        >
                                            <input
                                                type="radio"
                                                :name="`field-${field.id}`"
                                                :checked="!otherActive[field.id] && submission.answers[field.id] === choice"
                                                :required="field.required"
                                                class="h-4 w-4"
                                                :style="{ accentColor: accent }"
                                                @change="selectChoice(field.id, choice)"
                                            />
                                            <span class="text-sm">{{ choice }}</span>
                                        </label>
                                        <template v-if="field.options?.allow_other">
                                            <label
                                                class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
                                                :style="selectedRowStyle(!!otherActive[field.id])"
                                            >
                                                <input
                                                    type="radio"
                                                    :name="`field-${field.id}`"
                                                    :checked="!!otherActive[field.id]"
                                                    class="h-4 w-4"
                                                    :style="{ accentColor: accent }"
                                                    @change="selectOtherChoice(field.id)"
                                                />
                                                <span class="text-sm">{{ $t('Other') }}</span>
                                            </label>
                                            <Input
                                                v-if="otherActive[field.id]"
                                                v-model="submission.answers[field.id] as string"
                                                :placeholder="$t('Please specify')"
                                                :required="field.required"
                                            />
                                        </template>
                                    </div>

                                    <div v-else-if="field.type === 'checkboxes'" class="grid gap-2">
                                        <label
                                            v-for="choice in field.options?.choices ?? []"
                                            :key="choice"
                                            class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
                                            :class="errorClass(field.id)"
                                            :style="selectedRowStyle(isChecked(field.id, choice))"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isChecked(field.id, choice)"
                                                class="h-4 w-4 rounded"
                                                :style="{ accentColor: accent }"
                                                @change="toggleCheckbox(field.id, choice, ($event.target as HTMLInputElement).checked)"
                                            />
                                            <span class="text-sm">{{ choice }}</span>
                                        </label>
                                        <template v-if="field.options?.allow_other">
                                            <label
                                                class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors"
                                                :style="selectedRowStyle(!!otherChecked[field.id])"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :checked="!!otherChecked[field.id]"
                                                    class="h-4 w-4 rounded"
                                                    :style="{ accentColor: accent }"
                                                    @change="toggleOtherCheckbox(field.id, ($event.target as HTMLInputElement).checked)"
                                                />
                                                <span class="text-sm">{{ $t('Other') }}</span>
                                            </label>
                                            <Input
                                                v-if="otherChecked[field.id]"
                                                :model-value="otherText[field.id] ?? ''"
                                                :placeholder="$t('Please specify')"
                                                @update:model-value="updateOtherCheckboxText(field.id, String($event))"
                                            />
                                        </template>
                                    </div>

                                    <select
                                        v-else-if="field.type === 'dropdown'"
                                        :id="`field-${field.id}`"
                                        :value="selectDropdownValue(field.id)"
                                        :required="field.required"
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-none md:text-sm"
                                        :class="errorClass(field.id)"
                                        @change="onDropdownChange(field.id, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="" disabled>{{ $t('Select…') }}</option>
                                        <option v-for="choice in field.options?.choices ?? []" :key="choice" :value="choice">{{ choice }}</option>
                                        <option v-if="field.options?.allow_other" :value="OTHER_VALUE">{{ $t('Other…') }}</option>
                                    </select>
                                    <Input
                                        v-if="field.type === 'dropdown' && otherActive[field.id]"
                                        v-model="submission.answers[field.id] as string"
                                        class="mt-2"
                                        :placeholder="$t('Please specify')"
                                        :required="field.required"
                                    />

                                    <input
                                        v-else-if="field.type === 'file'"
                                        :id="`field-${field.id}`"
                                        type="file"
                                        :required="field.required"
                                        class="border-input bg-background flex w-full cursor-pointer rounded-md border px-3 py-2 text-base file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium md:text-sm"
                                        :class="errorClass(field.id)"
                                        @change="setFile(field.id, $event)"
                                    />

                                    <InputError :message="answerError(field.id)" />
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- GDPR consent -->
                <div
                    data-consent-block
                    class="bg-background border-border/60 rounded-2xl border p-6 shadow-sm"
                    :class="submission.errors.consent ? 'border-red-500 ring-1 ring-red-500' : ''"
                >
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="submission.consent" type="checkbox" required class="mt-1 h-4 w-4 rounded" :style="{ accentColor: accent }" />
                        <span class="text-sm">
                            {{ $t('I have read and accept the') }}
                            <Link :href="route('terms')" target="_blank" class="underline hover:no-underline">{{ $t('terms of use') }}</Link>
                            {{ $t('and the') }}
                            <Link :href="route('privacy')" target="_blank" class="underline hover:no-underline">{{ $t('privacy policy') }}</Link
                            >.
                        </span>
                    </label>
                    <p class="text-muted-foreground mt-3 text-xs">
                        {{
                            $t('Your answers and uploaded documents will be kept for :days days, then automatically deleted (GDPR).', {
                                days: String(form.retention_days),
                            })
                        }}
                    </p>
                    <InputError class="mt-2" :message="submission.errors.consent" />
                </div>

                <Button
                    type="submit"
                    size="lg"
                    class="w-full text-white transition-[filter] hover:brightness-90"
                    :style="{ backgroundColor: accent }"
                    :disabled="submission.processing || preview"
                    :title="preview ? $t('Publish the form to accept responses.') : undefined"
                >
                    <LoaderCircle v-if="submission.processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ $t('Submit my response') }}
                </Button>

                <p v-if="Object.keys(submission.errors).length" class="text-center text-sm text-red-600">
                    {{ $t('Some answers need your attention. Please review the fields above.') }}
                </p>
            </form>

            <footer class="text-muted-foreground flex items-center justify-center gap-4 pt-4 text-xs">
                <span>{{ page.props.name }}</span>
                <Link :href="route('terms')" class="hover:text-foreground">{{ $t('Terms of use') }}</Link>
                <Link :href="route('privacy')" class="hover:text-foreground">{{ $t('Privacy policy') }}</Link>
            </footer>
        </div>
    </div>
</template>
