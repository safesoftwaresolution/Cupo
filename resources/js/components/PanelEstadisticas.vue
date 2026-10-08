<script setup lang="ts">
import BarraDividida from '@/components/charts/BarraDividida.vue';
import BarrasHorizontales from '@/components/charts/BarrasHorizontales.vue';
import GraficaArea from '@/components/charts/GraficaArea.vue';
import MapaCalor from '@/components/charts/MapaCalor.vue';
import TarjetaGrafica from '@/components/charts/TarjetaGrafica.vue';
import TarjetaIndicador from '@/components/charts/TarjetaIndicador.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { fechaCorta, formatoCOP, formatoCOPCompacto, formatoNumero } from '@/lib/format';
import { type Analitica } from '@/types';
import { router } from '@inertiajs/vue3';
import { BarChart3, MessageCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    analitica: Analitica;
    rango: string;
    barberia: string;
    urlReserva: string;
    manejaComisiones: boolean;
}>();

const periodos = [
    { valor: '7', etiqueta: '7 días' },
    { valor: '30', etiqueta: '30 días' },
    { valor: '90', etiqueta: '90 días' },
    { valor: 'mes', etiqueta: 'Este mes' },
];

// Al cambiar de periodo solo se vuelven a pedir las estadísticas, y mientras llegan se conserva
// lo que ya se veía (más tenue) en lugar de vaciar la pantalla.
const cargando = ref(false);

