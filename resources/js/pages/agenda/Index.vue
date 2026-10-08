<script setup lang="ts">
import CobroDialog from '@/components/CobroDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoCOP } from '@/lib/format';
import { type BreadcrumbItem, type CitaPorCobrar, type MetodoPagoOpcion } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, CircleAlert, LoaderCircle, MessageCircle, Phone, Plus, Users, Wallet } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface CitaAgenda {
    id: number;
    barbero_id: number;
    inicia: string;
    termina: string;
    estado: string;
    estado_etiqueta: string;
    cliente: { nombre: string; telefono: string };
    servicios: string;
    precio_total: number;
    origen: string;
    notas: string | null;
    cobrable: boolean;
    acepta_whatsapp: boolean;
    recordatorio: { estado: string; etiqueta: string; enviado: string | null; respuesta: string | null; error: string | null } | null;
    venta: { total: number; metodo: string } | null;
    transiciones: { valor: string; etiqueta: string }[];
}

interface BarberoAgenda {
    id: number;
    nombre: string;
    color: string | null;
    servicios: number[];
    franjas: { inicio: string; fin: string }[];
}

interface ServicioAgenda {
    id: number;
    nombre: string;
    duracion_minutos: number;
    precio: number;
}

const props = defineProps<{
    fecha: string;
    hoy: string;
    barberos: BarberoAgenda[];
    citas: CitaAgenda[];
    servicios: ServicioAgenda[];
    metodos: MetodoPagoOpcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Agenda', href: '/agenda' }];

const page = usePage();
const errorEstado = computed(() => (page.props.errors as Record<string, string>).estado);

// --- Navegación entre días ---------------------------------------------------

const sumarDias = (fecha: string, dias: number) => {
    const d = new Date(`${fecha}T12:00:00`);
    d.setDate(d.getDate() + dias);

    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
};

const irA = (fecha: string) =>
    router.get(route('agenda.index'), { fecha }, { preserveScroll: true, preserveState: true, only: ['fecha', 'barberos', 'citas'] });

const tituloFecha = computed(() => {
    const texto = new Date(`${props.fecha}T12:00:00`).toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });

    return props.fecha === props.hoy ? `Hoy, ${texto}` : texto;
});

// --- Rejilla de horas -------------------------------------------------------------

const PX_POR_MINUTO = 1.15;

const aMinutos = (hora: string) => {
    const [h, m] = hora.split(':').map(Number);

    return h * 60 + m;
};

const mostrarCanceladas = ref(false);

const citasVisibles = computed(() => props.citas.filter((c) => mostrarCanceladas.value || !['cancelada', 'no_asistio'].includes(c.estado)));
const hayCanceladas = computed(() => props.citas.some((c) => ['cancelada', 'no_asistio'].includes(c.estado)));

// El rango de horas se ajusta a lo que hay ese día (mínimo 8:00 a 20:00).
const rango = computed(() => {
    const inicios = [
        8 * 60,
        ...props.barberos.flatMap((b) => b.franjas.map((f) => aMinutos(f.inicio))),
        ...props.citas.map((c) => aMinutos(c.inicia)),
    ];
    const fines = [20 * 60, ...props.barberos.flatMap((b) => b.franjas.map((f) => aMinutos(f.fin))), ...props.citas.map((c) => aMinutos(c.termina))];

    return { inicio: Math.floor(Math.min(...inicios) / 60) * 60, fin: Math.ceil(Math.max(...fines) / 60) * 60 };
});

const alto = computed(() => (rango.value.fin - rango.value.inicio) * PX_POR_MINUTO);
const horasEje = computed(() => Array.from({ length: (rango.value.fin - rango.value.inicio) / 60 }, (_, i) => rango.value.inicio / 60 + i));
const etiquetaHora = (h: number) => `${h % 12 === 0 ? 12 : h % 12} ${h < 12 ? 'a. m.' : 'p. m.'}`;

const estilo = (inicio: string, fin: string) => ({
    top: `${(aMinutos(inicio) - rango.value.inicio) * PX_POR_MINUTO}px`,
    height: `${Math.max((aMinutos(fin) - aMinutos(inicio)) * PX_POR_MINUTO, 24)}px`,
});

const citasDe = (barberoId: number) => citasVisibles.value.filter((c) => c.barbero_id === barberoId);

const clasesEstado = (estado: string) =>
    ({
        pendiente: 'border-brand bg-brand-soft',
        confirmada: 'border-success bg-success-soft',
        completada: 'border-muted-foreground/40 bg-muted',
        cancelada: 'border-muted-foreground/30 bg-muted opacity-60 line-through',
        no_asistio: 'border-destructive/50 bg-destructive/10 opacity-70',
    })[estado] ?? 'bg-muted';

