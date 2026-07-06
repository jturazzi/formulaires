<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type FormStatus } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { CheckCircle2, FileText, Inbox, TrendingUp } from 'lucide-vue-next';

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
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('Forms') }}</CardTitle>
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.forms }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('Published') }}</CardTitle>
                        <CheckCircle2 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.published }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('Responses') }}</CardTitle>
                        <Inbox class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.responses }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('This week') }}</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
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
                        <Link href="/forms">{{ $t('View all') }}</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <p v-if="recentForms.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        {{ $t('No forms yet. Create your first form!') }}
                    </p>
                    <ul v-else class="divide-y">
                        <li v-for="form in recentForms" :key="form.id" class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <Link :href="route('forms.edit', form.id)" class="font-medium hover:underline">
                                    {{ form.title }}
                                </Link>
                                <p class="text-xs text-muted-foreground">{{ form.responses_count }} {{ $t('response(s)') }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass[form.status]">
                                {{ $t(statusLabel[form.status]) }}
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
