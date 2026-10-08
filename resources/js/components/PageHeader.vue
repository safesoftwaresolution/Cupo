<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 space-y-1">
            <h1 class="text-2xl font-bold md:text-3xl">{{ title }}</h1>
            <p v-if="description" class="max-w-prose text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex shrink-0 items-center gap-2">
            <slot />
        </div>
    </div>
</template>
