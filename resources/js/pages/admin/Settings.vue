<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps<{
    settings: {
        default_retention_days: number;
        terms_fr: string | null;
        terms_en: string | null;
        privacy_fr: string | null;
        privacy_en: string | null;
    };
    systemInfo: {
        app_version: string | null;
        php_version: string;
        laravel_version: string;
        server: string;
        environment: string;
        database: string;
        cache: string;
        session: string;
        timezone: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: trans('Site settings'), href: '/admin/settings' }];

const form = useForm({
    default_retention_days: props.settings.default_retention_days,
    terms_fr: props.settings.terms_fr ?? '',
    terms_en: props.settings.terms_en ?? '',
    privacy_fr: props.settings.privacy_fr ?? '',
    privacy_en: props.settings.privacy_en ?? '',
});

const activeTab = ref<'terms_fr' | 'terms_en' | 'privacy_fr' | 'privacy_en'>('terms_fr');

const tabs = [
    { key: 'terms_fr', label: 'CGU (FR)' },
    { key: 'terms_en', label: 'Terms (EN)' },
    { key: 'privacy_fr', label: 'Confidentialité (FR)' },
    { key: 'privacy_en', label: 'Privacy (EN)' },
] as const;

const save = () => {
    form.put(route('admin.settings.update'), { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Site settings')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('GDPR retention') }}</CardTitle>
                    <CardDescription>{{ $t('Default retention period applied to forms that do not define their own.') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid max-w-xs gap-2">
                        <Label for="retention-days">{{ $t('Retention period (days)') }}</Label>
                        <Input id="retention-days" v-model.number="form.default_retention_days" type="number" min="1" max="3650" />
                        <p v-if="form.errors.default_retention_days" class="text-sm text-red-600">{{ form.errors.default_retention_days }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('Legal pages') }}</CardTitle>
                    <CardDescription>
                        {{
                            $t(
                                'Markdown content shown on the public terms and privacy pages. Replace the [placeholders] with your organization details.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-1 rounded-lg bg-muted p-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm transition-colors"
                            :class="activeTab === tab.key ? 'bg-background font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <textarea
                        v-model="form[activeTab]"
                        rows="18"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :placeholder="$t('Markdown content…')"
                    ></textarea>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="save">{{ $t('Save') }}</Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('System information') }}</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <h3 class="text-sm font-medium text-muted-foreground">{{ $t('Application') }}</h3>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            <template v-if="systemInfo.app_version">
                                <dt class="text-muted-foreground">{{ $t('Version') }}</dt>
                                <dd class="font-mono">{{ systemInfo.app_version }}</dd>
                            </template>
                            <dt class="text-muted-foreground">{{ $t('PHP version') }}</dt>
                            <dd class="font-mono">{{ systemInfo.php_version }}</dd>
                            <dt class="text-muted-foreground">{{ $t('Laravel version') }}</dt>
                            <dd class="font-mono">{{ systemInfo.laravel_version }}</dd>
                        </dl>
                    </div>
                    <div class="grid gap-2">
                        <h3 class="text-sm font-medium text-muted-foreground">{{ $t('Server') }}</h3>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            <dt class="text-muted-foreground">{{ $t('Server') }}</dt>
                            <dd class="truncate font-mono" :title="systemInfo.server">{{ systemInfo.server }}</dd>
                            <dt class="text-muted-foreground">{{ $t('Environment') }}</dt>
                            <dd class="font-mono">{{ systemInfo.environment }}</dd>
                            <dt class="text-muted-foreground">{{ $t('Database') }}</dt>
                            <dd class="truncate font-mono" :title="systemInfo.database">{{ systemInfo.database }}</dd>
                            <dt class="text-muted-foreground">{{ $t('Cache') }}</dt>
                            <dd class="font-mono">{{ systemInfo.cache }}</dd>
                            <dt class="text-muted-foreground">{{ $t('Session') }}</dt>
                            <dd class="font-mono">{{ systemInfo.session }}</dd>
                            <dt class="text-muted-foreground">{{ $t('Timezone') }}</dt>
                            <dd class="font-mono">{{ systemInfo.timezone }}</dd>
                        </dl>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
