<script setup lang="ts">
import Heading from '@/components/Heading.vue';
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="$t('Settings')" :description="$t('Manage your appearance settings')" />

        <div class="max-w-xl space-y-12">
            <slot />
        </div>
    </div>
</template>
