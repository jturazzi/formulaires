<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, MailCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PublicField {
    id: number;
    type: string;
    label: string;
    description: string | null;
    required: boolean;
    options: { choices?: string[]; max_length?: number } | null;
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

const answerError = (fieldId: number) => (submission.errors as Record<string, string>)[`answers.${fieldId}`];

const errorClass = (fieldId: number) => (answerError(fieldId) ? 'border-red-500 ring-1 ring-red-500 focus-visible:ring-red-500' : '');

const submit = () => {
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

    <div class="min-h-screen bg-muted/40 pb-16" :style="{ '--form-accent': accent }">
        <div class="h-2 w-full" :style="{ backgroundColor: accent }"></div>

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 px-4 pt-10">
            <div class="flex justify-end">
                <LanguageSwitcher />
            </div>

            <!-- Header -->
            <div class="rounded-xl border bg-background p-8 shadow-sm">
                <img v-if="form.logo_url" :src="form.logo_url" alt="Logo" class="mb-6 max-h-20 object-contain" />
                <h1 class="text-2xl font-bold">{{ form.title }}</h1>
                <p v-if="form.description" class="mt-2 whitespace-pre-line text-muted-foreground">{{ form.description }}</p>
            </div>

            <!-- Closed notice -->
            <div v-if="closed" class="rounded-xl border bg-background p-8 text-center shadow-sm">
                <p class="font-medium">{{ $t('This form is no longer accepting responses.') }}</p>
            </div>

            <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
                <!-- Email verification -->
                <div v-if="form.require_email_verification" class="rounded-xl border bg-background p-6 shadow-sm">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <MailCheck class="h-5 w-5" :style="{ color: accent }" />
                        {{ $t('Verify your email address') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
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
                <div v-for="section in form.sections" :key="section.id" class="rounded-xl border bg-background p-6 shadow-sm">
                    <div v-if="section.title || section.description" class="mb-5 border-b pb-4">
                        <h2 v-if="section.title" class="text-lg font-semibold">{{ section.title }}</h2>
                        <p v-if="section.description" class="mt-1 whitespace-pre-line text-sm text-muted-foreground">{{ section.description }}</p>
                    </div>

                    <div class="flex flex-col gap-6">
                        <div v-for="field in section.fields" :key="field.id">
                            <!-- Static text block -->
                            <p v-if="field.type === 'info'" class="whitespace-pre-line text-sm">{{ field.label }}</p>

                            <div v-else :id="`field-block-${field.id}`" class="grid gap-2">
                                <Label :for="`field-${field.id}`" class="text-base font-medium">
                                    {{ field.label }}
                                    <span v-if="field.required" class="text-red-600" aria-hidden="true">*</span>
                                </Label>
                                <p v-if="field.description" class="text-sm text-muted-foreground">{{ field.description }}</p>

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
                                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
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
                                    :required="field.required"
                                    :class="errorClass(field.id)"
                                />

                                <Input
                                    v-else-if="field.type === 'date'"
                                    :id="`field-${field.id}`"
                                    v-model="submission.answers[field.id] as string"
                                    type="date"
                                    :required="field.required"
                                    :class="errorClass(field.id)"
                                />

                                <div v-else-if="field.type === 'choice'" class="grid gap-2">
                                    <label
                                        v-for="choice in field.options?.choices ?? []"
                                        :key="choice"
                                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                        :class="errorClass(field.id)"
                                    >
                                        <input
                                            v-model="submission.answers[field.id]"
                                            type="radio"
                                            :name="`field-${field.id}`"
                                            :value="choice"
                                            :required="field.required"
                                            class="h-4 w-4"
                                            :style="{ accentColor: accent }"
                                        />
                                        <span class="text-sm">{{ choice }}</span>
                                    </label>
                                </div>

                                <div v-else-if="field.type === 'checkboxes'" class="grid gap-2">
                                    <label
                                        v-for="choice in field.options?.choices ?? []"
                                        :key="choice"
                                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                        :class="errorClass(field.id)"
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
                                </div>

                                <select
                                    v-else-if="field.type === 'dropdown'"
                                    :id="`field-${field.id}`"
                                    v-model="submission.answers[field.id]"
                                    :required="field.required"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    :class="errorClass(field.id)"
                                >
                                    <option value="" disabled selected>{{ $t('Select…') }}</option>
                                    <option v-for="choice in field.options?.choices ?? []" :key="choice" :value="choice">{{ choice }}</option>
                                </select>

                                <input
                                    v-else-if="field.type === 'file'"
                                    :id="`field-${field.id}`"
                                    type="file"
                                    :required="field.required"
                                    class="flex w-full cursor-pointer rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:border-0 file:bg-transparent file:font-medium"
                                    :class="errorClass(field.id)"
                                    @change="setFile(field.id, $event)"
                                />

                                <InputError :message="answerError(field.id)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GDPR consent -->
                <div
                    data-consent-block
                    class="rounded-xl border bg-background p-6 shadow-sm"
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
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{
                            $t('Your answers and uploaded documents will be kept for :days days, then automatically deleted (GDPR).', {
                                days: String(form.retention_days),
                            })
                        }}
                    </p>
                    <InputError class="mt-2" :message="submission.errors.consent" />
                </div>

                <Button type="submit" size="lg" class="w-full text-white" :style="{ backgroundColor: accent }" :disabled="submission.processing">
                    <LoaderCircle v-if="submission.processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ $t('Submit my response') }}
                </Button>

                <p v-if="Object.keys(submission.errors).length" class="text-center text-sm text-red-600">
                    {{ $t('Some answers need your attention. Please review the fields above.') }}
                </p>
            </form>

            <footer class="flex items-center justify-center gap-4 pt-4 text-xs text-muted-foreground">
                <span>{{ page.props.name }}</span>
                <Link :href="route('terms')" class="hover:text-foreground">{{ $t('Terms of use') }}</Link>
                <Link :href="route('privacy')" class="hover:text-foreground">{{ $t('Privacy policy') }}</Link>
            </footer>
        </div>
    </div>
</template>
