<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Head, Link } from '@inertiajs/vue3';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    content: string | null;
}>();

const html = computed(() => (props.content ? marked.parse(props.content, { async: false }) : ''));
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-muted/40 pb-16">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 px-4 pt-10">
            <div class="flex items-center justify-between">
                <Link :href="route('home')" class="text-sm text-muted-foreground hover:text-foreground">← {{ $t('Back') }}</Link>
                <LanguageSwitcher />
            </div>

            <div class="rounded-xl border bg-background p-8 shadow-sm">
                <article v-if="html" class="prose prose-neutral max-w-none dark:prose-invert" v-html="html"></article>
                <p v-else class="text-muted-foreground">{{ $t('This page has not been configured yet.') }}</p>
            </div>
        </div>
    </div>
</template>
