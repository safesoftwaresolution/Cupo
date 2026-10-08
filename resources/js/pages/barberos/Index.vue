<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type Barbero, type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarClock, CircleAlert, LoaderCircle, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    barberos: Barbero[];
    servicios: { id: number; nombre: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Barberos', href: '/barberos' }];

const page = usePage();
const manejaComisiones = computed(() => (page.props.barberia as { maneja_comisiones: boolean } | null)?.maneja_comisiones ?? true);
const errorGeneral = computed(() => (page.props.errors as Record<string, string>).barbero);

const abierto = ref(false);
const editando = ref<Barbero | null>(null);

const form = useForm({
    nombre: '',
    telefono: '',
    comision_porcentaje: 50,
    color: '#a16207',
    activo: true,
    servicios: [] as number[],
});

const iniciales = (nombre: string) =>
    nombre
        .split(' ')
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();

// Texto oscuro o claro según la luminosidad del color elegido, para que las iniciales siempre se lean.
const textoSobre = (hex: string | null) => {
    const limpio = (hex ?? '#78716c').replace('#', '');
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(limpio.slice(i, i + 2), 16));
    const luminancia = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    return luminancia > 0.6 ? '#1c1917' : '#ffffff';
};

const nuevo = () => {
    editando.value = null;
    form.reset();
    form.clearErrors();
    abierto.value = true;
};

const editar = (barbero: Barbero) => {
    editando.value = barbero;
    form.clearErrors();
    form.nombre = barbero.nombre;
    form.telefono = barbero.telefono ?? '';
    form.comision_porcentaje = barbero.comision_porcentaje;
    form.color = barbero.color ?? '#a16207';
    form.activo = barbero.activo;
    form.servicios = [...barbero.servicios];
    abierto.value = true;
};

const guardar = () => {
    const opciones = { preserveScroll: true, onSuccess: () => (abierto.value = false) };

    if (editando.value) {
        form.put(route('barberos.update', editando.value.id), opciones);
    } else {
        form.post(route('barberos.store'), opciones);
    }
};

const eliminar = (barbero: Barbero) => {
    if (!confirm(`¿Eliminar a ${barbero.nombre}? Su historial de citas y ventas se conserva.`)) return;

    router.delete(route('barberos.destroy', barbero.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Barberos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Barberos" description="Tu equipo y qué servicios hace cada uno.">
                <Button @click="nuevo"><Plus /> Nuevo barbero</Button>
            </PageHeader>

            <p
                v-if="errorGeneral"
                role="alert"
                class="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ errorGeneral }}
            </p>

            <EmptyState
                v-if="barberos.length === 0"
                :icon="Users"
                title="Aún no tienes barberos"
                description="Agrega al primero para poder definir su horario y asignarle servicios."
            >
                <Button @click="nuevo"><Plus /> Agregar mi primer barbero</Button>
            </EmptyState>

            <ul v-else class="divide-y overflow-hidden rounded-xl border bg-card">
                <li v-for="barbero in barberos" :key="barbero.id" class="flex flex-wrap items-center gap-x-4 gap-y-3 p-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        :style="{ backgroundColor: barbero.color ?? '#78716c', color: textoSobre(barbero.color) }"
                        aria-hidden="true"
                        >{{ iniciales(barbero.nombre) }}</span
                    >
                    <div class="min-w-0 flex-1 basis-56">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold">{{ barbero.nombre }}</p>
                            <Badge v-if="!barbero.activo" variant="muted">Inactivo</Badge>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <Badge v-if="manejaComisiones" variant="muted">Comisión {{ barbero.comision_porcentaje }}%</Badge>
                            <Badge variant="muted"
                                >{{ barbero.servicios.length }} {{ barbero.servicios.length === 1 ? 'servicio' : 'servicios' }}</Badge
                            >
                            <Badge v-if="barbero.franjas === 0" variant="brand">Sin horario</Badge>
                            <Badge v-else variant="success"
                                >{{ barbero.franjas }} {{ barbero.franjas === 1 ? 'franja' : 'franjas' }} por semana</Badge
                            >
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="route('barberos.horario.edit', barbero.id)"><CalendarClock /> Horario</Link>
                        </Button>
                        <Button variant="ghost" size="icon" :aria-label="`Editar ${barbero.nombre}`" @click="editar(barbero)"><Pencil /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="hover:text-destructive"
                            :aria-label="`Eliminar ${barbero.nombre}`"
                            @click="eliminar(barbero)"
                            ><Trash2
                        /></Button>
                    </div>
                </li>
            </ul>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">{{ editando ? 'Editar barbero' : 'Nuevo barbero' }}</DialogTitle>
                    <DialogDescription>{{
                        manejaComisiones ? 'La comisión se aplica al valor de cada servicio que cobre.' : 'Su nombre, sus servicios y su horario.'
                    }}</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="guardar" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="nombre">Nombre</Label>
                        <Input id="nombre" v-model="form.nombre" required placeholder="Carlos Gómez" />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2" :class="{ 'col-span-2': !manejaComisiones }">
                            <Label for="telefono">Celular</Label>
                            <Input id="telefono" type="tel" v-model="form.telefono" placeholder="300 123 4567" />
                            <InputError :message="form.errors.telefono" />
                        </div>
                        <div v-if="manejaComisiones" class="grid gap-2">
                            <Label for="comision">Comisión (%)</Label>
                            <Input id="comision" type="number" min="0" max="100" step="0.5" v-model.number="form.comision_porcentaje" required />
                            <InputError :message="form.errors.comision_porcentaje" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="color">Color en la agenda</Label>
                        <input
                            id="color"
                            type="color"
                            v-model="form.color"
                            class="h-11 w-20 cursor-pointer rounded-md border border-input bg-transparent p-1"
                        />
                        <InputError :message="form.errors.color" />
                    </div>

                    <fieldset class="grid gap-1">
                        <legend class="mb-1 text-sm font-medium">Servicios que realiza</legend>
                        <p v-if="servicios.length === 0" class="text-sm text-muted-foreground">Primero crea tus servicios en la sección Servicios.</p>
                        <label v-for="servicio in servicios" :key="servicio.id" class="flex min-h-11 items-center gap-3 text-sm">
                            <input type="checkbox" :value="servicio.id" v-model="form.servicios" class="size-5 accent-[hsl(var(--brand))]" />
                            {{ servicio.nombre }}
                        </label>
                        <InputError :message="form.errors['servicios.0'] ?? form.errors.servicios" />
                    </fieldset>

                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="checkbox" v-model="form.activo" class="size-5 accent-[hsl(var(--brand))]" />
                        Activo (aparece en la agenda)
                    </label>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="abierto = false">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
