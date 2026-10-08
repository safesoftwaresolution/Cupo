<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    ajustes: {
        nombre: string;
        telefono: string | null;
        direccion: string | null;
        ciudad: string | null;
        slug: string;
        intervalo_agenda: number;
        anticipacion_minima: number;
        dias_reserva: number;
        maneja_comisiones: boolean;
        recordatorios_activos: boolean;
        recordatorio_horas: number;
    };
    opciones: { intervalos: number[]; anticipaciones: number[]; horasRecordatorio: number[] };
    urlReserva: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mi barbería', href: '/ajustes/barberia' }];

const form = useForm({
    nombre: props.ajustes.nombre,
    telefono: props.ajustes.telefono ?? '',
    direccion: props.ajustes.direccion ?? '',
    ciudad: props.ajustes.ciudad ?? '',
    intervalo_agenda: props.ajustes.intervalo_agenda,
    anticipacion_minima: props.ajustes.anticipacion_minima,
    dias_reserva: props.ajustes.dias_reserva,
    maneja_comisiones: props.ajustes.maneja_comisiones,
    recordatorios_activos: props.ajustes.recordatorios_activos,
    recordatorio_horas: props.ajustes.recordatorio_horas,
});

const etiquetaAnticipacion = (minutos: number) => {
    if (minutos === 0) return 'Sin anticipación';
    if (minutos < 60) return `${minutos} minutos`;
    if (minutos === 60) return '1 hora';
    if (minutos < 1440) return `${minutos / 60} horas`;

    return '1 día';
};

const etiquetaRecordatorio = (horas: number) => (horas === 24 ? '1 día antes' : horas === 48 ? '2 días antes' : `${horas} horas antes`);

const guardar = () => form.put(route('ajustes.barberia.update'), { preserveScroll: true });

const claseSelect = 'flex h-11 w-full rounded-md border border-input bg-background px-3 text-base md:h-10 md:text-sm';
</script>

<template>
    <Head title="Mi barbería" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-1 flex-col gap-6 p-4 md:p-6" @submit.prevent="guardar">
            <PageHeader title="Mi barbería" description="Cómo funciona tu negocio dentro de Cupo. Los cambios aplican de inmediato." />

            <div class="grid max-w-3xl gap-6">
                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-xl">Datos del negocio</CardTitle>
                        <CardDescription>Se muestran a tus clientes al reservar.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="nombre">Nombre</Label>
                            <Input id="nombre" v-model="form.nombre" required />
                            <InputError :message="form.errors.nombre" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="telefono">Teléfono</Label>
                            <Input id="telefono" v-model="form.telefono" type="tel" inputmode="tel" placeholder="300 123 4567" />
                            <InputError :message="form.errors.telefono" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ciudad">Ciudad</Label>
                            <Input id="ciudad" v-model="form.ciudad" placeholder="Bogotá" />
                            <InputError :message="form.errors.ciudad" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="direccion">Dirección</Label>
                            <Input id="direccion" v-model="form.direccion" placeholder="Calle 10 # 5-20" />
                            <InputError :message="form.errors.direccion" />
                        </div>
                        <p class="text-sm text-muted-foreground sm:col-span-2">
                            Tu enlace de reservas: <code class="rounded bg-muted px-1.5 py-0.5">{{ urlReserva }}</code>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-xl">Reservas</CardTitle>
                        <CardDescription>Cómo reservan tus clientes desde el enlace público.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="intervalo">Cada cuánto hay cupo</Label>
                            <select id="intervalo" v-model.number="form.intervalo_agenda" :class="claseSelect">
                                <option v-for="i in opciones.intervalos" :key="i" :value="i">{{ i }} minutos</option>
                            </select>
                            <InputError :message="form.errors.intervalo_agenda" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="anticipacion">Anticipación mínima</Label>
                            <select id="anticipacion" v-model.number="form.anticipacion_minima" :class="claseSelect">
                                <option v-for="a in opciones.anticipaciones" :key="a" :value="a">{{ etiquetaAnticipacion(a) }}</option>
                            </select>
                            <InputError :message="form.errors.anticipacion_minima" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="dias">Reservar hasta (días)</Label>
                            <Input id="dias" v-model.number="form.dias_reserva" type="number" min="1" max="90" inputmode="numeric" />
                            <InputError :message="form.errors.dias_reserva" />
                        </div>
                        <p class="text-sm text-muted-foreground sm:col-span-3">
                            La anticipación evita reservas de último minuto. El personal siempre puede agendar “para ya” desde la agenda.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-xl">Pago a los barberos</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <SwitchField
                            v-model="form.maneja_comisiones"
                            label="Mis barberos ganan por comisión"
                            description="Activado: cada barbero tiene un porcentaje y Cupo calcula lo que se le debe. Desactivado: todo el ingreso es de la barbería y se ocultan las comisiones."
                        />
                        <p v-if="!form.maneja_comisiones" class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                            Las ventas que ya registraste conservan su comisión. Las nuevas se registran sin comisión.
                        </p>
                        <InputError :message="form.errors.maneja_comisiones" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-xl">Recordatorios por WhatsApp</CardTitle>
                        <CardDescription>Bajan las faltas: el cliente confirma o cancela con un toque.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <SwitchField
                            v-model="form.recordatorios_activos"
                            label="Enviar recordatorios"
                            description="Solo a clientes que autorizaron recibir mensajes al reservar."
                        />
                        <div v-if="form.recordatorios_activos" class="grid max-w-xs gap-2">
                            <Label for="horas">Cuándo avisar</Label>
                            <select id="horas" v-model.number="form.recordatorio_horas" :class="claseSelect">
                                <option v-for="h in opciones.horasRecordatorio" :key="h" :value="h">{{ etiquetaRecordatorio(h) }}</option>
                            </select>
                            <InputError :message="form.errors.recordatorio_horas" />
                        </div>
                    </CardContent>
                </Card>

                <div
                    class="sticky bottom-0 -mx-4 flex justify-end border-t bg-background/90 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0"
                >
                    <Button type="submit" :disabled="form.processing || !form.isDirty" class="w-full md:w-auto">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Guardar cambios
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
