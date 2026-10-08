<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { capitalizar, formatoCOP } from '@/lib/format';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarPlus, CircleAlert, CircleCheck, CircleX } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    barberia: { nombre: string; slug: string; ciudad: string | null; direccion: string | null; telefono: string | null };
    cita: {
        codigo: string;
        estado: string;
        estado_etiqueta: string;
        inicia_en: string;
        termina_en: string;
        barbero: string;
        cliente: string;
        servicios: string[];
        precio_total: number;
        cancelable: boolean;
    };
}>();

const page = usePage();
const errorCita = computed(() => (page.props.errors as Record<string, string>).cita);

const inicio = computed(() => new Date(props.cita.inicia_en));
const fechaLarga = computed(() => capitalizar(inicio.value.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' })));
const horaLarga = computed(() => inicio.value.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }));
const cancelada = computed(() => props.cita.estado === 'cancelada');

// Enlace para guardar la cita en Google Calendar (fechas en UTC, formato AAAAMMDDTHHMMSSZ).
const enlaceCalendario = computed(() => {
    const utc = (iso: string) => new Date(iso).toISOString().replace(/[-:]|\.\d{3}/g, '');
    const parametros = new URLSearchParams({
        action: 'TEMPLATE',
        text: `${props.cita.servicios.join(' + ')} en ${props.barberia.nombre}`,
        dates: `${utc(props.cita.inicia_en)}/${utc(props.cita.termina_en)}`,
        location: [props.barberia.direccion, props.barberia.ciudad].filter(Boolean).join(', '),
    });

    return `https://calendar.google.com/calendar/render?${parametros}`;
});

const cancelar = () => {
    if (!confirm('¿Seguro que quieres cancelar tu cita? El cupo quedará libre para otra persona.')) return;

    router.post(route('reserva.cancelar', { slug: props.barberia.slug, codigo: props.cita.codigo }), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Tu cita" />

    <PublicLayout :barberia="barberia">
        <FlashMessage />

        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-full"
                    :class="cancelada ? 'bg-destructive/10 text-destructive' : 'bg-success-soft text-success'"
                >
                    <CircleX v-if="cancelada" class="size-6" aria-hidden="true" />
                    <CircleCheck v-else class="size-6" aria-hidden="true" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ cancelada ? 'Cita cancelada' : '¡Tu cita quedó reservada!' }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ cancelada ? 'Ya no te esperamos en este horario.' : `Te esperamos, ${cita.cliente.split(' ')[0]}.` }}
                    </p>
                </div>
            </div>

            <p
                v-if="errorCita"
                role="alert"
                class="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ errorCita }}
            </p>

            <dl class="divide-y overflow-hidden rounded-xl border bg-card text-sm">
                <div class="flex justify-between gap-4 p-4">
                    <dt class="text-muted-foreground">Cuándo</dt>
                    <dd class="text-right font-semibold">{{ fechaLarga }}<br />{{ horaLarga }}</dd>
                </div>
                <div class="flex justify-between gap-4 p-4">
                    <dt class="text-muted-foreground">Barbero</dt>
                    <dd class="font-semibold">{{ cita.barbero }}</dd>
                </div>
                <div class="flex justify-between gap-4 p-4">
                    <dt class="text-muted-foreground">Servicios</dt>
                    <dd class="text-right font-semibold">{{ cita.servicios.join(' + ') }}</dd>
                </div>
                <div class="flex justify-between gap-4 p-4">
                    <dt class="text-muted-foreground">Total a pagar en la barbería</dt>
                    <dd class="font-display text-lg font-bold">{{ formatoCOP(cita.precio_total) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 p-4">
                    <dt class="text-muted-foreground">Estado</dt>
                    <dd>
                        <Badge :variant="cancelada ? 'muted' : cita.estado === 'confirmada' ? 'success' : 'brand'">{{ cita.estado_etiqueta }}</Badge>
                    </dd>
                </div>
            </dl>

            <div v-if="!cancelada" class="flex flex-col gap-2 sm:flex-row">
                <Button variant="outline" as-child class="sm:flex-1">
                    <a :href="enlaceCalendario" target="_blank" rel="noopener noreferrer"><CalendarPlus /> Agregar a mi calendario</a>
                </Button>
                <Button v-if="cita.cancelable" variant="ghost" class="text-destructive hover:text-destructive sm:flex-1" @click="cancelar"
                    >Cancelar mi cita</Button
                >
            </div>

            <p class="text-xs text-muted-foreground">
                Guarda este enlace para consultar o cancelar tu cita. Si necesitas cambiarla, cancélala y reserva otro horario.
                <template v-if="barberia.telefono"> También puedes llamar al {{ barberia.telefono }}.</template>
            </p>
        </div>
    </PublicLayout>
</template>
