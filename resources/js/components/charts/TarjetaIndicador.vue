<script setup lang="ts">
import { formatoNumero } from '@/lib/format';
import { ArrowDown, ArrowUp, Minus } from 'lucide-vue-next';
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';

const props = withDefaults(
    defineProps<{
        etiqueta: string;
        valor: string;
        /** Variación contra el periodo anterior; null si el anterior no tiene datos. */
        cambio: number | null;
        unidad?: 'porcentaje' | 'puntos';
        /** Si subir es bueno (ventas) o malo (inasistencia): decide el color de la variación. */
        subeEsBueno?: boolean;
        serie?: number[];
        nota?: string;
    }>(),
    { unidad: 'porcentaje', subeEsBueno: true },
);

const direccion = computed(() => (props.cambio === null || Math.abs(props.cambio) < 0.05 ? 0 : props.cambio > 0 ? 1 : -1));
const bueno = computed(() => (direccion.value === 0 ? null : direccion.value > 0 === props.subeEsBueno));

const textoCambio = computed(() => {
    if (props.cambio === null) return 'Sin datos del periodo anterior';
    if (direccion.value === 0) return 'Igual que el periodo anterior';

    const signo = props.cambio > 0 ? '+' : '−';
    const cantidad = formatoNumero(Math.abs(props.cambio));

    return `${signo}${cantidad}${props.unidad === 'puntos' ? ' pts' : ' %'} vs. periodo anterior`;
});

// El color nunca va solo: siempre hay flecha y texto.
const clase = computed(() => (bueno.value === null ? 'text-muted-foreground' : bueno.value ? 'text-success' : 'text-destructive'));
</script>

<template>
    <div class="flex items-start justify-between gap-3 rounded-xl border bg-card p-4 shadow-sm">
        <div class="min-w-0 space-y-1">
            <p class="text-sm text-muted-foreground">{{ etiqueta }}</p>
            <p class="font-display text-3xl font-bold leading-tight">{{ valor }}</p>
            <p class="flex items-center gap-1 text-xs font-medium" :class="clase">
                <ArrowUp v-if="direccion > 0" class="size-3.5 shrink-0" aria-hidden="true" />
                <ArrowDown v-else-if="direccion < 0" class="size-3.5 shrink-0" aria-hidden="true" />
                <Minus v-else class="size-3.5 shrink-0" aria-hidden="true" />
                <span>{{ textoCambio }}</span>
            </p>
            <p v-if="nota" class="text-xs text-muted-foreground">{{ nota }}</p>
        </div>
        <Sparkline v-if="serie && serie.length > 1" :valores="serie" />
    </div>
</template>
