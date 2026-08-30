<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FileText, Lock, ShieldCheck, Timer } from '@lucide/vue';

const page = usePage<SharedData>();
</script>

<template>
    <Head :title="$t('Welcome')" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-2 font-semibold">
                <FileText class="h-5 w-5" />
                {{ page.props.name }}
            </div>
            <div class="flex items-center gap-4">
                <LanguageSwitcher />
                <Button v-if="page.props.auth.user" as-child>
                    <Link :href="route('dashboard')">{{ $t('Dashboard') }}</Link>
                </Button>
                <Button v-else as-child>
                    <Link :href="route('login')">{{ $t('Log in') }}</Link>
                </Button>
            </div>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
            <h1 class="max-w-2xl text-4xl font-bold tracking-tight md:text-5xl">
                {{ $t('Create forms, collect responses.') }}
            </h1>
            <p class="mt-4 max-w-xl text-lg text-muted-foreground">
                {{ $t('An open source, self-hosted and GDPR-friendly alternative to collect answers and documents from external users.') }}
            </p>
            <div class="mt-8">
                <Button as-child size="lg">
                    <Link :href="route('login')">{{ $t('Get started') }}</Link>
                </Button>
            </div>

            <div class="mt-16 grid max-w-3xl gap-8 text-left sm:grid-cols-3">
                <div>
                    <Lock class="h-6 w-6 text-muted-foreground" />
                    <h2 class="mt-2 font-semibold">{{ $t('Microsoft 365 SSO') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ $t('Managers sign in with their organization account.') }}</p>
                </div>
                <div>
                    <ShieldCheck class="h-6 w-6 text-muted-foreground" />
                    <h2 class="mt-2 font-semibold">{{ $t('GDPR built in') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ $t('Explicit consent, retention periods and automatic purge.') }}</p>
                </div>
                <div>
                    <Timer class="h-6 w-6 text-muted-foreground" />
                    <h2 class="mt-2 font-semibold">{{ $t('Fast to build') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ $t('Sections, choices, dates, file uploads, logo and colors.') }}</p>
                </div>
            </div>
        </main>

        <footer class="flex items-center justify-center gap-4 px-6 py-6 text-sm text-muted-foreground">
            <Link :href="route('terms')" class="hover:text-foreground">{{ $t('Terms of use') }}</Link>
            <Link :href="route('privacy')" class="hover:text-foreground">{{ $t('Privacy policy') }}</Link>
        </footer>
    </div>
</template>
