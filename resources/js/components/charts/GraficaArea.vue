<script setup lang="ts">
import { fechaCorta, fechaLarga } from '@/lib/format';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Punto {
    fecha: string;
    valor: number;
    anterior: number;
}

const props = defineProps<{
    datos: Punto[];
    formatoValor: (n: number) => string;
    formatoEje: (n: number) => string;
    serie: string;
    serieAnterior: string;
    nombre: string;
}>();

const ALTO = 264;
const margen = { izq: 58, der: 24, arr: 20, aba: 30 };

// --- Tamaño: el SVG ocupa todo el ancho de su tarjeta ---------------------------------------
const contenedor = ref<HTMLElement | null>(null);
const ancho = ref(640);
let observador: ResizeObserver | undefined;

onMounted(() => {
    if (!contenedor.value) return;

    ancho.value = Math.max(280, contenedor.value.clientWidth);
    observador = new ResizeObserver(([entrada]) => (ancho.value = Math.max(280, Math.floor(entrada.contentRect.width))));
    observador.observe(contenedor.value);
});
onBeforeUnmount(() => observador?.disconnect());

// --- Escalas ---------------------------------------------------------------------------------------
const w = computed(() => ancho.value - margen.izq - margen.der);
const h = ALTO - margen.arr - margen.aba;
const n = computed(() => props.datos.length);

/** Paso "redondo" (1, 2, 2,5, 5 × 10^k) para que los números del eje sean limpios. */
const pasoLimpio = (x: number): number => {
    const exponente = 10 ** Math.floor(Math.log10(x));
    const f = x / exponente;

    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * exponente;
};

const paso = computed(() => pasoLimpio(Math.max(1, ...props.datos.flatMap((d) => [d.valor, d.anterior])) / 4));
const maximo = computed(() => paso.value * 4);
const marcasY = computed(() => [0, 1, 2, 3, 4].map((i) => i * paso.value));

const x = (i: number) => margen.izq + (n.value <= 1 ? w.value / 2 : (i / (n.value - 1)) * w.value);
const y = (v: number) => margen.arr + h - (v / maximo.value) * h;

