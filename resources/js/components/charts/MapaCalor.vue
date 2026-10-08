<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    horas: number[];
    dias: string[];
    celdas: number[][];
    maximo: number;
    nombre: string;
}>();

const DIAS_LARGOS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

const hora12 = (h: number) => `${h % 12 || 12} ${h < 12 ? 'a. m.' : 'p. m.'}`;
const hora12Corta = (h: number) => `${h % 12 || 12}${h < 12 ? 'a' : 'p'}`;

// Una sola escala de un tono: más citas = más intenso. Se mezcla con el fondo de la tarjeta,
// así funciona igual en modo claro y oscuro y la intensidad siempre crece en un sentido.
const color = (cantidad: number): string => {
    if (cantidad <= 0) return 'hsl(var(--muted))';

    const porcentaje = 16 + 84 * (cantidad / Math.max(1, props.maximo));

    return `color-mix(in srgb, hsl(var(--viz-acento)) ${porcentaje.toFixed(0)}%, hsl(var(--card)))`;
};

const escala = [0.15, 0.4, 0.65, 0.85, 1].map((f) => Math.max(1, Math.round(props.maximo * f)));

// Con muchas columnas se rotula una hora sí y otra no para que no se encimen.
const rotular = (indice: number) => props.horas.length <= 12 || indice % 2 === 0;

const momentoFuerte = computed(() => {
    let mejor = { d: -1, i: -1, n: 0 };
    props.celdas.forEach((fila, d) => fila.forEach((n, i) => n > mejor.n && (mejor = { d, i, n })));

    return mejor.n > 0 ? mejor : null;
});

// --- Burbuja al pasar el cursor ---------------------------------------------------------------------
const caja = ref<HTMLElement | null>(null);
const activa = ref<{ d: number; i: number; x: number; y: number } | null>(null);

const mostrar = (e: Event, d: number, i: number) => {
    const celda = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const marco = caja.value!.getBoundingClientRect();

    activa.value = { d, i, x: celda.left - marco.left + celda.width / 2, y: celda.top - marco.top };
};

const texto = (d: number, i: number) => {
    const n = props.celdas[d][i];

    return `${DIAS_LARGOS[d]} ${hora12(props.horas[i])}: ${n} ${n === 1 ? 'cita' : 'citas'}`;
};
</script>

<template>
    <div class="space-y-3">
        <p v-if="momentoFuerte" class="text-sm text-muted-foreground">
            El momento con más citas:
            <strong class="text-foreground">{{ DIAS_LARGOS[momentoFuerte.d] }} a las {{ hora12(horas[momentoFuerte.i]) }}</strong>
            ({{ momentoFuerte.n }} {{ momentoFuerte.n === 1 ? 'cita' : 'citas' }} en el periodo).
        </p>

        <div ref="caja" class="relative" @pointerleave="activa = null">
            <div
                class="grid gap-0.5"
                :style="{ gridTemplateColumns: `2.5rem repeat(${horas.length}, minmax(0, 1fr))` }"
                role="img"
                :aria-label="`${nombre}. Hay una tabla con los mismos datos más abajo.`"
            >
                <div aria-hidden="true" />
                <div v-for="(h, i) in horas" :key="`h-${h}`" class="pb-1 text-center text-[11px] text-muted-foreground" aria-hidden="true">
                    {{ rotular(i) ? hora12Corta(h) : '' }}
                </div>

                <template v-for="(dia, d) in dias" :key="dia">
                    <div class="flex items-center pr-1 text-xs text-muted-foreground" aria-hidden="true">{{ dia }}</div>
                    <div
                        v-for="(h, i) in horas"
                        :key="`${dia}-${h}`"
                        class="h-7 rounded-[3px] transition-opacity duration-100 hover:opacity-80"
                        :style="{ background: color(celdas[d][i]) }"
                        @pointerenter="mostrar($event, d, i)"
                        @pointerdown="mostrar($event, d, i)"
                    />
                </template>
            </div>

            <div
                v-if="activa"
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg border bg-popover px-3 py-1.5 text-xs shadow-lg"
                :style="{ left: `${activa.x}px`, top: `${activa.y - 6}px` }"
            >
                <span class="capitalize text-muted-foreground">{{ DIAS_LARGOS[activa.d] }} {{ hora12(horas[activa.i]) }}</span>
                <strong class="ml-2 text-sm text-foreground"
                    >{{ celdas[activa.d][activa.i] }} {{ celdas[activa.d][activa.i] === 1 ? 'cita' : 'citas' }}</strong
                >
            </div>
        </div>

        <div class="flex items-center gap-2 text-xs text-muted-foreground" aria-label="Escala de color">
            <span>Menos</span>
            <span v-for="n in escala" :key="n" class="h-3 w-6 rounded-[2px]" :style="{ background: color(n) }" aria-hidden="true" />
            <span>Más (hasta {{ maximo }})</span>
        </div>

        <details class="text-sm">
            <summary
                class="w-fit cursor-pointer rounded text-muted-foreground underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                Ver como tabla
            </summary>
            <div class="mt-2 overflow-x-auto rounded-lg border">
                <table class="w-full text-center tabular-nums">
                    <caption class="sr-only">
                        {{
                            nombre
                        }}
                    </caption>
                    <thead class="bg-muted text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-2 py-1.5 text-left font-semibold">Día</th>
                            <th v-for="h in horas" :key="h" scope="col" class="px-2 py-1.5 font-semibold">{{ hora12Corta(h) }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(dia, d) in dias" :key="dia">
                            <th scope="row" class="px-2 py-1.5 text-left font-normal">{{ dia }}</th>
                            <td v-for="(h, i) in horas" :key="h" class="px-2 py-1.5" :class="{ 'text-muted-foreground': !celdas[d][i] }">
                                {{ celdas[d][i] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>
