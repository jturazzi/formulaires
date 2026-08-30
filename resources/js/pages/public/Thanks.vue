<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    form: {
        title: string;
        logo_url: string | null;
        primary_color: string | null;
        success_message: string | null;
    };
}>();

const accent = computed(() => props.form.primary_color ?? '#2563eb');
</script>

<template>
    <Head :title="form.title" />

    <div class="flex min-h-screen flex-col bg-muted/40">
        <div class="h-2 w-full" :style="{ backgroundColor: accent }"></div>

        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center px-4">
            <div class="w-full rounded-xl border bg-background p-10 text-center shadow-sm">
                <img v-if="form.logo_url" :src="form.logo_url" alt="Logo" class="mx-auto mb-6 max-h-16 object-contain" />
                <CheckCircle2 class="mx-auto h-14 w-14" :style="{ color: accent }" />
                <h1 class="mt-4 text-2xl font-bold">{{ $t('Thank you!') }}</h1>
                <p class="mt-2 whitespace-pre-line text-muted-foreground">
                    {{ form.success_message || $t('Your response has been recorded.') }}
                </p>
            </div>
        </div>
    </div>
</template>
