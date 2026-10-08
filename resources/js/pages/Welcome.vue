<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatoCOP } from '@/lib/format';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bot, CalendarCheck, Check, Clock, Link2, MessageCircle, NotebookPen, UserX, Users, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const autenticado = computed(() => !!page.props.auth.user);

const dolores = [
    { icono: MessageCircle, problema: 'WhatsApp todo el día', solucion: 'Tus clientes reservan solos y el sistema responde por ti.' },
    { icono: UserX, problema: 'Clientes que no llegan', solucion: 'Recordatorios con botón de confirmar, reagendar o cancelar.' },
    { icono: NotebookPen, problema: 'Cuentas en el cuaderno', solucion: 'Caja del día y comisión de cada barbero, calculadas solas.' },
];

const funciones = [
    {
        icono: CalendarCheck,
        titulo: 'Agenda sin cruces',
        texto: 'Cupos por barbero según su horario. Si alguien cancela, el espacio se libera.',
        pronto: true,
    },
    {
        icono: Link2,
        titulo: 'Reserva por link y QR',
        texto: 'Tu barbería tiene su propia página para reservar desde el celular, sin descargar nada.',
        pronto: true,
    },
    {
        icono: MessageCircle,
        titulo: 'Recordatorios por WhatsApp',
        texto: 'Confirmación y aviso antes de la cita desde el WhatsApp oficial de tu barbería.',
        pronto: true,
    },
    {
        icono: Bot,
        titulo: 'Asistente que entiende audios',
        texto: 'Tu cliente escribe o manda una nota de voz y el asistente le agenda el cupo.',
        pronto: true,
    },
    { icono: Wallet, titulo: 'Caja y comisiones', texto: 'Cobros por efectivo, Nequi o tarjeta, propinas y cierre de caja del día.', pronto: true },
    {
        icono: Users,
        titulo: 'Tu equipo y tus servicios',
        texto: 'Barberos con horarios, comisiones y precios por servicio, listos en minutos.',
        pronto: false,
    },
];

const planes = [
    {
        nombre: 'Básico',
        precio: 69900,
        para: '1 a 2 barberos',
        destacado: false,
        incluye: ['Agenda y cupos', 'Reserva por link y QR', 'Recordatorios por WhatsApp', 'Clientes y caja básica'],
    },
    {
        nombre: 'Pro',
        precio: 129900,
        para: '3 a 6 barberos',
        destacado: true,
        incluye: ['Todo lo del Básico', 'Asistente de WhatsApp con IA', 'Comisiones y lista de espera', 'Reportes y reactivación de clientes'],
    },
    {
        nombre: 'Premium',
        precio: 249900,
        para: 'Varias sedes',
        destacado: false,
        incluye: ['Todo lo del Pro', 'Multi-sede', 'Asistente de IA para el dueño', 'Recepcionista de voz para llamadas'],
    },
];

const horas = [
    { hora: '9:00', cliente: 'Andrés M.', servicio: 'Fade', estado: 'confirmada' },
    { hora: '9:45', cliente: 'Libre', servicio: '', estado: 'libre' },
    { hora: '10:30', cliente: 'Camilo R.', servicio: 'Corte + barba', estado: 'confirmada' },
    { hora: '11:30', cliente: 'Juan P.', servicio: 'Barba', estado: 'pendiente' },
];
</script>

