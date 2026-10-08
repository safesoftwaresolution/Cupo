<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    valores: number[];
}>();

const ANCHO = 96;
const ALTO = 32;
const PAD = 5; // deja espacio para el punto final

const maximo = computed(() => Math.max(1, ...props.valores));

const punto = (valor: number, i: number): [number, number] => [
    PAD + (props.valores.length <= 1 ? (ANCHO - PAD * 2) / 2 : (i / (props.valores.length - 1)) * (ANCHO - PAD * 2)),
    ALTO - PAD - (valor / maximo.value) * (ALTO - PAD * 2),
];

const trazo = computed(() =>
    props.valores
        .map(
            (v, i) =>
                `${i === 0 ? 'M' : 'L'}${punto(v, i)
                    .map((n) => n.toFixed(1))
                    .join(' ')}`,
        )
        .join(' '),
);
const final = computed(() => punto(props.valores[props.valores.length - 1] ?? 0, props.valores.length - 1));
</script>

<template>
    <svg :width="ANCHO" :height="ALTO" :viewBox="`0 0 ${ANCHO} ${ALTO}`" class="shrink-0" aria-hidden="true">
        <path :d="trazo" fill="none" stroke="hsl(var(--viz-contexto))" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        <circle v-if="valores.length" :cx="final[0]" :cy="final[1]" r="4" fill="hsl(var(--viz-acento))" stroke="hsl(var(--card))" stroke-width="2" />
    </svg>
</template>
