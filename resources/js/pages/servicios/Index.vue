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
import { formatoCOP, formatoDuracion } from '@/lib/format';
import { type BreadcrumbItem, type Servicio } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Clock, LoaderCircle, Pencil, Plus, Scissors, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    servicios: Servicio[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Servicios', href: '/servicios' }];

const abierto = ref(false);
const editando = ref<Servicio | null>(null);

const form = useForm({
    nombre: '',
    descripcion: '',
    duracion_minutos: 30,
    precio: 20000,
    activo: true,
});

const nuevo = () => {
    editando.value = null;
    form.reset();
    form.clearErrors();
    abierto.value = true;
};

const editar = (servicio: Servicio) => {
    editando.value = servicio;
    form.clearErrors();
    form.nombre = servicio.nombre;
    form.descripcion = servicio.descripcion ?? '';
    form.duracion_minutos = servicio.duracion_minutos;
    form.precio = servicio.precio;
    form.activo = servicio.activo;
    abierto.value = true;
};

const guardar = () => {
    const opciones = { preserveScroll: true, onSuccess: () => (abierto.value = false) };

    if (editando.value) {
        form.put(route('servicios.update', editando.value.id), opciones);
    } else {
        form.post(route('servicios.store'), opciones);
    }
};

const eliminar = (servicio: Servicio) => {
    if (!confirm(`¿Eliminar el servicio "${servicio.nombre}"? Las citas ya hechas conservan su registro.`)) return;

    router.delete(route('servicios.destroy', servicio.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Servicios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Servicios" description="Lo que ofrece tu barbería, con su duración y precio.">
                <Button @click="nuevo"><Plus /> Nuevo servicio</Button>
            </PageHeader>

            <EmptyState
                v-if="servicios.length === 0"
                :icon="Scissors"
                title="Aún no tienes servicios"
                description="Crea el primero, por ejemplo “Corte clásico”. Tus clientes lo elegirán al reservar."
            >
                <Button @click="nuevo"><Plus /> Crear mi primer servicio</Button>
            </EmptyState>

            <ul v-else class="divide-y overflow-hidden rounded-xl border bg-card">
                <li v-for="servicio in servicios" :key="servicio.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
                    <div class="min-w-0 flex-1 basis-48">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold">{{ servicio.nombre }}</p>
                            <Badge v-if="!servicio.activo" variant="muted">Oculto</Badge>
                        </div>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Clock class="size-3.5" aria-hidden="true" />
                            {{ formatoDuracion(servicio.duracion_minutos) }}
                            <template v-if="servicio.descripcion"> · {{ servicio.descripcion }}</template>
                        </p>
                    </div>
                    <p class="font-display text-lg font-bold tabular-nums">{{ formatoCOP(servicio.precio) }}</p>
                    <div class="flex gap-1">
                        <Button variant="ghost" size="icon" :aria-label="`Editar ${servicio.nombre}`" @click="editar(servicio)"><Pencil /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="hover:text-destructive"
                            :aria-label="`Eliminar ${servicio.nombre}`"
                            @click="eliminar(servicio)"
                            ><Trash2
                        /></Button>
                    </div>
                </li>
            </ul>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">{{ editando ? 'Editar servicio' : 'Nuevo servicio' }}</DialogTitle>
                    <DialogDescription>Los clientes verán este nombre y precio al reservar.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="guardar" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="nombre">Nombre</Label>
                        <Input id="nombre" v-model="form.nombre" required placeholder="Corte clásico" />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="duracion">Duración (minutos)</Label>
                            <Input id="duracion" type="number" min="5" max="480" step="5" v-model.number="form.duracion_minutos" required />
                            <InputError :message="form.errors.duracion_minutos" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="precio">Precio (COP)</Label>
                            <Input id="precio" type="number" min="0" step="500" v-model.number="form.precio" required />
                            <InputError :message="form.errors.precio" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="descripcion">Descripción (opcional)</Label>
                        <Input id="descripcion" v-model="form.descripcion" placeholder="Incluye lavado y peinado" />
                        <InputError :message="form.errors.descripcion" />
                    </div>

                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="checkbox" v-model="form.activo" class="size-5 accent-[hsl(var(--brand))]" />
                        Disponible para reservar
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