<template>
    <Head title="Agenda y WhatsApp para barberías" />

    <div class="min-h-svh bg-background">
        <!-- Encabezado -->
        <header class="sticky top-0 z-40 border-b bg-background/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 md:px-6">
                <Link
                    :href="route('home')"
                    class="rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="Cupo, inicio"
                >
                    <BrandLogo />
                </Link>
                <nav class="hidden items-center gap-6 text-sm font-medium text-muted-foreground md:flex" aria-label="Secciones">
                    <a href="#funciones" class="transition-colors hover:text-foreground">Funciones</a>
                    <a href="#precios" class="transition-colors hover:text-foreground">Precios</a>
                </nav>
                <div class="flex items-center gap-2">
                    <Button v-if="autenticado" as-child><Link :href="route('dashboard')">Ir a mi panel</Link></Button>
                    <template v-else>
                        <Button variant="ghost" as-child class="hidden sm:inline-flex"><Link :href="route('login')">Iniciar sesión</Link></Button>
                        <Button as-child><Link :href="route('register')">Probar gratis</Link></Button>
                    </template>
                </div>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 md:px-6 lg:grid-cols-2 lg:py-24">
                <div class="space-y-6">
                    <Badge variant="brand"><Clock class="size-3.5" aria-hidden="true" /> 60 días de prueba gratis</Badge>
                    <h1 class="text-4xl font-extrabold leading-[1.05] sm:text-5xl lg:text-6xl">
                        Llena tu agenda.<br />
                        <span class="text-brand">Deja el WhatsApp manual.</span>
                    </h1>
                    <p class="max-w-xl text-lg text-muted-foreground">
                        Cupo organiza las citas de tu barbería, avisa a tus clientes por WhatsApp y te dice cuánto ganó cada barbero. Sin cuaderno y
                        sin cruces.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button size="lg" as-child><Link :href="route('register')">Crear mi barbería</Link></Button>
                        <Button size="lg" variant="outline" as-child><a href="#precios">Ver precios</a></Button>
                    </div>
                    <p class="text-sm text-muted-foreground">Sin tarjeta. Sin permanencia: cancelas cuando quieras.</p>
                </div>

                <!-- Ilustración de la agenda (decorativa) -->
                <div class="relative" aria-hidden="true">
                    <div class="bg-poste absolute -inset-4 rounded-3xl" />
                    <div class="relative rounded-2xl border bg-card p-5 shadow-xl">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="font-display text-lg font-bold">Hoy, sábado</p>
                                <p class="text-xs text-muted-foreground">Carlos · 4 de 7 cupos</p>
                            </div>
                            <span class="size-3 rounded-full bg-brand" />
                        </div>
                        <ul class="space-y-2">
                            <li
                                v-for="h in horas"
                                :key="h.hora"
                                class="flex items-center gap-3 rounded-lg border px-3 py-3"
                                :class="h.estado === 'libre' ? 'border-dashed bg-transparent' : 'bg-background'"
                            >
                                <span class="w-12 font-display text-sm font-semibold tabular-nums text-muted-foreground">{{ h.hora }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium" :class="{ 'text-muted-foreground': h.estado === 'libre' }">
                                        {{ h.cliente }}
                                    </p>
                                    <p v-if="h.servicio" class="truncate text-xs text-muted-foreground">{{ h.servicio }}</p>
                                </div>
                                <Badge v-if="h.estado === 'confirmada'" variant="success">Confirmada</Badge>
                                <Badge v-else-if="h.estado === 'pendiente'" variant="brand">Pendiente</Badge>
                                <Badge v-else variant="outline">Cupo libre</Badge>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Antes y con Cupo -->
            <section class="border-y bg-secondary/60">
                <div class="mx-auto max-w-6xl px-4 py-16 md:px-6">
                    <h2 class="max-w-2xl text-3xl font-bold md:text-4xl">Lo que hoy te quita tiempo, Cupo lo resuelve.</h2>
                    <div class="mt-10 grid gap-4 md:grid-cols-3">
                        <article v-for="d in dolores" :key="d.problema" class="rounded-xl border bg-card p-6">
                            <div class="mb-4 flex size-11 items-center justify-center rounded-lg bg-brand-soft text-brand">
                                <component :is="d.icono" class="size-5" aria-hidden="true" />
                            </div>
                            <h3 class="text-lg font-semibold">{{ d.problema }}</h3>
                            <p class="mt-2 text-sm text-muted-foreground">{{ d.solucion }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Funciones -->
            <section id="funciones" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 md:px-6 lg:py-24">
                <h2 class="max-w-2xl text-3xl font-bold md:text-4xl">Todo lo que necesita una barbería, en un solo lugar.</h2>
                <div class="mt-10 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="f in funciones" :key="f.titulo" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <component :is="f.icono" class="size-5" aria-hidden="true" />
                            </div>
                            <Badge v-if="f.pronto" variant="outline">Pronto</Badge>
                            <Badge v-else variant="success">Disponible</Badge>
                        </div>
                        <h3 class="text-lg font-semibold">{{ f.titulo }}</h3>
                        <p class="text-sm text-muted-foreground">{{ f.texto }}</p>
                    </article>
                </div>
            </section>

            <!-- Precios -->
            <section id="precios" class="scroll-mt-20 border-t bg-secondary/60">
                <div class="mx-auto max-w-6xl px-4 py-16 md:px-6 lg:py-24">
                    <div class="max-w-2xl space-y-2">
                        <h2 class="text-3xl font-bold md:text-4xl">Un precio que se paga con las citas que recuperas.</h2>
                        <p class="text-muted-foreground">Precios mensuales en pesos colombianos, más IVA. Los primeros 60 días son gratis.</p>
                    </div>
                    <div class="mt-10 grid gap-6 lg:grid-cols-3">
                        <article
                            v-for="plan in planes"
                            :key="plan.nombre"
                            class="relative flex flex-col rounded-2xl border bg-card p-6"
                            :class="plan.destacado ? 'border-2 border-brand shadow-lg' : ''"
                        >
                            <Badge v-if="plan.destacado" variant="brand" class="absolute -top-3 left-6 bg-brand text-brand-foreground"
                                >Más elegido</Badge
                            >
                            <h3 class="text-xl font-bold">{{ plan.nombre }}</h3>
                            <p class="text-sm text-muted-foreground">{{ plan.para }}</p>
                            <p class="mt-5 font-display text-4xl font-extrabold tracking-tight">
                                {{ formatoCOP(plan.precio) }}<span class="text-base font-medium text-muted-foreground"> /mes</span>
                            </p>
                            <ul class="mt-6 flex-1 space-y-3 text-sm">
                                <li v-for="item in plan.incluye" :key="item" class="flex items-start gap-2.5">
                                    <Check class="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
                                    {{ item }}
                                </li>
                            </ul>
                            <Button class="mt-8 w-full" :variant="plan.destacado ? 'default' : 'outline'" as-child>
                                <Link :href="route('register')">Empezar gratis</Link>
                            </Button>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Cierre -->
            <section class="bg-poste bg-[hsl(24_10%_10%)] text-white">
                <div class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-16 md:px-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-xl space-y-2">
                        <h2 class="text-3xl font-bold md:text-4xl">Empieza hoy. Tus clientes ya quieren reservar.</h2>
                        <p class="text-white/70">Configura tu barbería en minutos y pruébala 60 días sin pagar.</p>
                    </div>
                    <Button size="lg" variant="brand" class="bg-[hsl(46_97%_65%)] text-[hsl(24_10%_10%)] hover:bg-[hsl(46_97%_72%)]" as-child>
                        <Link :href="route('register')">Crear mi barbería</Link>
                    </Button>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-muted-foreground md:flex-row md:px-6"
            >
                <BrandLogo />
                <p>© {{ new Date().getFullYear() }} Cupo. Hecho para barberías de Colombia.</p>
            </div>
        </footer>
    </div>
</template>
