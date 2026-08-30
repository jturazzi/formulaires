<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: trans('Appearance settings'),
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('Appearance settings')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('Appearance settings')" :description="$t(`Update your account's appearance settings`)" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
