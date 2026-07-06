<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Download, Eye, FileDown, MailCheck, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface ResponseAnswer {
    field_id: number;
    value: string | string[] | null;
    file_name: string | null;
    file_size: number | null;
    file_url: string | null;
}

interface ResponseRow {
    id: number;
    email: string | null;
    email_verified: boolean;
    submitted_at: string;
    consented_at: string;
    answers: ResponseAnswer[];
}

const props = defineProps<{
    form: {
        id: number;
        title: string;
        status: string;
        retention_days: number;
    };
    fields: { id: number; type: string; label: string }[];
    responses: {
        data: ResponseRow[];
        meta: { current_page: number; last_page: number; total: number };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: trans('Forms'), href: '/forms' },
    { title: props.form.title, href: route('forms.edit', props.form.id) },
    { title: trans('Responses'), href: route('forms.responses.index', props.form.id) },
];

const selected = ref<ResponseRow | null>(null);
const deleteCandidate = ref<ResponseRow | null>(null);

const answerFor = (response: ResponseRow, fieldId: number) => response.answers.find((answer) => answer.field_id === fieldId);

const displayValue = (answer: ResponseAnswer | undefined) => {
    if (!answer) {
        return '—';
    }

    if (answer.file_name) {
        return answer.file_name;
    }

    if (Array.isArray(answer.value)) {
        return answer.value.join(', ');
    }

    return answer.value ?? '—';
};

const formatDateTime = (value: string) => new Date(value).toLocaleString();

const formatSize = (bytes: number | null) => {
    if (!bytes) {
        return '';
    }

    return bytes > 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`;
};

const confirmDelete = () => {
    if (!deleteCandidate.value) {
        return;
    }

    router.delete(route('forms.responses.destroy', [props.form.id, deleteCandidate.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            deleteCandidate.value = null;
            selected.value = null;
        },
    });
};

const goToPage = (pageNumber: number) => {
    router.get(route('forms.responses.index', props.form.id), { page: pageNumber }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${$t('Responses')} — ${form.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ form.title }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ responses.meta.total }} {{ $t('response(s)') }} ·
                        {{ $t('kept :days days', { days: String(form.retention_days) }) }}
                    </p>
                </div>
                <Button variant="outline" as-child :disabled="responses.meta.total === 0">
                    <a :href="route('forms.responses.export', form.id)">
                        <FileDown class="mr-1 h-4 w-4" />
                        {{ $t('Export CSV') }}
                    </a>
                </Button>
            </div>

            <div v-if="responses.data.length === 0" class="rounded-xl border border-dashed p-16 text-center text-muted-foreground">
                {{ $t('No responses yet.') }}
            </div>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="whitespace-nowrap px-4 py-3 font-medium">{{ $t('Date') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 font-medium">{{ $t('Email') }}</th>
                            <th v-for="field in fields.slice(0, 4)" :key="field.id" class="max-w-48 truncate px-4 py-3 font-medium">
                                {{ field.label }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="response in responses.data" :key="response.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="whitespace-nowrap px-4 py-3">{{ formatDateTime(response.submitted_at) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span v-if="response.email" class="inline-flex items-center gap-1">
                                    {{ response.email }}
                                    <MailCheck v-if="response.email_verified" class="h-3.5 w-3.5 text-green-600" />
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td v-for="field in fields.slice(0, 4)" :key="field.id" class="max-w-48 truncate px-4 py-3">
                                {{ displayValue(answerFor(response, field.id)) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <Button variant="ghost" size="icon" class="h-8 w-8" :aria-label="$t('View')" @click="selected = response">
                                    <Eye class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-red-600"
                                    :aria-label="$t('Delete')"
                                    @click="deleteCandidate = response"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="responses.meta.last_page > 1" class="flex items-center justify-center gap-2">
                <Button variant="outline" size="sm" :disabled="responses.meta.current_page <= 1" @click="goToPage(responses.meta.current_page - 1)">
                    {{ $t('Previous') }}
                </Button>
                <span class="text-sm text-muted-foreground">{{ responses.meta.current_page }} / {{ responses.meta.last_page }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="responses.meta.current_page >= responses.meta.last_page"
                    @click="goToPage(responses.meta.current_page + 1)"
                >
                    {{ $t('Next') }}
                </Button>
            </div>
        </div>

        <!-- Response detail -->
        <Dialog :open="selected !== null" @update:open="(value: boolean) => !value && (selected = null)">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('Response detail') }}</DialogTitle>
                    <DialogDescription v-if="selected">
                        {{ formatDateTime(selected.submitted_at) }}
                        <template v-if="selected.email"> · {{ selected.email }}</template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selected" class="flex flex-col gap-4 py-2">
                    <div v-for="field in fields" :key="field.id" class="grid gap-1">
                        <p class="text-sm font-medium">{{ field.label }}</p>
                        <template v-if="answerFor(selected, field.id)?.file_url">
                            <a
                                :href="answerFor(selected, field.id)!.file_url!"
                                class="inline-flex w-fit items-center gap-2 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                            >
                                <Download class="h-4 w-4" />
                                {{ answerFor(selected, field.id)!.file_name }}
                                <span class="text-xs text-muted-foreground">{{ formatSize(answerFor(selected, field.id)!.file_size) }}</span>
                            </a>
                        </template>
                        <p v-else class="whitespace-pre-line text-sm text-muted-foreground">{{ displayValue(answerFor(selected, field.id)) }}</p>
                    </div>

                    <p class="border-t pt-3 text-xs text-muted-foreground">
                        {{ $t('Consent given on :date.', { date: formatDateTime(selected.consented_at) }) }}
                    </p>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <Dialog :open="deleteCandidate !== null" @update:open="(value: boolean) => !value && (deleteCandidate = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('Delete this response?') }}</DialogTitle>
                    <DialogDescription>{{ $t('The response and its uploaded files will be permanently deleted (GDPR erasure).') }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteCandidate = null">{{ $t('Cancel') }}</Button>
                    <Button variant="destructive" @click="confirmDelete">{{ $t('Delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
