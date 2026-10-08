<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Franja } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CircleAlert, Copy, LoaderCircle, Plus, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    barbero: { id: number; nombre: string };
    franjas: Franja[];
}>();

const dias = [
    { n: 1, nombre: 'Lunes' },
    { n: 2, nombre: 'Martes' },
    { n: 3, nombre: 'Miércoles' },
    { n: 4, nombre: 'Jueves' },
    { n: 5, nombre: 'Viernes' },
    { n: 6, nombre: 'Sábado' },
    { n: 7, nombre: 'Domingo' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Barberos', href: '/barberos' },
    { title: 'Horario', href: '#' },
];

const form = useForm({
    franjas: props.franjas.map((f) => ({ ...f })),
});

// Se conserva el índice real para que v-model apunte a la franja correcta del arreglo plano.
const delDia = (dia: number) => form.franjas.map((franja, indice) => ({ franja, indice })).filter((x) => x.franja.dia_semana === dia);

const agregar = (dia: number) => {
    const ultima = delDia(dia).at(-1)?.franja;

    form.franjas.push({
        dia_semana: dia,
        hora_inicio: ultima ? ultima.hora_fin : '09:00',
        hora_fin: ultima ? '19:00' : '12:00',
    });
};

const quitar = (indice: number) => form.franjas.splice(indice, 1);

const copiarATodos = (dia: number) => {
    const base = form.franjas.filter((f) => f.dia_semana === dia);

    form.franjas = dias.flatMap((d) => base.map((f) => ({ ...f, dia_semana: d.n })));
};

// Los errores de validación llegan con claves como "franjas.0.hora_fin"; se muestran juntos arriba.
const errores = computed(() => [...new Set(Object.values(form.errors))]);

const guardar = () => form.put(route('barberos.horario.update', props.barbero.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Horario de ${barbero.nombre}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <Button variant="ghost" size="sm" as-child class="-ml-3 mb-2">
                    <Link :href="route('barberos.index')"><ArrowLeft /> Barberos</Link>
                </Button>
                <PageHeader
                    :title="`Horario de ${barbero.nombre}`"
                    description="Agrega las franjas en que atiende cada día. Para el almuerzo usa dos franjas (por ejemplo 9:00 a 12:00 y 13:00 a 19:00). Un día sin franjas queda cerrado."
                />
            </div>

            <ul
                v-if="errores.length"
                role="alert"
                class="space-y-1 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                <li v-for="error in errores" :key="error" class="flex items-start gap-2">
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ error }}
                </li>
            </ul>

            <form @submit.prevent="guardar" class="grid max-w-2xl gap-3">
                <section v-for="dia in dias" :key="dia.n" class="rounded-xl border bg-card p-4" :aria-label="dia.nombre">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">{{ dia.nombre }}</h2>
                        <div class="flex gap-1">
                            <Button v-if="delDia(dia.n).length" type="button" variant="ghost" size="sm" @click="copiarATodos(dia.n)">
                                <Copy /> Copiar a todos
                            </Button>
                            <Button type="button" variant="outline" size="sm" @click="agregar(dia.n)"><Plus /> Franja</Button>
                        </div>
                    </div>

                    <p v-if="!delDia(dia.n).length" class="mt-2 text-sm text-muted-foreground">Cerrado</p>

                    <div v-for="{ franja, indice } in delDia(dia.n)" :key="indice" class="mt-3 flex items-center gap-2">
                        <Input type="time" v-model="franja.hora_inicio" required class="w-32" :aria-label="`${dia.nombre}: hora de inicio`" />
                        <span class="text-muted-foreground">a</span>
                        <Input type="time" v-model="franja.hora_fin" required class="w-32" :aria-label="`${dia.nombre}: hora de fin`" />
                        <Button type="button" variant="ghost" size="icon" aria-label="Quitar franja" @click="quitar(indice)"><X /></Button>
                    </div>
                </section>

                <div
                    class="sticky bottom-0 -mx-4 flex justify-end border-t bg-background/90 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0"
                >
                    <Button type="submit" :disabled="form.processing" class="w-full md:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar horario
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
