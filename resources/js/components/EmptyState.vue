<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="bg-poste flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-14 text-center">
        <div class="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
            <component :is="icon" class="size-6" aria-hidden="true" />
        </div>
        <div class="space-y-1">
            <p class="font-display text-lg font-semibold">{{ title }}</p>
            <p class="mx-auto max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
