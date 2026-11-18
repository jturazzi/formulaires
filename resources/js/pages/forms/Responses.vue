<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { BarChart3, ChartPie, Download, Eye, ExternalLink, FileDown, List, MailCheck, Star, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';

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

interface SummaryOption {
    label: string | null;
    count: number;
    other: boolean;
}

interface FieldSummary {
    field_id: number;
    answered: number;
    kind: 'choices' | 'rating' | 'number' | 'range' | 'text' | 'file';
    options?: SummaryOption[];
    samples?: string[];
    average?: number | null;
    min?: number | string | null;
    max?: number | string | null;
}

interface Summary {
    total: number;
    last_7_days: number;
    last_submitted_at: string | null;
    timeline: { date: string; count: number }[];
    fields: FieldSummary[];
}

const props = defineProps<{
    view: 'summary' | 'list';
    summary: Summary | null;
    form: {
        id: number;
        title: string;
        status: string;
        retention_days: number;
    };
    fields: { id: number; type: string; label: string; required: boolean; section: string | null }[];
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

// The table only has room for a handful of columns; favor required questions
// (the ones every respondent actually answered) over optional ones, while
// keeping each group in its original order. The detail dialog still shows
// every answer regardless.
const columnFields = computed(() => {
    const required = props.fields.filter((field) => field.required);
    const optional = props.fields.filter((field) => !field.required);

    return [...required, ...optional].slice(0, 4);
});

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

const previewUrl = (answer: ResponseAnswer) => `${answer.file_url}?preview=1`;

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
    router.get(route('forms.responses.index', props.form.id), { view: 'list', page: pageNumber }, { preserveScroll: true });
};

const switchView = (view: 'summary' | 'list') => {
    if (view !== props.view) {
        router.get(route('forms.responses.index', props.form.id), { view }, { preserveScroll: true });
    }
};

/* Summary ---------------------------------------------------------------- */

const fieldById = computed(() => new Map(props.fields.map((field) => [field.id, field])));

const percent = (count: number, of: number) => (of > 0 ? Math.round((count / of) * 100) : 0);

const formatNumber = (value: number | string | null | undefined) =>
    value === null || value === undefined ? '—' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });

const formatRangeValue = (type: string | undefined, value: number | string | null | undefined) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return type === 'date' ? new Date(`${value}T00:00:00`).toLocaleDateString() : String(value);
};

const timelineMax = computed(() => Math.max(1, ...(props.summary?.timeline ?? []).map((day) => day.count)));

const formatDay = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });

// Largest bar of a breakdown, so bars are scaled relative to the top answer.
const optionsMax = (options: SummaryOption[] = []) => Math.max(1, ...options.map((option) => option.count));

// A pie only makes sense when each respondent gives one answer (parts sum to
// 100%): single choice, dropdown and ratings — not checkboxes.
const canPie = (stat: FieldSummary) =>
    stat.kind === 'rating' || (stat.kind === 'choices' && fieldById.value.get(stat.field_id)?.type !== 'checkboxes');

// Chart type picked per question; choices default to a pie, ratings to bars.
const chartMode = reactive<Record<number, 'bar' | 'pie'>>({});

const isPie = (stat: FieldSummary) => canPie(stat) && (chartMode[stat.field_id] ?? (stat.kind === 'choices' ? 'pie' : 'bar')) === 'pie';

