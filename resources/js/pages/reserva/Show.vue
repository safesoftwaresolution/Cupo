<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { capitalizar, formatoCOP, formatoDuracion } from '@/lib/format';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Clock, LoaderCircle, Shuffle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ServicioPublico {
    id: number;
    nombre: string;
    descripcion: string | null;
    duracion_minutos: number;
    precio: number;
}

interface BarberoPublico {
    id: number;
    nombre: string;
    color: string | null;
    servicios: number[];
}

const props = defineProps<{
    barberia: { nombre: string; slug: string; ciudad: string | null; direccion: string | null; telefono: string | null };
    servicios: ServicioPublico[];
    barberos: BarberoPublico[];
    hoy: string;
    diasReserva: number;
}>();

const seleccion = ref<number[]>([]);
const barberoId = ref<number | null>(null); // null = cualquiera
const fecha = ref<string | null>(null);
const hora = ref<string | null>(null);

const horas = ref<string[]>([]);
const cargandoHoras = ref(false);
const errorHoras = ref<string | null>(null);

const seleccionados = computed(() => props.servicios.filter((s) => seleccion.value.includes(s.id)));
const total = computed(() => seleccionados.value.reduce((suma, s) => suma + s.precio, 0));
const duracion = computed(() => seleccionados.value.reduce((suma, s) => suma + s.duracion_minutos, 0));

// Solo se ofrecen barberos que hacen TODOS los servicios elegidos.
const elegibles = computed(() => props.barberos.filter((b) => seleccion.value.every((id) => b.servicios.includes(id))));

// Hasta 14 días visibles (o los que permita la barbería), con la fecha como texto para no depender de zonas horarias.
const dias = computed(() => {
    const cantidad = Math.min(props.diasReserva, 14) + 1;
    const base = new Date(`${props.hoy}T12:00:00`);

    return Array.from({ length: cantidad }, (_, i) => {
        const d = new Date(base);
        d.setDate(base.getDate() + i);
        const valor = [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');

        return {
            valor,
            dia: i === 0 ? 'Hoy' : d.toLocaleDateString('es-CO', { weekday: 'short' }).replace('.', ''),
            numero: d.getDate(),
            mes: d.toLocaleDateString('es-CO', { month: 'short' }).replace('.', ''),
        };
    });
});

const form = useForm({
    nombre: '',
    telefono: '',
    acepta: false,
    sitio_web: '', // campo trampa para bots
    servicios: [] as number[],
    barbero_id: null as number | null,
    inicia_en: '',
});

let controlador: AbortController | null = null;

const cargarHoras = async () => {
    controlador?.abort();
    horas.value = [];
    errorHoras.value = null;

    if (!seleccion.value.length || !fecha.value) return;

    controlador = new AbortController();
    cargandoHoras.value = true;

    const parametros = new URLSearchParams({ fecha: fecha.value });
    seleccion.value.forEach((id) => parametros.append('servicios[]', String(id)));
    if (barberoId.value) parametros.set('barbero_id', String(barberoId.value));

    try {
        const respuesta = await fetch(`${route('reserva.disponibilidad', { slug: props.barberia.slug })}?${parametros}`, {
            headers: { Accept: 'application/json' },
            signal: controlador.signal,
        });
        if (!respuesta.ok) throw new Error('respuesta no válida');

        horas.value = (await respuesta.json()).horas;
        if (hora.value && !horas.value.includes(hora.value)) hora.value = null;
    } catch (e) {
        if ((e as Error).name !== 'AbortError') errorHoras.value = 'No pudimos cargar los horarios. Intenta de nuevo.';
    } finally {
        cargandoHoras.value = false;
    }
};

watch([seleccion, barberoId, fecha], () => {
    // Si el barbero elegido ya no hace todos los servicios, se vuelve a "cualquiera".
    if (barberoId.value && !elegibles.value.some((b) => b.id === barberoId.value)) barberoId.value = null;
    hora.value = null;
    cargarHoras();
});

const alternarServicio = (id: number) => {
    seleccion.value = seleccion.value.includes(id) ? seleccion.value.filter((s) => s !== id) : [...seleccion.value, id];
};

const fechaLarga = computed(() => {
    if (!fecha.value) return '';

    return capitalizar(new Date(`${fecha.value}T12:00:00`).toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }));
});

const nombreBarbero = computed(() => (barberoId.value ? props.barberos.find((b) => b.id === barberoId.value)?.nombre : 'el primero disponible'));

