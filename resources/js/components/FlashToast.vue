<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from '@lucide/vue';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();

const message = ref<string | null>(null);
const type = ref<'success' | 'error'>('success');
let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => page.props.flash,
    (flash) => {
        const next = flash?.success ?? flash?.error;

        if (!next) {
            return;
        }

        type.value = flash?.success ? 'success' : 'error';
        message.value = next;

        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => (message.value = null), 4000);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="message"
            class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-lg border bg-background px-4 py-3 text-sm shadow-lg"
            role="status"
        >
            <CheckCircle2 v-if="type === 'success'" class="h-5 w-5 text-green-600" />
            <XCircle v-else class="h-5 w-5 text-red-600" />
            <span>{{ message }}</span>
        </div>
    </Transition>
</template>