// Categorical palette, distinguishable in both light and dark themes.
const PIE_COLORS = ['#2563eb', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#64748b'];

const pieColor = (index: number, option: SummaryOption) => (option.other ? '#94a3b8' : PIE_COLORS[index % PIE_COLORS.length]);

// SVG donut slices on a circle of circumference 100, so dash lengths are percentages.
const pieSlices = (stat: FieldSummary) => {
    let offset = 0;

    return (stat.options ?? []).flatMap((option, index) => {
        if (option.count === 0 || stat.answered === 0) {
            return [];
        }

        const size = (option.count / stat.answered) * 100;
        const slice = { color: pieColor(index, option), size, offset };
        offset += size;

        return [slice];
    });
};
</script>

<template>
    <Head :title="`${$t('Responses')} — ${form.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ form.title }}</h1>
                    <p class="text-muted-foreground text-sm">
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

            <div class="bg-muted/40 flex w-fit gap-1 rounded-lg border p-1" role="tablist">
                <button
                    v-for="tab in [
                        { view: 'summary' as const, label: 'Summary', icon: BarChart3 },
                        { view: 'list' as const, label: 'Individual responses', icon: List },
                    ]"
                    :key="tab.view"
                    type="button"
                    role="tab"
                    :aria-selected="view === tab.view"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="view === tab.view ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="switchView(tab.view)"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ $t(tab.label) }}
                </button>
            </div>

            <div v-if="responses.meta.total === 0" class="text-muted-foreground rounded-xl border border-dashed p-16 text-center">
                {{ $t('No responses yet.') }}
            </div>

            <!-- Summary -->
            <template v-else-if="view === 'summary' && summary">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border p-4">
                        <p class="text-muted-foreground text-sm">{{ $t('Total responses') }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ summary.total }}</p>
                    </div>
                    <div class="rounded-xl border p-4">
                        <p class="text-muted-foreground text-sm">{{ $t('Last 7 days') }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ summary.last_7_days }}</p>
                    </div>
                    <div class="rounded-xl border p-4">
                        <p class="text-muted-foreground text-sm">{{ $t('Latest response') }}</p>
                        <p class="mt-1 text-lg font-semibold">{{ summary.last_submitted_at ? formatDateTime(summary.last_submitted_at) : '—' }}</p>
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <p class="mb-3 text-sm font-medium">{{ $t('Responses over the last 30 days') }}</p>
                    <div class="flex h-28 items-end gap-0.5" role="list">
                        <div
                            v-for="day in summary.timeline"
                            :key="day.date"
                            role="listitem"
                            class="group relative flex h-full flex-1 items-end"
                            :title="`${formatDay(day.date)} : ${day.count}`"
                            :aria-label="`${formatDay(day.date)} : ${day.count}`"
                        >
                            <div
                                class="bg-primary/80 group-hover:bg-primary w-full rounded-t-sm"
                                :style="{ height: day.count ? `${Math.max(4, (day.count / timelineMax) * 100)}%` : '1px' }"
                                :class="day.count ? '' : 'bg-border'"
                            />
                        </div>
                    </div>
                    <div v-if="summary.timeline.length" class="text-muted-foreground mt-1 flex justify-between text-xs">
                        <span>{{ formatDay(summary.timeline[0].date) }}</span>
                        <span>{{ formatDay(summary.timeline[summary.timeline.length - 1].date) }}</span>
                    </div>
                </div>

                <div v-for="stat in summary.fields" :key="stat.field_id" class="rounded-xl border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p v-if="fieldById.get(stat.field_id)?.section" class="text-muted-foreground text-xs tracking-wide uppercase">
                                {{ fieldById.get(stat.field_id)?.section }}
                            </p>
                            <h2 class="font-medium">{{ fieldById.get(stat.field_id)?.label }}</h2>
                        </div>
                        <div v-if="canPie(stat) && stat.answered > 0" class="bg-muted/40 flex shrink-0 gap-0.5 rounded-md border p-0.5">
                            <button
                                v-for="mode in [
                                    { value: 'bar' as const, label: 'Bars', icon: BarChart3 },
                                    { value: 'pie' as const, label: 'Pie chart', icon: ChartPie },
                                ]"
                                :key="mode.value"
                                type="button"
                                class="rounded p-1.5 transition-colors"
                                :class="
                                    (isPie(stat) ? 'pie' : 'bar') === mode.value
                                        ? 'bg-background shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-label="$t(mode.label)"
                                :title="$t(mode.label)"
                                :aria-pressed="(isPie(stat) ? 'pie' : 'bar') === mode.value"
                                @click="chartMode[stat.field_id] = mode.value"
                            >
                                <component :is="mode.icon" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <p class="text-muted-foreground mb-4 text-sm">
                        {{
                            $t(':count of :total responded (:percent%)', {
                                count: String(stat.answered),
                                total: String(summary.total),
                                percent: String(percent(stat.answered, summary.total)),
                            })
                        }}
                    </p>

                    <p v-if="stat.answered === 0" class="text-muted-foreground text-sm">{{ $t('No answers to this question yet.') }}</p>

                    <template v-else>
                        <div v-if="stat.kind === 'rating'" class="mb-4 flex items-baseline gap-2">
                            <Star class="h-5 w-5 self-center fill-amber-400 text-amber-400" />
                            <span class="text-2xl font-semibold">{{ formatNumber(stat.average) }}</span>
                            <span class="text-muted-foreground text-sm"
                                >/ {{ stat.options?.[stat.options.length - 1]?.label }} {{ $t('on average') }}</span
                            >
                        </div>

                        <div v-if="isPie(stat)" class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                            <svg
                                viewBox="0 0 42 42"
                                class="h-44 w-44 shrink-0 -rotate-90"
                                role="img"
                                :aria-label="fieldById.get(stat.field_id)?.label"
                            >
                                <circle cx="21" cy="21" r="15.915" fill="none" class="stroke-muted" stroke-width="7" />
                                <circle
                                    v-for="(slice, index) in pieSlices(stat)"
                                    :key="index"
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="none"
                                    :stroke="slice.color"
                                    stroke-width="7"
                                    :stroke-dasharray="`${slice.size} ${100 - slice.size}`"
                                    :stroke-dashoffset="-slice.offset"
                                />
                            </svg>
                            <ul class="grid w-full max-w-md gap-2 self-center">
                                <li v-for="(option, index) in stat.options" :key="option.label ?? '__other'" class="flex items-center gap-3 text-sm">
                                    <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: pieColor(index, option) }" />
                                    <span class="flex-1 truncate" :class="option.other ? 'text-muted-foreground italic' : ''">
                                        {{ option.other ? $t('Other') : option.label }}
                                    </span>
                                    <span class="text-muted-foreground shrink-0 tabular-nums">
                                        {{ option.count }} · {{ percent(option.count, stat.answered) }}%
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div v-else-if="stat.kind === 'choices' || stat.kind === 'rating'" class="grid gap-2">
                            <div v-for="option in stat.options" :key="option.label ?? '__other'" class="grid gap-1">
                                <div class="flex justify-between gap-3 text-sm">
                                    <span class="truncate" :class="option.other ? 'text-muted-foreground italic' : ''">
                                        {{ option.other ? $t('Other') : option.label }}
                                    </span>
                                    <span class="text-muted-foreground shrink-0 tabular-nums">
                                        {{ option.count }} · {{ percent(option.count, stat.answered) }}%
                                    </span>
                                </div>
                                <div class="bg-muted h-2 overflow-hidden rounded-full">
                                    <div
                                        class="bg-primary h-full rounded-full"
                                        :style="{ width: `${(option.count / optionsMax(stat.options)) * 100}%` }"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="stat.kind === 'choices' && fieldById.get(stat.field_id)?.type === 'checkboxes'"
                                class="text-muted-foreground text-xs"
                            >
                                {{ $t('Several answers possible: percentages may add up to more than 100%.') }}
                            </p>
                        </div>

                        <div v-if="stat.kind === 'number'" class="grid grid-cols-3 gap-3">
                            <div
                                v-for="item in [
                                    { label: 'Average', value: stat.average },
                                    { label: 'Minimum', value: stat.min },
                                    { label: 'Maximum', value: stat.max },
                                ]"
                                :key="item.label"
                                class="bg-muted/50 rounded-lg p-3"
                            >
                                <p class="text-muted-foreground text-xs">{{ $t(item.label) }}</p>
                                <p class="text-lg font-semibold tabular-nums">{{ formatNumber(item.value) }}</p>
                            </div>
                        </div>

                        <div v-if="stat.kind === 'range'" class="grid grid-cols-2 gap-3">
                            <div class="bg-muted/50 rounded-lg p-3">
                                <p class="text-muted-foreground text-xs">{{ $t('Earliest') }}</p>
                                <p class="text-lg font-semibold">{{ formatRangeValue(fieldById.get(stat.field_id)?.type, stat.min) }}</p>
                            </div>
                            <div class="bg-muted/50 rounded-lg p-3">
                                <p class="text-muted-foreground text-xs">{{ $t('Latest') }}</p>
                                <p class="text-lg font-semibold">{{ formatRangeValue(fieldById.get(stat.field_id)?.type, stat.max) }}</p>
                            </div>
                        </div>

                        <p v-if="stat.kind === 'file'" class="text-sm">{{ $t(':count file(s) received', { count: String(stat.answered) }) }}</p>

                        <div v-if="stat.samples?.length" class="mt-4 grid gap-2">
                            <p class="text-muted-foreground text-xs font-medium">
                                {{ stat.kind === 'choices' ? $t('“Other” answers') : $t('Latest answers') }}
                            </p>
                            <p
                                v-for="(sample, index) in stat.samples"
                                :key="index"
                                class="bg-muted/50 rounded-lg px-3 py-2 text-sm whitespace-pre-line"
                            >
                                {{ sample }}
                            </p>
                            <button
                                v-if="stat.kind === 'text' && stat.answered > stat.samples.length"
                                type="button"
                                class="text-primary w-fit text-sm hover:underline"
                                @click="switchView('list')"
                            >
                                {{ $t('See all :count answers', { count: String(stat.answered) }) }}
                            </button>
                        </div>
                    </template>
                </div>
            </template>

            <div v-else-if="view === 'list'" class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/50 border-b text-left">
                            <th class="px-4 py-3 font-medium whitespace-nowrap">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">{{ $t('Email') }}</th>
                            <th v-for="field in columnFields" :key="field.id" class="max-w-48 truncate px-4 py-3 font-medium">
                                {{ field.label }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="response in responses.data" :key="response.id" class="hover:bg-muted/30 border-b last:border-0">
                            <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(response.submitted_at) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span v-if="response.email" class="inline-flex items-center gap-1">
                                    {{ response.email }}
                                    <MailCheck v-if="response.email_verified" class="h-3.5 w-3.5 text-green-600" />
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td v-for="field in columnFields" :key="field.id" class="max-w-48 truncate px-4 py-3">
                                {{ displayValue(answerFor(response, field.id)) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Button variant="ghost" size="icon" class="h-8 w-8" :aria-label="$t('View')" @click="selected = response">
                                    <Eye class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground h-8 w-8 hover:text-red-600"
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

            <div v-if="view === 'list' && responses.meta.last_page > 1" class="flex items-center justify-center gap-2">
                <Button variant="outline" size="sm" :disabled="responses.meta.current_page <= 1" @click="goToPage(responses.meta.current_page - 1)">
                    {{ $t('Previous') }}
                </Button>
                <span class="text-muted-foreground text-sm">{{ responses.meta.current_page }} / {{ responses.meta.last_page }}</span>
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
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    :href="previewUrl(answerFor(selected, field.id)!)"
                                    target="_blank"
                                    rel="noopener"
                                    class="hover:bg-muted inline-flex w-fit items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    {{ $t('Preview') }}
                                </a>
                                <a
                                    :href="answerFor(selected, field.id)!.file_url!"
                                    class="hover:bg-muted inline-flex w-fit items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                                >
                                    <Download class="h-4 w-4" />
                                    {{ $t('Download') }}
                                </a>
                            </div>
                            <p class="text-muted-foreground text-xs">
                                {{ answerFor(selected, field.id)!.file_name }} · {{ formatSize(answerFor(selected, field.id)!.file_size) }}
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground text-sm whitespace-pre-line">{{ displayValue(answerFor(selected, field.id)) }}</p>
                    </div>

                    <p class="text-muted-foreground border-t pt-3 text-xs">
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
