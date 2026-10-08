<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
import { MapPin } from 'lucide-vue-next';

defineProps<{
    barberia: { nombre: string; ciudad: string | null; direccion: string | null };
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <header class="bg-poste bg-[hsl(24_10%_10%)] text-white">
            <div class="mx-auto flex max-w-2xl flex-col gap-1 px-4 py-8 md:py-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-[hsl(46_97%_65%)]">Reserva tu cupo</p>
                <h1 class="text-3xl font-extrabold md:text-4xl">{{ barberia.nombre }}</h1>
                <p v-if="barberia.direccion || barberia.ciudad" class="flex items-center gap-1.5 text-sm text-white/70">
                    <MapPin class="size-4 shrink-0" aria-hidden="true" />
                    {{ [barberia.direccion, barberia.ciudad].filter(Boolean).join(', ') }}
                </p>
            </div>
        </header>

        <main class="mx-auto w-full max-w-2xl flex-1 px-4 py-6 md:py-8">
            <slot />
        </main>

        <footer class="border-t py-6">
            <Link
                :href="route('home')"
                class="mx-auto flex w-fit items-center gap-2 text-xs text-muted-foreground transition-colors hover:text-foreground"
            >
                <AppLogoIcon class="size-5" />
                Reservas con Cupo
            </Link>
        </footer>
    </div>
</template>
