<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        tone?: 'sobre-claro' | 'sobre-oscuro';
        size?: 'md' | 'lg';
    }>(),
    { tone: 'sobre-claro', size: 'md' },
);
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <AppLogoIcon :tone="tone" :class="size === 'lg' ? 'size-11' : 'size-9'" />
        <span
            class="font-display font-extrabold leading-none tracking-tighter"
            :class="[size === 'lg' ? 'text-4xl' : 'text-2xl', tone === 'sobre-oscuro' ? 'text-white' : 'text-foreground']"
            >cupo</span
        >
    </span>
</template>