// Línea de "ahora": solo si se mira el día de hoy.
const ahora = ref(new Date());
let reloj: ReturnType<typeof setInterval> | undefined;
onMounted(() => (reloj = setInterval(() => (ahora.value = new Date()), 60_000)));
onBeforeUnmount(() => clearInterval(reloj));

const lineaAhora = computed(() => {
    if (props.fecha !== props.hoy) return null;
    const minutos = ahora.value.getHours() * 60 + ahora.value.getMinutes();

    return minutos < rango.value.inicio || minutos > rango.value.fin ? null : `${(minutos - rango.value.inicio) * PX_POR_MINUTO}px`;
});

const totalDia = computed(() => citasVisibles.value.filter((c) => !['cancelada', 'no_asistio'].includes(c.estado)).length);

// --- Detalle de una cita ----------------------------------------------------------------

const detalleAbierto = ref(false);
const seleccionada = ref<CitaAgenda | null>(null);

// Se vuelve a leer desde las props para que el diálogo refleje el cambio de estado.
const citaActual = computed(() => props.citas.find((c) => c.id === seleccionada.value?.id) ?? null);

const verCita = (cita: CitaAgenda) => {
    seleccionada.value = cita;
    detalleAbierto.value = true;
};

const verbos: Record<string, string> = {
    confirmada: 'Confirmar',
    completada: 'Completar sin cobrar',
    no_asistio: 'No asistió',
    cancelada: 'Cancelar cita',
};

const cambiarEstado = (cita: CitaAgenda, estado: string) => {
    if (estado === 'cancelada' && !confirm(`¿Cancelar la cita de ${cita.cliente.nombre}? El cupo quedará libre.`)) return;

    router.patch(route('agenda.citas.estado', cita.id), { estado }, { preserveScroll: true, onSuccess: () => (detalleAbierto.value = false) });
};

// --- Cobrar ---------------------------------------------------------------------------

const cobroAbierto = ref(false);
const citaACobrar = ref<CitaPorCobrar | null>(null);

const cobrar = (cita: CitaAgenda) => {
    citaACobrar.value = { id: cita.id, cliente: cita.cliente.nombre, servicios: cita.servicios, precio_total: cita.precio_total };
    detalleAbierto.value = false;
    cobroAbierto.value = true;
};

// --- Nueva cita -----------------------------------------------------------------------

const nuevaAbierta = ref(false);
const form = useForm({ nombre: '', telefono: '', notas: '' });
const nuevaServicios = ref<number[]>([]);
const nuevaBarberoId = ref<number | null>(null);
const nuevaFecha = ref(props.fecha);
const nuevaHora = ref<string | null>(null);
const horasLibres = ref<string[]>([]);
const cargandoHoras = ref(false);

const elegibles = computed(() =>
    props.barberos.filter((b) => nuevaServicios.value.length > 0 && nuevaServicios.value.every((id) => b.servicios.includes(id))),
);

const abrirNueva = () => {
    form.reset();
    form.clearErrors();
    nuevaServicios.value = [];
    nuevaBarberoId.value = null;
    nuevaFecha.value = props.fecha < props.hoy ? props.hoy : props.fecha;
    nuevaHora.value = null;
    horasLibres.value = [];
    nuevaAbierta.value = true;
};

const cargarHoras = async () => {
    nuevaHora.value = null;
    horasLibres.value = [];

    if (!nuevaServicios.value.length || !nuevaBarberoId.value || !nuevaFecha.value) return;

    cargandoHoras.value = true;
    const parametros = new URLSearchParams({ fecha: nuevaFecha.value, barbero_id: String(nuevaBarberoId.value) });
    nuevaServicios.value.forEach((id) => parametros.append('servicios[]', String(id)));

    try {
        const respuesta = await fetch(`${route('agenda.disponibilidad')}?${parametros}`, { headers: { Accept: 'application/json' } });
        if (respuesta.ok) horasLibres.value = (await respuesta.json()).barberos[0]?.horas ?? [];
    } finally {
        cargandoHoras.value = false;
    }
};

watch([nuevaServicios, nuevaBarberoId, nuevaFecha], () => {
    if (nuevaBarberoId.value && !elegibles.value.some((b) => b.id === nuevaBarberoId.value)) nuevaBarberoId.value = null;
    cargarHoras();
});