const linea = (clave: 'valor' | 'anterior') =>
    props.datos.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)} ${y(d[clave]).toFixed(1)}`).join(' ');
const lineaActual = computed(() => linea('valor'));
const lineaAnterior = computed(() => linea('anterior'));
const area = computed(() => (n.value ? `${lineaActual.value} L${x(n.value - 1).toFixed(1)} ${y(0)} L${x(0).toFixed(1)} ${y(0)} Z` : ''));

// Unas 6 fechas en el eje, siempre con la primera y la última.
const marcasX = computed(() => {
    if (!n.value) return [];
    const cada = Math.max(1, Math.ceil(n.value / 6));
    const indices = Array.from({ length: n.value }, (_, i) => i).filter((i) => i % cada === 0);
    if (n.value - 1 - indices[indices.length - 1] < cada / 2) indices.pop();

    return [...indices, n.value - 1];
});

// El día más fuerte se rotula; los demás valores los dan el eje y el tooltip (no hay un número en cada punto).
const indicePico = computed(() => {
    const max = Math.max(...props.datos.map((d) => d.valor));

    return max > 0 ? props.datos.findIndex((d) => d.valor === max) : -1;
});

// --- Interacción: mouse, toque y teclado ------------------------------------------------------------
const activo = ref<number | null>(null);
const svg = ref<SVGSVGElement | null>(null);

const indiceDesde = (clientX: number): number => {
    const caja = svg.value!.getBoundingClientRect();
    const relativo = ((clientX - caja.left) / caja.width) * ancho.value - margen.izq;

    return Math.min(n.value - 1, Math.max(0, Math.round((relativo / w.value) * (n.value - 1))));
};

const alMover = (e: PointerEvent) => (activo.value = indiceDesde(e.clientX));

const alTeclear = (e: KeyboardEvent) => {
    const actual = activo.value ?? n.value - 1;
    const nuevo = { ArrowLeft: actual - 1, ArrowRight: actual + 1, Home: 0, End: n.value - 1 }[e.key];

    if (nuevo === undefined) return;
    e.preventDefault();
    activo.value = Math.min(n.value - 1, Math.max(0, nuevo));
};

const puntoActivo = computed(() => (activo.value === null ? null : props.datos[activo.value]));

// La burbuja se queda dentro de la gráfica aunque el cursor esté en un borde.
const estiloBurbuja = computed(() => {
    if (activo.value === null) return {};
    const ladoDerecho = x(activo.value) > ancho.value / 2;

    return { left: `${x(activo.value)}px`, transform: `translateX(${ladoDerecho ? 'calc(-100% - 14px)' : '14px'})` };
});

const resumen = computed(() => {
    const total = props.datos.reduce((s, d) => s + d.valor, 0);

    return `${props.nombre}: ${n.value} días, total ${props.formatoValor(total)}. Usa las flechas para recorrer los días.`;
});
</script>

<template>
    <div class="space-y-3">
        <ul class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-muted-foreground" aria-label="Leyenda">
            <li class="flex items-center gap-2">
                <span class="h-0.5 w-5 rounded-full" style="background: hsl(var(--viz-acento))" aria-hidden="true" />{{ serie }}
            </li>
            <li class="flex items-center gap-2">
                <span class="h-0.5 w-5 rounded-full" style="background: hsl(var(--viz-contexto))" aria-hidden="true" />{{ serieAnterior }}
            </li>
        </ul>

        <div
            ref="contenedor"
            class="relative outline-none focus-visible:ring-2 focus-visible:ring-ring"
            tabindex="0"
            role="group"
            :aria-label="resumen"
            @keydown="alTeclear"
            @focus="activo = n - 1"
            @blur="activo = null"
        >
            <svg
                ref="svg"
                :width="ancho"
                :height="ALTO"
                :viewBox="`0 0 ${ancho} ${ALTO}`"
                class="block touch-pan-y select-none"
                aria-hidden="true"
                @pointermove="alMover"
                @pointerdown="alMover"
                @pointerleave="activo = null"
            >
                <!-- Rejilla: hairlines sólidas y recesivas -->
                <g>
                    <line
                        v-for="v in marcasY"
                        :key="v"
                        :x1="margen.izq"
                        :x2="ancho - margen.der"
                        :y1="y(v)"
                        :y2="y(v)"
                        :stroke="v === 0 ? 'hsl(var(--viz-eje))' : 'hsl(var(--viz-rejilla))'"
                        stroke-width="1"
                    />
                    <text
                        v-for="v in marcasY"
                        :key="`ty-${v}`"
                        :x="margen.izq - 10"
                        :y="y(v)"
                        text-anchor="end"
                        dominant-baseline="middle"
                        class="fill-muted-foreground text-[11px] tabular-nums"
                    >
                        {{ formatoEje(v) }}
                    </text>
                    <text
                        v-for="i in marcasX"
                        :key="`tx-${i}`"
                        :x="x(i)"
                        :y="ALTO - 8"
                        text-anchor="middle"
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ fechaCorta(datos[i].fecha) }}
                    </text>
                </g>

                <!-- Periodo anterior: contexto, en gris -->
                <path
                    :d="lineaAnterior"
                    fill="none"
                    stroke="hsl(var(--viz-contexto))"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />

                <!-- Este periodo: protagonista -->
                <path :d="area" fill="hsl(var(--viz-acento))" fill-opacity="0.1" />
                <path :d="lineaActual" fill="none" stroke="hsl(var(--viz-acento))" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                <!-- Cierre de la línea y el día más fuerte -->
                <circle
                    v-if="n"
                    :cx="x(n - 1)"
                    :cy="y(datos[n - 1].valor)"
                    r="4"
                    fill="hsl(var(--viz-acento))"
                    stroke="hsl(var(--card))"
                    stroke-width="2"
                />
                <template v-if="indicePico >= 0 && indicePico !== activo">
                    <circle
                        :cx="x(indicePico)"
                        :cy="y(datos[indicePico].valor)"
                        r="4"
                        fill="hsl(var(--viz-acento))"
                        stroke="hsl(var(--card))"
                        stroke-width="2"
                    />
                    <text
                        :x="Math.min(Math.max(x(indicePico), margen.izq + 24), ancho - margen.der - 24)"
                        :y="y(datos[indicePico].valor) - 12"
                        text-anchor="middle"
                        class="fill-foreground text-[11px] font-semibold"
                    >
                        {{ formatoEje(datos[indicePico].valor) }}
                    </text>
                </template>

                <!-- Cursor: línea vertical que se ajusta al día más cercano -->
                <g v-if="puntoActivo && activo !== null">
                    <line :x1="x(activo)" :x2="x(activo)" :y1="margen.arr" :y2="y(0)" stroke="hsl(var(--viz-eje))" stroke-width="1" />
                    <circle
                        :cx="x(activo)"
                        :cy="y(puntoActivo.anterior)"
                        r="4"
                        fill="hsl(var(--viz-contexto))"
                        stroke="hsl(var(--card))"
                        stroke-width="2"
                    />
                    <circle
                        :cx="x(activo)"
                        :cy="y(puntoActivo.valor)"
                        r="4"
                        fill="hsl(var(--viz-acento))"
                        stroke="hsl(var(--card))"
                        stroke-width="2"
                    />
                </g>
            </svg>

            <div
                v-if="puntoActivo"
                class="pointer-events-none absolute top-2 z-10 min-w-40 rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg"
                :style="estiloBurbuja"
            >
                <p class="mb-1.5 font-medium text-muted-foreground">{{ fechaLarga(puntoActivo.fecha) }}</p>
                <p class="flex items-center justify-between gap-4">
                    <span class="flex items-center gap-2 text-muted-foreground"
                        ><span class="h-0.5 w-3 rounded-full" style="background: hsl(var(--viz-acento))" aria-hidden="true" />{{ serie }}</span
                    >
                    <strong class="text-sm tabular-nums text-foreground">{{ formatoValor(puntoActivo.valor) }}</strong>
                </p>
                <p class="mt-1 flex items-center justify-between gap-4">
                    <span class="flex items-center gap-2 text-muted-foreground"
                        ><span class="h-0.5 w-3 rounded-full" style="background: hsl(var(--viz-contexto))" aria-hidden="true" />{{
                            serieAnterior
                        }}</span
                    >
                    <span class="tabular-nums text-foreground">{{ formatoValor(puntoActivo.anterior) }}</span>
                </p>
            </div>

            <p class="sr-only" aria-live="polite">
                <template v-if="puntoActivo">{{ fechaLarga(puntoActivo.fecha) }}: {{ formatoValor(puntoActivo.valor) }}</template>
            </p>
        </div>

        <details class="text-sm">
            <summary
                class="w-fit cursor-pointer rounded text-muted-foreground underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                Ver como tabla
            </summary>
            <div class="mt-2 max-h-64 overflow-auto rounded-lg border">
                <table class="w-full text-left">
                    <caption class="sr-only">
                        {{
                            nombre
                        }}
                    </caption>
                    <thead class="sticky top-0 bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-semibold">Día</th>
                            <th scope="col" class="px-3 py-2 text-right font-semibold">{{ serie }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-semibold">{{ serieAnterior }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y tabular-nums">
                        <tr v-for="d in datos" :key="d.fecha">
                            <th scope="row" class="px-3 py-1.5 font-normal">{{ fechaLarga(d.fecha) }}</th>
                            <td class="px-3 py-1.5 text-right">{{ formatoValor(d.valor) }}</td>
                            <td class="px-3 py-1.5 text-right text-muted-foreground">{{ formatoValor(d.anterior) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>
