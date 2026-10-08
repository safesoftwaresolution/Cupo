<script setup lang="ts">
import { computed } from 'vue';

export interface Segmento {
    etiqueta: string;
    valor: number;
    /** El segmento protagonista va en el color de la marca; los demás en gris de contexto. */
    enfasis?: boolean;
}

const props = defineProps<{
    segmentos: Segmento[];
    nombre: string;
}>();

const total = computed(() => props.segmentos.reduce((s, x) => s + x.valor, 0));
const porcentaje = (valor: number) => (total.value ? Math.round((valor / total.value) * 100) : 0);
const visibles = computed(() => props.segmentos.filter((s) => s.valor > 0));
</script>

<template>
    <div class="space-y-3">
        <!-- Segmentos separados por 2 px de fondo (no por bordes) -->
        <div
            class="flex h-4 gap-0.5 overflow-hidden rounded-[4px]"
            role="img"
            :aria-label="`${nombre}: ${segmentos.map((s) => `${s.etiqueta} ${s.valor}`).join(', ')}`"
        >
            <div
                v-for="s in visibles"
                :key="s.etiqueta"
                class="h-full transition-opacity duration-150 hover:opacity-80"
                :style="{ flex: s.valor, background: s.enfasis ? 'hsl(var(--viz-acento))' : 'hsl(var(--viz-contexto))' }"
                :title="`${s.etiqueta}: ${s.valor} (${porcentaje(s.valor)} %)`"
            />
            <div v-if="!visibles.length" class="h-full flex-1 bg-muted" />
        </div>

        <ul class="grid gap-2 sm:grid-cols-2" aria-label="Leyenda">
            <li v-for="s in segmentos" :key="s.etiqueta" class="flex items-center gap-3">
                <span
                    class="h-3 w-3 shrink-0 rounded-[3px]"
                    :style="{ background: s.enfasis ? 'hsl(var(--viz-acento))' : 'hsl(var(--viz-contexto))' }"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 text-sm text-muted-foreground">{{ s.etiqueta }}</span>
                <span class="font-display text-xl font-bold">{{ s.valor }}</span>
                <span class="w-10 text-right text-xs tabular-nums text-muted-foreground">{{ porcentaje(s.valor) }} %</span>
            </li>
        </ul>
    </div>
</template>
