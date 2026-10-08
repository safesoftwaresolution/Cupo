<script setup lang="ts">
import { computed } from 'vue';

export interface FilaBarra {
    etiqueta: string;
    valor: number;
    /** Dato secundario que se muestra bajo la etiqueta (por ejemplo, cuántas veces). */
    detalle?: string;
}

const props = defineProps<{
    filas: FilaBarra[];
    formatoValor: (n: number) => string;
    nombre: string;
    /** Encabezado de la columna de valores en la vista de tabla. */
    columna: string;
}>();

// Una sola serie: todas las barras llevan el mismo color; el largo ya dice cuánto es cada una.
const maximo = computed(() => Math.max(1, ...props.filas.map((f) => f.valor)));
</script>

<template>
    <div class="space-y-3">
        <ul class="space-y-3" :aria-label="nombre">
            <li
                v-for="fila in filas"
                :key="fila.etiqueta"
                class="group grid grid-cols-[minmax(0,9rem)_1fr] items-center gap-x-3 gap-y-0.5 sm:grid-cols-[minmax(0,11rem)_1fr]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium" :title="fila.etiqueta">{{ fila.etiqueta }}</p>
                    <p v-if="fila.detalle" class="truncate text-xs text-muted-foreground">{{ fila.detalle }}</p>
                </div>
                <div class="flex min-w-0 items-center gap-2">
                    <!-- Barra de 20 px (máximo 24): redondeada en la punta, plana en la base -->
                    <div class="h-5 min-w-0 flex-1">
                        <div
                            class="h-full rounded-r-[4px] transition-opacity duration-150 group-hover:opacity-80"
                            :style="{ width: `${Math.max(2, (fila.valor / maximo) * 100)}%`, background: 'hsl(var(--viz-acento))' }"
                            :title="`${fila.etiqueta}: ${formatoValor(fila.valor)}`"
                        />
                    </div>
                    <span class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums">{{ formatoValor(fila.valor) }}</span>
                </div>
            </li>
        </ul>

        <details class="text-sm">
            <summary
                class="w-fit cursor-pointer rounded text-muted-foreground underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                Ver como tabla
            </summary>
            <table class="mt-2 w-full text-left">
                <caption class="sr-only">
                    {{
                        nombre
                    }}
                </caption>
                <thead class="text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th scope="col" class="py-1.5 font-semibold">Nombre</th>
                        <th scope="col" class="py-1.5 text-right font-semibold">{{ columna }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y tabular-nums">
                    <tr v-for="fila in filas" :key="fila.etiqueta">
                        <th scope="row" class="py-1.5 font-normal">
                            {{ fila.etiqueta }}<span v-if="fila.detalle" class="text-muted-foreground"> · {{ fila.detalle }}</span>
                        </th>
                        <td class="py-1.5 text-right">{{ formatoValor(fila.valor) }}</td>
                    </tr>
                </tbody>
            </table>
        </details>
    </div>
</template>