const cambiarPeriodo = (valor: string) => {
    if (valor === props.rango || cargando.value) return;

    router.get(
        route('dashboard'),
        { rango: valor },
        {
            only: ['analitica', 'rango'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (cargando.value = true),
            onFinish: () => (cargando.value = false),
        },
    );
};

const k = computed(() => props.analitica.kpis);
const sinDatos = computed(() => k.value.atenciones.valor === 0 && props.analitica.demanda.total === 0);

const subtitulo = computed(() => {
    const { desde, hasta, dias } = props.analitica.periodo;

    return `${fechaCorta(desde)} al ${fechaCorta(hasta)} · ${dias} ${dias === 1 ? 'día' : 'días'}, comparado con los ${dias} anteriores`;
});

const porcentaje = (valor: number | null) => (valor === null ? 'Sin datos' : `${formatoNumero(valor)} %`);

const serviciosFilas = computed(() =>
    props.analitica.servicios.map((s) => ({ etiqueta: s.nombre, valor: s.ingresos, detalle: `${s.cantidad} ${s.cantidad === 1 ? 'vez' : 'veces'}` })),
);

const barberosFilas = computed(() =>
    props.analitica.barberos.map((b) => ({
        etiqueta: b.nombre,
        valor: b.ventas,
        detalle: `${b.atenciones} ${b.atenciones === 1 ? 'atención' : 'atenciones'}${props.manejaComisiones ? ` · comisión ${formatoCOP(b.comision)}` : ''}`,
    })),
);

const metodosFilas = computed(() =>
    props.analitica.metodos.map((m) => ({ etiqueta: m.etiqueta, valor: m.total, detalle: `${m.cantidad} ${m.cantidad === 1 ? 'pago' : 'pagos'}` })),
);

const origenFilas = computed(() => props.analitica.origen.map((o) => ({ etiqueta: o.etiqueta, valor: o.cantidad })));

const segmentosClientes = computed(() => [
    { etiqueta: 'Nuevos', valor: props.analitica.clientes.nuevos, enfasis: true },
    { etiqueta: 'Recurrentes', valor: props.analitica.clientes.recurrentes },
]);

const enlaceWhatsapp = (cliente: Analitica['en_riesgo'][number]) => {
    const nombre = cliente.nombre.split(' ')[0];
    const mensaje = `Hola ${nombre}, hace rato no te vemos por ${props.barberia}. ¿Te agendamos una cita? Reserva aquí: ${props.urlReserva}`;

    return `https://wa.me/${cliente.telefono}?text=${encodeURIComponent(mensaje)}`;
};
</script>

<template>
    <section class="space-y-6" aria-labelledby="titulo-estadisticas">
        <!-- Un solo filtro, arriba de todo lo que afecta -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h2 id="titulo-estadisticas" class="text-2xl font-bold">Tu negocio</h2>
                <p class="text-sm text-muted-foreground">{{ subtitulo }}</p>
            </div>
            <div class="inline-flex rounded-lg border bg-card p-1" role="group" aria-label="Periodo">
                <Button
                    v-for="p in periodos"
                    :key="p.valor"
                    type="button"
                    size="sm"
                    :variant="rango === p.valor ? 'default' : 'ghost'"
                    :aria-pressed="rango === p.valor"
                    :disabled="cargando && rango !== p.valor"
                    @click="cambiarPeriodo(p.valor)"
                >
                    {{ p.etiqueta }}
                </Button>
            </div>
        </div>

        <div class="space-y-6 transition-opacity duration-200" :class="{ 'opacity-60': cargando }" :aria-busy="cargando">
            <EmptyState
                v-if="sinDatos"
                :icon="BarChart3"
                title="Aún no hay datos en este periodo"
                description="Cuando empieces a cobrar citas, aquí verás tus ventas, tus horas más fuertes y qué clientes dejaron de venir."
            />

            <template v-else>
                <!-- Indicadores -->
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <TarjetaIndicador
                        etiqueta="Ventas"
                        :valor="formatoCOP(k.ventas.valor)"
                        :cambio="k.ventas.cambio"
                        :serie="k.ventas.serie"
                        nota="Servicios cobrados, sin propinas"
                    />
                    <TarjetaIndicador
                        etiqueta="Atenciones"
                        :valor="String(k.atenciones.valor)"
                        :cambio="k.atenciones.cambio"
                        :serie="k.atenciones.serie"
                    />
                    <TarjetaIndicador
                        etiqueta="Ticket promedio"
                        :valor="formatoCOP(k.ticket.valor)"
                        :cambio="k.ticket.cambio"
                        nota="Lo que deja cada atención"
                    />
                    <TarjetaIndicador
                        etiqueta="Ocupación"
                        :valor="porcentaje(k.ocupacion.valor)"
                        :cambio="k.ocupacion.cambio"
                        unidad="puntos"
                        nota="Horas agendadas de las que tienen tus barberos"
                    />
                    <TarjetaIndicador
                        etiqueta="Inasistencia"
                        :valor="porcentaje(k.inasistencia.valor)"
                        :cambio="k.inasistencia.cambio"
                        unidad="puntos"
                        :sube-es-bueno="false"
                        :nota="
                            k.inasistencia.casos
                                ? `${k.inasistencia.casos} ${k.inasistencia.casos === 1 ? 'cliente no llegó' : 'clientes no llegaron'}`
                                : 'Nadie faltó'
                        "
                    />
                    <TarjetaIndicador
                        v-if="k.barberia"
                        etiqueta="Para la barbería"
                        :valor="formatoCOP(k.barberia.valor)"
                        :cambio="k.barberia.cambio"
                        nota="Ventas menos comisiones"
                    />
                </div>

                <TarjetaGrafica titulo="Ventas por día" descripcion="Cuánto se cobró cada día, frente al mismo tramo del periodo anterior.">
                    <GraficaArea
                        :datos="analitica.ventas_por_dia"
                        :formato-valor="formatoCOP"
                        :formato-eje="formatoCOPCompacto"
                        serie="Este periodo"
                        serie-anterior="Periodo anterior"
                        nombre="Ventas por día"
                    />
                </TarjetaGrafica>

                <TarjetaGrafica
                    titulo="Cuándo se llena tu barbería"
                    descripcion="Citas por día de la semana y hora. Úsalo para decidir horarios, turnos y promociones."
                >
                    <MapaCalor
                        :horas="analitica.demanda.horas"
                        :dias="analitica.demanda.dias"
                        :celdas="analitica.demanda.celdas"
                        :maximo="analitica.demanda.maximo"
                        nombre="Citas por día de la semana y hora"
                    />
                </TarjetaGrafica>

                <div class="grid gap-6 lg:grid-cols-2">
                    <TarjetaGrafica titulo="Servicios que más venden" descripcion="Por lo que dejaron en el periodo.">
                        <p v-if="!serviciosFilas.length" class="text-sm text-muted-foreground">Todavía no hay servicios cobrados.</p>
                        <BarrasHorizontales
                            v-else
                            :filas="serviciosFilas"
                            :formato-valor="formatoCOPCompacto"
                            nombre="Servicios que más venden"
                            columna="Ventas"
                        />
                    </TarjetaGrafica>

                    <TarjetaGrafica
                        titulo="Ventas por barbero"
                        :descripcion="manejaComisiones ? 'Lo vendido por cada uno y su comisión.' : 'Lo vendido por cada uno.'"
                    >
                        <p v-if="!barberosFilas.length" class="text-sm text-muted-foreground">Todavía no hay ventas.</p>
                        <BarrasHorizontales
                            v-else
                            :filas="barberosFilas"
                            :formato-valor="formatoCOPCompacto"
                            nombre="Ventas por barbero"
                            columna="Ventas"
                        />
                    </TarjetaGrafica>

                    <TarjetaGrafica titulo="Cómo te pagan" descripcion="Total cobrado por método de pago, con propinas.">
                        <p v-if="!metodosFilas.length" class="text-sm text-muted-foreground">Todavía no hay pagos.</p>
                        <BarrasHorizontales v-else :filas="metodosFilas" :formato-valor="formatoCOPCompacto" nombre="Cómo te pagan" columna="Total" />
                    </TarjetaGrafica>

                    <TarjetaGrafica titulo="De dónde vienen las citas" descripcion="Cuántas reservan solos y cuántas agenda tu equipo.">
                        <p v-if="!origenFilas.length" class="text-sm text-muted-foreground">Todavía no hay citas.</p>
                        <BarrasHorizontales
                            v-else
                            :filas="origenFilas"
                            :formato-valor="(n: number) => String(n)"
                            nombre="De dónde vienen las citas"
                            columna="Citas"
                        />
                    </TarjetaGrafica>

                    <TarjetaGrafica titulo="Clientes del periodo" descripcion="Quiénes vinieron por primera vez y quiénes ya eran clientes.">
                        <BarraDividida :segmentos="segmentosClientes" nombre="Clientes nuevos y recurrentes" />
                        <p v-if="analitica.clientes.sin_registro" class="mt-3 text-xs text-muted-foreground">
                            Además hubo {{ analitica.clientes.sin_registro }} {{ analitica.clientes.sin_registro === 1 ? 'venta' : 'ventas' }} de
                            mostrador sin cliente registrado.
                        </p>
                    </TarjetaGrafica>

                    <TarjetaGrafica
                        titulo="Clientes que podrías recuperar"
                        descripcion="Venían seguido y llevan más de 45 días sin aparecer. Escríbeles tú, con un toque."
                    >
                        <p v-if="!analitica.en_riesgo.length" class="text-sm text-muted-foreground">
                            Por ahora nadie de tus clientes habituales se ha alejado. ¡Buena señal!
                        </p>
                        <ul v-else class="divide-y">
                            <li v-for="c in analitica.en_riesgo" :key="c.id" class="flex items-center gap-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ c.nombre }}</p>
                                    <p class="text-xs text-muted-foreground">{{ c.visitas }} visitas · última hace {{ c.dias }} días</p>
                                </div>
                                <Button variant="outline" size="sm" as-child>
                                    <a
                                        :href="enlaceWhatsapp(c)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :aria-label="`Escribir a ${c.nombre} por WhatsApp`"
                                    >
                                        <MessageCircle /> Escribir
                                    </a>
                                </Button>
                            </li>
                        </ul>
                    </TarjetaGrafica>
                </div>
            </template>
        </div>
    </section>
</template>
