<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type FormStatus, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ArrowRight, CheckCircle2, FileText, Inbox, Plus, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

interface RecentForm {
    id: number;
    title: string;
    status: FormStatus;
    responses_count: number;
    updated_at: string;
}

defineProps<{
    stats: {
        forms: number;
        published: number;
        responses: number;
        responses_this_week: number;
    };
    recentForms: RecentForm[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: trans('Dashboard'),
        href: '/dashboard',
    },
];

const statusClass: Record<FormStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    published: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    closed: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

const statusLabel: Record<FormStatus, string> = {
    draft: 'Draft',
    published: 'Published',
    closed: 'Closed',
};

const iconClass: Record<'forms' | 'published' | 'responses' | 'week', string> = {
    forms: 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300',
    published: 'bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-300',
    responses: 'bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-300',
    week: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
};

const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);

const formatDate = (value: string) => new Date(value).toLocaleDateString();
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ $t('Hello, :name', { name: firstName }) }}</h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ $t("Here's an overview of your forms.") }}</p>
                </div>
                <Button as-child>
                    <Link href="/forms?create=1">
                        <Plus class="mr-1 h-4 w-4" />
                        {{ $t('New form') }}
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">{{ $t('Forms') }}</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="iconClass.forms">
                            <FileText class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.forms }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">{{ $t('Published') }}</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="iconClass.published">
                            <CheckCircle2 class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.published }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">{{ $t('Responses') }}</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="iconClass.responses">
                            <Inbox class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.responses }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium">{{ $t('This week') }}</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="iconClass.week">
                            <TrendingUp class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.responses_this_week }}</div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ $t('Recent forms') }}</CardTitle>
                    <Button as-child variant="outline" size="sm">
                        <Link href="/forms">
                            {{ $t('View all') }}
                            <ArrowRight class="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="recentForms.length === 0" class="flex flex-col items-center gap-3 py-10 text-center">
                        <span class="bg-muted flex h-12 w-12 items-center justify-center rounded-full">
                            <FileText class="text-muted-foreground h-6 w-6" />
                        </span>
                        <p class="text-muted-foreground text-sm">{{ $t('No forms yet. Create your first form!') }}</p>
                        <Button as-child size="sm">
                            <Link href="/forms?create=1">
                                <Plus class="mr-1 h-4 w-4" />
                                {{ $t('New form') }}
                            </Link>
                        </Button>
                    </div>
                    <ul v-else class="divide-y">
                        <li v-for="form in recentForms" :key="form.id">
                            <Link
                                :href="route('forms.edit', form.id)"
                                class="hover:bg-muted/50 flex items-center justify-between gap-4 py-3 transition-colors"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ form.title }}</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ form.responses_count }} {{ $t('response(s)') }} ·
                                        {{ $t('Updated :date', { date: formatDate(form.updated_at) }) }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass[form.status]">
                                    {{ $t(statusLabel[form.status]) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
