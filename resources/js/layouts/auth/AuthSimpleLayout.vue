<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import { Link } from '@inertiajs/vue3';
import { CalendarCheck, MessageCircle, Wallet } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const beneficios = [
    { icono: CalendarCheck, texto: 'Agenda con cupos que nunca se cruzan' },
    { icono: MessageCircle, texto: 'Recordatorios por WhatsApp que bajan las faltas' },
    { icono: Wallet, texto: 'Caja y comisiones claras, sin cuaderno' },
];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <!-- Panel de marca: solo en pantallas grandes -->
        <aside class="bg-poste relative hidden flex-col justify-between overflow-hidden bg-[hsl(24_10%_10%)] p-12 text-white lg:flex">
            <div class="bg-[hsl(46_97%_65%)]/10 pointer-events-none absolute -right-24 -top-24 size-96 rounded-full blur-3xl" aria-hidden="true" />

            <Link
                :href="route('home')"
                class="relative w-fit rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(46_97%_65%)]"
            >
                <BrandLogo tone="sobre-oscuro" size="lg" />
            </Link>

            <div class="relative space-y-8">
                <h2 class="font-display text-4xl font-bold leading-tight tracking-tight xl:text-5xl">
                    Tu agenda llena.<br />
                    <span class="text-[hsl(46_97%_65%)]">Tu WhatsApp en paz.</span>
                </h2>
                <ul class="space-y-4">
                    <li v-for="b in beneficios" :key="b.texto" class="flex items-center gap-3 text-base text-white/80">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10">
                            <component :is="b.icono" class="size-5 text-[hsl(46_97%_65%)]" aria-hidden="true" />
                        </span>
                        {{ b.texto }}
                    </li>
                </ul>
            </div>

            <p class="relative text-sm text-white/50">Hecho para barberías de Colombia.</p>
        </aside>

        <!-- Formulario -->
        <main class="flex flex-col items-center justify-center bg-background px-6 py-10 md:px-10">
            <div class="w-full max-w-sm">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col gap-6">
                        <Link :href="route('home')" class="w-fit rounded-md lg:hidden">
                            <BrandLogo />
                        </Link>
                        <div class="space-y-2">
                            <h1 class="text-3xl font-bold">{{ title }}</h1>
                            <p class="text-sm text-muted-foreground">{{ description }}</p>
                        </div>
                    </div>
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
