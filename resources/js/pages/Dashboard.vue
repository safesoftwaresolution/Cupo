<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import PanelEstadisticas from '@/components/PanelEstadisticas.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoCOP } from '@/lib/format';
import { type Analitica, type BreadcrumbItem, type PasoConfiguracion, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, Check, Copy, ExternalLink, Link2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CitaProxima {
    id: number;
    hora: string;
    cliente: string;
    barbero: string;
    servicios: string;
    estado: string;
    estado_etiqueta: string;
}

const props = defineProps<{
    pasos: PasoConfiguracion[];
    analitica: Analitica | null;
    rango: string;
    urlReserva: string;
    hoy: { total: number; ingresos: number; cobrado: number; proximas: CitaProxima[] };
}>();

const page = usePage<SharedData>();
const esDueno = computed(() => page.props.auth.user.rol === 'dueno');
const puedeVerAgenda = computed(() => ['dueno', 'recepcion'].includes(page.props.auth.user.rol));
const completados = computed(() => props.pasos.filter((p) => p.hecho).length);
const listo = computed(() => completados.value === props.pasos.length);
const progreso = computed(() => Math.round((completados.value / props.pasos.length) * 100));
const primerNombre = computed(() => page.props.auth.user.name.split(' ')[0]);
const fechaHoy = new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

const copiado = ref(false);
const copiarEnlace = async () => {
    try {
        await navigator.clipboard.writeText(props.urlReserva);
        copiado.value = true;
        setTimeout(() => (copiado.value = false), 2500);
    } catch {
        // Sin permiso para el portapapeles: la persona puede copiar el texto a mano.
    }
};
</script>

<template>
    <Head title="Inicio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <PageHeader :title="`Hola, ${primerNombre}`" :description="`${page.props.barberia?.nombre} · ${fechaHoy}`" />

            <Card v-if="esDueno && !listo" class="max-w-2xl">
                <CardHeader class="space-y-4">
                    <div class="space-y-1">
                        <CardTitle class="text-xl">Deja tu barbería lista</CardTitle>
                        <CardDescription>Tres pasos y tus clientes podrán reservar.</CardDescription>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs font-medium text-muted-foreground">
                            <span>{{ completados }} de {{ pasos.length }} completados</span>
                            <span>{{ progreso }}%</span>
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="progreso"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Progreso de configuración"
                        >
                            <div class="h-full rounded-full bg-brand transition-[width] duration-300" :style="{ width: `${progreso}%` }" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Link
                        v-for="(paso, i) in pasos"
                        :key="paso.clave"
                        :href="paso.href"
                        class="group flex min-h-14 items-center gap-3 rounded-lg border p-3 text-sm transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                            :class="paso.hecho ? 'bg-success text-white' : 'bg-muted text-muted-foreground'"
                        >
                            <Check v-if="paso.hecho" class="size-4" aria-hidden="true" />
                            <template v-else>{{ i + 1 }}</template>
                        </span>
                        <span class="flex-1 font-medium" :class="{ 'text-muted-foreground line-through': paso.hecho }">{{ paso.titulo }}</span>
                        <ArrowRight
                            class="size-4 text-muted-foreground transition-transform duration-150 group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </CardContent>
            </Card>

            <template v-if="puedeVerAgenda && (listo || !esDueno)">
                <div class="grid gap-4 sm:grid-cols-3 lg:max-w-3xl">
                    <Card>
                        <CardHeader class="space-y-1 pb-2">
                            <CardDescription>Citas de hoy</CardDescription>
                            <CardTitle class="text-4xl tabular-nums">{{ hoy.total }}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader class="space-y-1 pb-2">
                            <CardDescription>Previsto hoy</CardDescription>
                            <CardTitle class="text-3xl tabular-nums">{{ formatoCOP(hoy.ingresos) }}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader class="space-y-1 pb-2">
                            <CardDescription>Cobrado hoy</CardDescription>
                            <CardTitle class="text-3xl tabular-nums text-success">{{ formatoCOP(hoy.cobrado) }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card class="max-w-3xl">
                    <CardHeader class="flex-row items-center justify-between space-y-0">
                        <CardTitle class="text-xl">Próximas citas de hoy</CardTitle>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="route('agenda.index')">Ver agenda <ArrowRight /></Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p v-if="!hoy.proximas.length" class="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                            <CalendarDays class="size-4" aria-hidden="true" /> No quedan citas para hoy.
                        </p>
                        <ul v-else class="divide-y">
                            <li v-for="cita in hoy.proximas" :key="cita.id" class="flex items-center gap-4 py-3">
                                <span class="w-14 font-display font-bold tabular-nums">{{ cita.hora }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ cita.cliente }}</p>
                                    <p class="truncate text-sm text-muted-foreground">{{ cita.servicios }} · {{ cita.barbero }}</p>
                                </div>
                                <Badge :variant="cita.estado === 'confirmada' ? 'success' : 'brand'">{{ cita.estado_etiqueta }}</Badge>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </template>

            <PanelEstadisticas
                v-if="esDueno && analitica"
                :analitica="analitica"
                :rango="rango"
                :barberia="page.props.barberia?.nombre ?? ''"
                :url-reserva="urlReserva"
                :maneja-comisiones="page.props.barberia?.maneja_comisiones ?? true"
            />

            <Card v-if="esDueno" class="max-w-3xl">
                <CardHeader class="flex-row items-start gap-4 space-y-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <Link2 class="size-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <CardTitle class="text-xl">Tu enlace de reservas</CardTitle>
                        <CardDescription
                            >Compártelo en WhatsApp, Instagram o ponlo en tu barbería. Tus clientes reservan solos, a cualquier hora.</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <code class="min-w-0 flex-1 truncate rounded-md border bg-muted px-3 py-2.5 text-sm">{{ urlReserva }}</code>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="copiarEnlace">
                            <Check v-if="copiado" class="text-success" />
                            <Copy v-else />
                            {{ copiado ? 'Copiado' : 'Copiar' }}
                        </Button>
                        <Button variant="outline" as-child>
                            <a :href="urlReserva" target="_blank" rel="noopener noreferrer"><ExternalLink /> Abrir</a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
