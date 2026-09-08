<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';

const page = usePage<SharedData>();
</script>

<template>
    <Head :title="$t('Welcome')" />

    <div class="bg-background text-foreground flex min-h-screen flex-col">
        <header class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-2 font-semibold">
                <FileText class="h-5 w-5" />
                {{ page.props.name }}
            </div>
            <LanguageSwitcher />
        </header>

        <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
            <h1 class="max-w-2xl text-4xl font-bold tracking-tight md:text-5xl">
                {{ $t('Create forms, collect responses.') }}
            </h1>
            <p class="text-muted-foreground mt-4 max-w-xl text-lg">
                {{ $t('An open source, self-hosted and GDPR-friendly alternative to collect answers and documents from external users.') }}
            </p>
            <div class="mt-8">
                <Button v-if="page.props.features.sso" as-child size="lg">
                    <a :href="route('auth.microsoft')">
                        <svg class="mr-2 h-4 w-4" viewBox="0 0 23 23" aria-hidden="true">
                            <rect x="1" y="1" width="10" height="10" fill="#f25022" />
                            <rect x="12" y="1" width="10" height="10" fill="#7fba00" />
                            <rect x="1" y="12" width="10" height="10" fill="#00a4ef" />
                            <rect x="12" y="12" width="10" height="10" fill="#ffb900" />
                        </svg>
                        {{ $t('Sign in with Microsoft 365') }}
                    </a>
                </Button>
                <Button v-else as-child size="lg">
                    <Link :href="route('login')">{{ $t('Get started') }}</Link>
                </Button>
            </div>
        </main>

        <footer class="text-muted-foreground flex flex-col items-center justify-center gap-4 px-6 py-6 text-sm sm:flex-row">
            <div class="flex items-center gap-4">
                <Link :href="route('terms')" class="hover:text-foreground">{{ $t('Terms of use') }}</Link>
                <Link :href="route('privacy')" class="hover:text-foreground">{{ $t('Privacy policy') }}</Link>
            </div>
            <a
                href="https://github.com/jturazzi/formulaires"
                target="_blank"
                rel="noopener noreferrer"
                class="hover:text-foreground flex items-center gap-1.5"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path
                        d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.57.1.78-.25.78-.55 0-.27-.01-1.16-.02-2.11-3.2.7-3.88-1.36-3.88-1.36-.52-1.34-1.28-1.69-1.28-1.69-1.04-.72.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.75 2.69 1.25 3.34.96.1-.75.4-1.25.73-1.54-2.55-.29-5.24-1.28-5.24-5.68 0-1.26.45-2.29 1.19-3.09-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.18 1.18a11 11 0 0 1 5.79 0c2.2-1.49 3.17-1.18 3.17-1.18.64 1.59.24 2.76.12 3.05.74.8 1.19 1.83 1.19 3.09 0 4.41-2.69 5.38-5.25 5.67.41.36.78 1.06.78 2.15 0 1.55-.01 2.8-.01 3.18 0 .3.21.66.79.55A10.51 10.51 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z"
                    />
                </svg>
                GitHub
            </a>
        </footer>
    </div>
</template>
