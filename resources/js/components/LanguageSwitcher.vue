<script setup lang="ts">
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { loadLanguageAsync } from 'laravel-vue-i18n';

const page = usePage<SharedData>();

const switchTo = (locale: string) => {
    if (locale === page.props.locale) {
        return;
    }

    router.post(
        route('locale.update'),
        { locale },
        {
            preserveScroll: true,
            onSuccess: () => loadLanguageAsync(locale),
        },
    );
};
</script>

<template>
    <div class="flex items-center gap-1 text-sm" role="group" aria-label="Language">
        <button
            v-for="locale in ['fr', 'en']"
            :key="locale"
            type="button"
            class="rounded px-2 py-1 uppercase transition-colors"
            :class="locale === page.props.locale ? 'font-semibold text-foreground' : 'text-muted-foreground hover:text-foreground'"
            @click="switchTo(locale)"
        >
            {{ locale }}
        </button>
    </div>
</template>
