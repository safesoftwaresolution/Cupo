<script setup lang="ts">
/**
 * Marca de Cupo: una "C" abierta (el hueco) con un punto dentro (el cliente que reservó).
 * `sobre-claro`: cuadro tinta con C dorada. `sobre-oscuro`: cuadro dorado con C tinta,
 * para superficies tinta como la barra lateral.
 */
withDefaults(
    defineProps<{
        tone?: 'sobre-claro' | 'sobre-oscuro';
    }>(),
    { tone: 'sobre-claro' },
);

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" role="img" aria-label="Cupo" v-bind="$attrs">
        <rect width="40" height="40" rx="10" :fill="tone === 'sobre-claro' ? '#1C1917' : '#FCD34D'" />
        <path
            d="M27.28 13.89A9.5 9.5 0 1 0 27.28 26.11"
            fill="none"
            :stroke="tone === 'sobre-claro' ? '#FCD34D' : '#1C1917'"
            stroke-width="5"
            stroke-linecap="round"
        />
        <circle cx="27.5" cy="20" r="2.4" :fill="tone === 'sobre-claro' ? '#FAFAF9' : '#1C1917'" />
    </svg>
</template>
