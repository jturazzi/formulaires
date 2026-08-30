<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage<SharedData>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center">
        <FileText class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-none font-semibold">{{ page.props.name }}</span>
    </div>
</template>