const reservar = () => {
    form.transform((datos) => ({
        ...datos,
        servicios: seleccion.value,
        barbero_id: barberoId.value,
        inicia_en: `${fecha.value} ${hora.value}`,
    })).post(route('reserva.store', { slug: props.barberia.slug }), {
        preserveScroll: true,
        // Si el horario se ocupó mientras llenaba sus datos, se refrescan las horas libres.
        onError: (errores) => {
            if (errores.inicia_en) {
                hora.value = null;
                cargarHoras();
            }
        },
    });
};
</script>

<template>
    <Head :title="`Reservar en ${barberia.nombre}`" />

    <PublicLayout :barberia="barberia">
        <div v-if="servicios.length === 0" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
            Esta barbería todavía no tiene horarios disponibles para reservar en línea.
            <template v-if="barberia.telefono"> Comunícate al {{ barberia.telefono }}.</template>
        </div>

        <form v-else class="space-y-10" @submit.prevent="reservar" novalidate>
            <!-- 1. Servicios -->
            <section aria-labelledby="paso-servicios" class="space-y-3">
                <h2 id="paso-servicios" class="flex items-center gap-3 text-xl font-bold">
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary text-sm text-primary-foreground">1</span>
                    ¿Qué te vas a hacer?
                </h2>
                <ul class="space-y-2">
                    <li v-for="servicio in servicios" :key="servicio.id">
                        <label class="block">
                            <input
                                type="checkbox"
                                class="peer sr-only"
                                :checked="seleccion.includes(servicio.id)"
                                @change="alternarServicio(servicio.id)"
                            />
                            <span
                                class="flex min-h-16 items-center gap-3 rounded-xl border bg-card p-4 transition-colors duration-150 hover:bg-accent peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:hover:bg-brand-soft peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                            >
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-md border-2"
                                    :class="seleccion.includes(servicio.id) ? 'border-brand bg-brand text-brand-foreground' : 'border-input'"
                                    aria-hidden="true"
                                >
                                    <Check v-if="seleccion.includes(servicio.id)" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold">{{ servicio.nombre }}</span>
                                    <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <Clock class="size-3.5" aria-hidden="true" />
                                        {{ formatoDuracion(servicio.duracion_minutos) }}
                                        <template v-if="servicio.descripcion"> · {{ servicio.descripcion }}</template>
                                    </span>
                                </span>
                                <span class="font-display text-lg font-bold tabular-nums">{{ formatoCOP(servicio.precio) }}</span>
                            </span>
                        </label>
                    </li>
                </ul>
            </section>

            <!-- 2. Barbero -->
            <section v-if="seleccion.length" aria-labelledby="paso-barbero" class="space-y-3">
                <h2 id="paso-barbero" class="flex items-center gap-3 text-xl font-bold">
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary text-sm text-primary-foreground">2</span>
                    ¿Con quién?
                </h2>
                <p v-if="!elegibles.length" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    Ningún barbero hace todos esos servicios juntos. Prueba con otra combinación.
                </p>
                <fieldset v-else class="grid gap-2 sm:grid-cols-2">
                    <legend class="sr-only">Barbero</legend>
                    <label class="block">
                        <input type="radio" name="barbero" class="peer sr-only" :checked="barberoId === null" @change="barberoId = null" />
                        <span
                            class="flex min-h-14 items-center gap-3 rounded-xl border bg-card p-3 transition-colors duration-150 hover:bg-accent peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:hover:bg-brand-soft peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                        >
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted" aria-hidden="true"
                                ><Shuffle class="size-4"
                            /></span>
                            <span class="font-semibold">Cualquiera disponible</span>
                        </span>
                    </label>
                    <label v-for="barbero in elegibles" :key="barbero.id" class="block">
                        <input
                            type="radio"
                            name="barbero"
                            class="peer sr-only"
                            :checked="barberoId === barbero.id"
                            @change="barberoId = barbero.id"
                        />
                        <span
                            class="flex min-h-14 items-center gap-3 rounded-xl border bg-card p-3 transition-colors duration-150 hover:bg-accent peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:hover:bg-brand-soft peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                :style="{ backgroundColor: barbero.color ?? '#78716c' }"
                                aria-hidden="true"
                                >{{ barbero.nombre.charAt(0).toUpperCase() }}</span
                            >
                            <span class="font-semibold">{{ barbero.nombre }}</span>
                        </span>
                    </label>
                </fieldset>
            </section>

            <!-- 3. Fecha y hora -->
            <section v-if="seleccion.length && elegibles.length" aria-labelledby="paso-fecha" class="space-y-4">
                <h2 id="paso-fecha" class="flex items-center gap-3 text-xl font-bold">
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary text-sm text-primary-foreground">3</span>
                    ¿Cuándo?
                </h2>

                <div class="-mx-4 overflow-x-auto px-4 pb-2">
                    <ul class="flex gap-2" role="list">
                        <li v-for="d in dias" :key="d.valor">
                            <button
                                type="button"
                                :aria-pressed="fecha === d.valor"
                                class="flex h-20 w-16 flex-col items-center justify-center rounded-xl border bg-card transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :class="{ '!border-brand !bg-brand-soft': fecha === d.valor }"
                                @click="fecha = d.valor"
                            >
                                <span class="text-xs capitalize text-muted-foreground">{{ d.dia }}</span>
                                <span class="font-display text-xl font-bold leading-tight">{{ d.numero }}</span>
                                <span class="text-xs capitalize text-muted-foreground">{{ d.mes }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div v-if="fecha" aria-live="polite">
                    <p v-if="cargandoHoras" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Buscando cupos…
                    </p>
                    <p v-else-if="errorHoras" class="text-sm text-destructive">{{ errorHoras }}</p>
                    <p v-else-if="!horas.length" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        No hay cupos libres ese día. Prueba con otro día
                        <template v-if="barberoId"> u otro barbero</template>.
                    </p>
                    <ul v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <li v-for="h in horas" :key="h">
                            <button
                                type="button"
                                :aria-pressed="hora === h"
                                class="h-11 w-full rounded-lg border bg-card text-sm font-semibold tabular-nums transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :class="{ '!border-primary !bg-primary !text-primary-foreground': hora === h }"
                                @click="hora = h"
                            >
                                {{ h }}
                            </button>
                        </li>
                    </ul>
                    <InputError :message="form.errors.inicia_en" class="mt-2" />
                </div>
            </section>

            <!-- 4. Datos -->
            <section v-if="hora" aria-labelledby="paso-datos" class="space-y-4">
                <h2 id="paso-datos" class="flex items-center gap-3 text-xl font-bold">
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary text-sm text-primary-foreground">4</span>
                    Tus datos
                </h2>

                <div class="rounded-xl border bg-card p-4 text-sm">
                    <p class="font-semibold">{{ fechaLarga }} · {{ hora }}</p>
                    <p class="mt-1 text-muted-foreground">{{ seleccionados.map((s) => s.nombre).join(' + ') }} · {{ formatoDuracion(duracion) }}</p>
                    <p class="text-muted-foreground">Con {{ nombreBarbero }}</p>
                    <p class="mt-2 font-display text-lg font-bold">{{ formatoCOP(total) }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="nombre">Tu nombre</Label>
                    <Input id="nombre" v-model="form.nombre" autocomplete="name" required placeholder="Nombre y apellido" />
                    <InputError :message="form.errors.nombre" />
                </div>
                <div class="grid gap-2">
                    <Label for="telefono">Tu celular (WhatsApp)</Label>
                    <Input id="telefono" v-model="form.telefono" type="tel" inputmode="tel" autocomplete="tel" required placeholder="300 123 4567" />
                    <p class="text-xs text-muted-foreground">Te enviaremos aquí el recordatorio de tu cita.</p>
                    <InputError :message="form.errors.telefono" />
                </div>

                <!-- Campo trampa: invisible para personas -->
                <input
                    v-model="form.sitio_web"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                    aria-hidden="true"
                    class="absolute -left-[9999px] size-0 opacity-0"
                />

                <label class="flex items-start gap-3 text-sm">
                    <input v-model="form.acepta" type="checkbox" class="mt-0.5 size-5 shrink-0 accent-[hsl(var(--brand))]" />
                    <span>
                        Autorizo a {{ barberia.nombre }} a tratar mis datos para gestionar mi cita y enviarme recordatorios por WhatsApp, conforme a
                        la Ley 1581 de 2012.
                    </span>
                </label>
                <InputError :message="form.errors.acepta" />
                <InputError :message="form.errors.sitio_web" />

                <Button type="submit" size="lg" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Confirmar mi cita
                </Button>
            </section>
        </form>
    </PublicLayout>
</template>