const alternarServicio = (id: number) => {
    nuevaServicios.value = nuevaServicios.value.includes(id) ? nuevaServicios.value.filter((s) => s !== id) : [...nuevaServicios.value, id];
};

const guardarCita = () => {
    form.transform((datos) => ({
        ...datos,
        barbero_id: nuevaBarberoId.value,
        servicios: nuevaServicios.value,
        inicia_en: `${nuevaFecha.value} ${nuevaHora.value}`,
    })).post(route('agenda.citas.store'), {
        preserveScroll: true,
        onSuccess: () => {
            nuevaAbierta.value = false;
            if (nuevaFecha.value !== props.fecha) irA(nuevaFecha.value);
        },
        onError: (errores) => {
            if (errores.inicia_en) cargarHoras();
        },
    });
};

const enlaceWhatsapp = (telefono: string) => `https://wa.me/${telefono}`;
const telefonoLegible = (telefono: string) => telefono.replace(/^57(\d{3})(\d{3})(\d{4})$/, '$1 $2 $3');
</script>

<template>
    <Head title="Agenda" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <PageHeader title="Agenda" :description="`${totalDia} ${totalDia === 1 ? 'cita' : 'citas'} · ${tituloFecha}`">
                <Button :disabled="barberos.length === 0 || servicios.length === 0" @click="abrirNueva"><Plus /> Nueva cita</Button>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="icon" aria-label="Día anterior" @click="irA(sumarDias(fecha, -1))"><ChevronLeft /></Button>
                <Button variant="outline" size="icon" aria-label="Día siguiente" @click="irA(sumarDias(fecha, 1))"><ChevronRight /></Button>
                <Button variant="outline" :disabled="fecha === hoy" @click="irA(hoy)">Hoy</Button>
                <Input type="date" :model-value="fecha" class="w-44" aria-label="Ir a una fecha" @update:model-value="(v) => v && irA(String(v))" />
                <label v-if="hayCanceladas" class="ml-auto flex min-h-11 items-center gap-2 text-sm">
                    <input v-model="mostrarCanceladas" type="checkbox" class="size-5 accent-[hsl(var(--brand))]" />
                    Mostrar canceladas
                </label>
            </div>

            <p
                v-if="errorEstado"
                role="alert"
                class="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ errorEstado }}
            </p>

            <EmptyState
                v-if="barberos.length === 0"
                :icon="Users"
                title="Aún no hay barberos activos"
                description="Agrega a tu equipo y define sus horarios para empezar a recibir citas."
            />

            <div v-else class="overflow-x-auto rounded-xl border bg-card">
                <div class="grid min-w-max" :style="{ gridTemplateColumns: `4.5rem repeat(${barberos.length}, minmax(13rem, 1fr))` }">
                    <!-- Cabecera -->
                    <div class="sticky left-0 z-20 border-b bg-card" />
                    <div v-for="barbero in barberos" :key="barbero.id" class="flex items-center gap-2 border-b border-l px-3 py-3">
                        <span class="size-3 shrink-0 rounded-full" :style="{ backgroundColor: barbero.color ?? '#78716c' }" aria-hidden="true" />
                        <span class="truncate text-sm font-semibold">{{ barbero.nombre }}</span>
                        <Badge v-if="!barbero.franjas.length" variant="muted" class="ml-auto">No atiende</Badge>
                    </div>

                    <!-- Eje de horas -->
                    <div class="sticky left-0 z-20 border-r bg-card" :style="{ height: `${alto}px` }">
                        <div class="relative h-full">
                            <span
                                v-for="h in horasEje"
                                :key="h"
                                class="absolute right-2 -translate-y-1/2 text-xs tabular-nums text-muted-foreground first:translate-y-0"
                                :style="{ top: `${(h * 60 - rango.inicio) * PX_POR_MINUTO}px` }"
                                >{{ etiquetaHora(h) }}</span
                            >
                        </div>
                    </div>

                    <!-- Columnas por barbero -->
                    <div v-for="barbero in barberos" :key="barbero.id" class="relative border-l bg-muted/60" :style="{ height: `${alto}px` }">
                        <!-- Horas en que el barbero atiende -->
                        <div
                            v-for="franja in barbero.franjas"
                            :key="franja.inicio"
                            class="absolute inset-x-0 bg-card"
                            :style="estilo(franja.inicio, franja.fin)"
                            aria-hidden="true"
                        />
                        <!-- Líneas de cada hora -->
                        <div
                            v-for="h in horasEje"
                            :key="h"
                            class="absolute inset-x-0 border-t border-border/70"
                            :style="{ top: `${(h * 60 - rango.inicio) * PX_POR_MINUTO}px` }"
                            aria-hidden="true"
                        />
                        <div
                            v-if="lineaAhora"
                            class="absolute inset-x-0 z-10 border-t-2 border-destructive"
                            :style="{ top: lineaAhora }"
                            aria-hidden="true"
                        />

                        <button
                            v-for="cita in citasDe(barbero.id)"
                            :key="cita.id"
                            type="button"
                            class="absolute inset-x-1 z-[5] overflow-hidden rounded-md border-l-4 px-2 py-1 text-left text-xs shadow-sm transition-shadow duration-150 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="clasesEstado(cita.estado)"
                            :style="estilo(cita.inicia, cita.termina)"
                            :aria-label="`${cita.inicia} a ${cita.termina}, ${cita.cliente.nombre}, ${cita.estado_etiqueta}`"
                            @click="verCita(cita)"
                        >
                            <span class="block truncate font-semibold">{{ cita.cliente.nombre }}</span>
                            <span class="block truncate text-muted-foreground">{{ cita.inicia }} · {{ cita.servicios }}</span>
                            <span v-if="cita.venta" class="block truncate font-semibold text-success"
                                >Cobrada · {{ formatoCOP(cita.venta.total) }}</span
                            >
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="barberos.length && citas.length === 0" class="flex items-center gap-2 text-sm text-muted-foreground">
                <CalendarDays class="size-4" aria-hidden="true" /> No hay citas este día. Usa “Nueva cita” para agendar una.
            </p>
        </div>

        <!-- Detalle de la cita -->
        <Dialog v-model:open="detalleAbierto">
            <DialogContent v-if="citaActual" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">{{ citaActual.cliente.nombre }}</DialogTitle>
                    <DialogDescription>{{ citaActual.inicia }} a {{ citaActual.termina }} · {{ citaActual.servicios }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <Badge :variant="citaActual.estado === 'confirmada' ? 'success' : citaActual.estado === 'pendiente' ? 'brand' : 'muted'">{{
                            citaActual.estado_etiqueta
                        }}</Badge>
                        <span class="font-display text-lg font-bold">{{ formatoCOP(citaActual.precio_total) }}</span>
                    </div>
                    <p class="text-muted-foreground">
                        Celular: <span class="font-medium tabular-nums text-foreground">{{ telefonoLegible(citaActual.cliente.telefono) }}</span>
                    </p>
                    <div class="rounded-lg border p-3">
                        <p class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <MessageCircle class="size-3.5" aria-hidden="true" /> Recordatorio por WhatsApp
                        </p>
                        <template v-if="citaActual.recordatorio">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    :variant="
                                        ['entregado', 'leido'].includes(citaActual.recordatorio.estado)
                                            ? 'success'
                                            : citaActual.recordatorio.estado === 'fallido'
                                              ? 'muted'
                                              : 'brand'
                                    "
                                    >{{ citaActual.recordatorio.etiqueta }}</Badge
                                >
                                <span v-if="citaActual.recordatorio.enviado" class="text-xs text-muted-foreground"
                                    >a las {{ citaActual.recordatorio.enviado }}</span
                                >
                                <Badge v-if="citaActual.recordatorio.respuesta === 'confirmo'" variant="success">Confirmó por WhatsApp</Badge>
                            </div>
                            <p v-if="citaActual.recordatorio.error" class="mt-1.5 text-xs text-destructive">{{ citaActual.recordatorio.error }}</p>
                        </template>
                        <p v-else-if="!citaActual.acepta_whatsapp" class="text-sm text-muted-foreground">
                            El cliente no autorizó recibir mensajes. Escríbele tú si necesitas avisarle.
                        </p>
                        <p v-else class="text-sm text-muted-foreground">Se enviará automáticamente antes de la cita.</p>
                    </div>
                    <p v-if="citaActual.notas" class="rounded-md bg-muted p-3">{{ citaActual.notas }}</p>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <a :href="enlaceWhatsapp(citaActual.cliente.telefono)" target="_blank" rel="noopener noreferrer"
                                ><MessageCircle /> WhatsApp</a
                            >
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <a :href="`tel:+${citaActual.cliente.telefono}`"><Phone /> Llamar</a>
                        </Button>
                    </div>
                </div>

                <p v-if="citaActual.venta" class="flex items-center gap-2 rounded-lg bg-success-soft px-3 py-2 text-sm font-semibold text-success">
                    Cobrada: {{ formatoCOP(citaActual.venta.total) }} · {{ citaActual.venta.metodo }}
                </p>

                <DialogFooter v-if="citaActual.cobrable || citaActual.transiciones.length" class="flex-col gap-2 sm:flex-col sm:space-x-0">
                    <Button v-if="citaActual.cobrable" class="w-full" @click="cobrar(citaActual)"><Wallet /> Cobrar</Button>
                    <Button
                        v-for="t in citaActual.transiciones"
                        :key="t.valor"
                        :variant="t.valor === 'confirmada' ? 'default' : t.valor === 'cancelada' ? 'ghost' : 'outline'"
                        :class="{ 'text-destructive hover:text-destructive': t.valor === 'cancelada' }"
                        class="w-full"
                        @click="cambiarEstado(citaActual, t.valor)"
                    >
                        {{ verbos[t.valor] ?? t.etiqueta }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Nueva cita -->
        <Dialog v-model:open="nuevaAbierta">
            <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">Nueva cita</DialogTitle>
                    <DialogDescription>Para quien llega o llama. El cupo se reserva al instante.</DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="guardarCita">
                    <fieldset class="grid gap-1">
                        <legend class="mb-1 text-sm font-medium">Servicios</legend>
                        <label v-for="servicio in servicios" :key="servicio.id" class="flex min-h-11 items-center gap-3 text-sm">
                            <input
                                type="checkbox"
                                :checked="nuevaServicios.includes(servicio.id)"
                                class="size-5 accent-[hsl(var(--brand))]"
                                @change="alternarServicio(servicio.id)"
                            />
                            <span class="flex-1">{{ servicio.nombre }}</span>
                            <span class="tabular-nums text-muted-foreground">{{ formatoCOP(servicio.precio) }}</span>
                        </label>
                        <InputError :message="form.errors.servicios" />
                    </fieldset>

                    <div v-if="nuevaServicios.length" class="grid gap-2">
                        <Label for="barbero">Barbero</Label>
                        <select
                            id="barbero"
                            v-model="nuevaBarberoId"
                            class="flex h-11 w-full rounded-md border border-input bg-background px-3 text-base md:h-10 md:text-sm"
                            required
                        >
                            <option :value="null" disabled>Elige un barbero</option>
                            <option v-for="b in elegibles" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                        </select>
                        <p v-if="!elegibles.length" class="text-sm text-muted-foreground">Ningún barbero hace todos esos servicios juntos.</p>
                        <InputError :message="form.errors.barbero_id" />
                    </div>

                    <div v-if="nuevaBarberoId" class="grid gap-2">
                        <Label for="fecha-cita">Día</Label>
                        <Input id="fecha-cita" v-model="nuevaFecha" type="date" :min="hoy" required />
                        <p v-if="cargandoHoras" class="flex items-center gap-2 text-sm text-muted-foreground">
                            <LoaderCircle class="size-4 animate-spin" /> Buscando cupos…
                        </p>
                        <p v-else-if="!horasLibres.length" class="text-sm text-muted-foreground">No hay cupos libres ese día para este barbero.</p>
                        <ul v-else class="grid grid-cols-4 gap-2" aria-label="Horas libres">
                            <li v-for="h in horasLibres" :key="h">
                                <button
                                    type="button"
                                    :aria-pressed="nuevaHora === h"
                                    class="h-10 w-full rounded-md border bg-card text-sm font-semibold tabular-nums transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    :class="{ '!border-primary !bg-primary !text-primary-foreground': nuevaHora === h }"
                                    @click="nuevaHora = h"
                                >
                                    {{ h }}
                                </button>
                            </li>
                        </ul>
                        <InputError :message="form.errors.inicia_en" />
                    </div>

                    <template v-if="nuevaHora">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="nombre-cliente">Nombre</Label>
                                <Input id="nombre-cliente" v-model="form.nombre" required placeholder="Nombre del cliente" />
                                <InputError :message="form.errors.nombre" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="telefono-cliente">Celular</Label>
                                <Input id="telefono-cliente" v-model="form.telefono" type="tel" inputmode="tel" required placeholder="300 123 4567" />
                                <InputError :message="form.errors.telefono" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="notas">Notas (opcional)</Label>
                            <Input id="notas" v-model="form.notas" placeholder="Fade 2 a los lados, barba con navaja…" />
                            <InputError :message="form.errors.notas" />
                        </div>
                    </template>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="nuevaAbierta = false">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing || !nuevaHora">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            Agendar cita
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        <CobroDialog v-model:open="cobroAbierto" :cita="citaACobrar" :metodos="metodos" />
    </AppLayout>
</template>
