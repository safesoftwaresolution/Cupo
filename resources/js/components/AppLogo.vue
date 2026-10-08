<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
</script>

<template>
    <!-- `!size-8`: el botón del menú fuerza svg de 16px y la marca debe verse más grande. -->
    <AppLogoIcon tone="sobre-oscuro" class="!size-8 shrink-0" />
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span class="font-display text-lg font-extrabold tracking-tighter text-white">cupo</span>
        <span v-if="page.props.barberia" class="truncate text-xs text-sidebar-foreground/70">{{ page.props.barberia.nombre }}</span>
    </div>
</template>
