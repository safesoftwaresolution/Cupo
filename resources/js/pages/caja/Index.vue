<script setup lang="ts">
import CobroDialog from '@/components/CobroDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoCOP } from '@/lib/format';
import { type BreadcrumbItem, type CitaPorCobrar, type MetodoPagoOpcion, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Ban, ChevronLeft, ChevronRight, CircleAlert, LoaderCircle, Lock, LockOpen, Plus, Receipt, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Venta {
    id: number;
    hora: string;
    cliente: string | null;
    barbero: string;
    servicios: string;
    metodo: string;
    metodo_etiqueta: string;
    subtotal: number;
    descuento: number;
    propina: number;
    total: number;
    comision: number;
    anulada: boolean;
    motivo_anulacion: string | null;
}

const props = defineProps<{
    fecha: string;
    hoy: string;
    resumen: { ventas: number; total: number; servicios: number; propinas: number; comisiones: number; barberia: number; efectivo_esperado: number };
    porMetodo: { valor: string; etiqueta: string; cantidad: number; total: number }[];
    porBarbero: { nombre: string; ventas: number; servicios: number; comision: number; propinas: number; a_pagar: number }[];
    ventas: Venta[];
    porCobrar: (CitaPorCobrar & { hora: string; barbero: string })[];
    cierre: {
        id: number;
        efectivo_esperado: number;
        efectivo_contado: number;
        diferencia: number;
        notas: string | null;
        usuario: string | null;
    } | null;
    barberos: { id: number; nombre: string; servicios: number[] }[];
    servicios: { id: number; nombre: string; precio: number }[];
    metodos: MetodoPagoOpcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Caja', href: '/caja' }];

const page = usePage<SharedData>();
const esDueno = computed(() => page.props.auth.user.rol === 'dueno');
const errores = computed(() => page.props.errors as Record<string, string>);
const cerrada = computed(() => props.cierre !== null);

// --- Navegación entre días -------------------------------------------------------

const sumarDias = (fecha: string, dias: number) => {
    const d = new Date(`${fecha}T12:00:00`);
    d.setDate(d.getDate() + dias);

    return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
};

const irA = (fecha: string) => router.get(route('caja.index'), { fecha }, { preserveScroll: true, preserveState: true });

const tituloFecha = computed(() => {
    const texto = new Date(`${props.fecha}T12:00:00`).toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
    const capitalizado = texto.charAt(0).toUpperCase() + texto.slice(1);

    return props.fecha === props.hoy ? `Hoy · ${capitalizado}` : capitalizado;
});

const aPagarBarberos = computed(() => props.resumen.comisiones + props.resumen.propinas);

// --- Cobrar una cita ---------------------------------------------------------------------

const cobroAbierto = ref(false);
const citaACobrar = ref<CitaPorCobrar | null>(null);

const cobrar = (cita: CitaPorCobrar) => {
    citaACobrar.value = cita;
    cobroAbierto.value = true;
};

// --- Venta de mostrador -------------------------------------------------------------------

const ventaAbierta = ref(false);
const ventaForm = useForm({ barbero_id: null as number | null, servicios: [] as number[], metodo_pago: 'efectivo', descuento: 0, propina: 0 });

const serviciosDelBarbero = computed(() => {
    const barbero = props.barberos.find((b) => b.id === ventaForm.barbero_id);

    return barbero ? props.servicios.filter((s) => barbero.servicios.includes(s.id)) : [];
});

watch(
    () => ventaForm.barbero_id,
    () => (ventaForm.servicios = []),
);

const totalVenta = computed(() => {
    const subtotal = serviciosDelBarbero.value.filter((s) => ventaForm.servicios.includes(s.id)).reduce((suma, s) => suma + s.precio, 0);

    return Math.max(0, subtotal - (Number(ventaForm.descuento) || 0)) + (Number(ventaForm.propina) || 0);
});

const abrirVenta = () => {
    ventaForm.reset();
    ventaForm.clearErrors();
    ventaAbierta.value = true;
};

const guardarVenta = () => ventaForm.post(route('caja.ventas.store'), { preserveScroll: true, onSuccess: () => (ventaAbierta.value = false) });

// --- Anular ----------------------------------------------------------------------------------

const anularAbierto = ref(false);
const ventaAAnular = ref<Venta | null>(null);
const anularForm = useForm({ motivo: '' });

const pedirAnular = (venta: Venta) => {
    ventaAAnular.value = venta;
    anularForm.reset();
    anularForm.clearErrors();
    anularAbierto.value = true;
};

const anular = () => {
    if (!ventaAAnular.value) return;

    anularForm.patch(route('caja.ventas.anular', ventaAAnular.value.id), { preserveScroll: true, onSuccess: () => (anularAbierto.value = false) });
};

// --- Cierre de caja ------------------------------------------------------------------------------

const cierreAbierto = ref(false);
const cierreForm = useForm({ fecha: props.fecha, efectivo_contado: 0, notas: '' });

const diferencia = computed(() => (Number(cierreForm.efectivo_contado) || 0) - props.resumen.efectivo_esperado);

const abrirCierre = () => {
    cierreForm.reset();
    cierreForm.clearErrors();
    cierreForm.fecha = props.fecha;
    cierreForm.efectivo_contado = props.resumen.efectivo_esperado;
    cierreAbierto.value = true;
};

const cerrarCaja = () => cierreForm.post(route('caja.cierre.store'), { preserveScroll: true, onSuccess: () => (cierreAbierto.value = false) });

const reabrir = () => {
    if (!props.cierre || !confirm('¿Reabrir la caja de este día? Podrás cobrar y anular ventas de nuevo, y tendrás que cerrarla otra vez.')) return;

    router.delete(route('caja.cierre.destroy', props.cierre.id), { preserveScroll: true });
};

const textoDiferencia = (valor: number) =>
    valor === 0 ? 'Cuadra exacto' : valor > 0 ? `Sobran ${formatoCOP(valor)}` : `Faltan ${formatoCOP(Math.abs(valor))}`;
</script>

<template>
    <Head title="Caja" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Caja" :description="tituloFecha">
                <Button :disabled="cerrada" @click="abrirVenta"><Plus /> Nueva venta</Button>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="icon" aria-label="Día anterior" @click="irA(sumarDias(fecha, -1))"><ChevronLeft /></Button>
                <Button variant="outline" size="icon" aria-label="Día siguiente" @click="irA(sumarDias(fecha, 1))"><ChevronRight /></Button>
                <Button variant="outline" :disabled="fecha === hoy" @click="irA(hoy)">Hoy</Button>
                <Input type="date" :model-value="fecha" class="w-44" aria-label="Ir a una fecha" @update:model-value="(v) => v && irA(String(v))" />
            </div>

            <template v-for="(mensaje, clave) in { cobro: errores.cobro, anulacion: errores.anulacion, cierre: errores.cierre }" :key="clave">
                <p
                    v-if="mensaje"
                    role="alert"
                    class="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ mensaje }}
                </p>
            </template>

            <!-- Estado de la caja -->
            <div class="flex flex-wrap items-center gap-4 rounded-xl border p-4" :class="cerrada ? 'border-success/40 bg-success-soft' : 'bg-card'">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg"
                    :class="cerrada ? 'bg-success text-white' : 'bg-brand-soft text-brand'"
                >
                    <Lock v-if="cerrada" class="size-5" aria-hidden="true" />
                    <LockOpen v-else class="size-5" aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-display text-lg font-bold">{{ cerrada ? 'Caja cerrada' : 'Caja abierta' }}</p>
                    <p v-if="cierre" class="text-sm text-muted-foreground">
                        Efectivo contado {{ formatoCOP(cierre.efectivo_contado) }} de {{ formatoCOP(cierre.efectivo_esperado) }} esperados ·
                        <strong class="font-semibold" :class="cierre.diferencia === 0 ? 'text-success' : 'text-destructive'">{{
                            textoDiferencia(cierre.diferencia)
                        }}</strong>
                        <template v-if="cierre.usuario"> · cerró {{ cierre.usuario }}</template>
                    </p>
                    <p v-if="cierre?.notas" class="text-sm text-muted-foreground">“{{ cierre.notas }}”</p>
                    <p v-else-if="!cierre" class="text-sm text-muted-foreground">
                        Efectivo que debe haber: {{ formatoCOP(resumen.efectivo_esperado) }}
                    </p>
                </div>
                <Button v-if="!cerrada" variant="outline" @click="abrirCierre"><Lock /> Cerrar caja</Button>
                <Button v-else-if="esDueno" variant="outline" @click="reabrir"><LockOpen /> Reabrir</Button>
            </div>

            <!-- Resumen -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="space-y-1 pb-2">
                        <CardDescription>Total cobrado</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">{{ formatoCOP(resumen.total) }}</CardTitle>
                        <p class="text-xs text-muted-foreground">
                            {{ resumen.ventas }} {{ resumen.ventas === 1 ? 'venta' : 'ventas' }}, con propinas
                        </p>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="space-y-1 pb-2">
                        <CardDescription>A pagar a barberos</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">{{ formatoCOP(aPagarBarberos) }}</CardTitle>
                        <p class="text-xs text-muted-foreground">
                            Comisiones {{ formatoCOP(resumen.comisiones) }} + propinas {{ formatoCOP(resumen.propinas) }}
                        </p>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="space-y-1 pb-2">
                        <CardDescription>Para la barbería</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">{{ formatoCOP(resumen.barberia) }}</CardTitle>
                        <p class="text-xs text-muted-foreground">Servicios menos comisiones</p>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="space-y-1 pb-2">
                        <CardDescription>Efectivo esperado</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">{{ formatoCOP(resumen.efectivo_esperado) }}</CardTitle>
                        <p class="text-xs text-muted-foreground">Ventas en efectivo, con propinas</p>
                    </CardHeader>
                </Card>
            </div>

            <!-- Por cobrar -->
            <Card v-if="porCobrar.length && !cerrada">
                <CardHeader class="space-y-1">
                    <CardTitle class="text-xl">Por cobrar</CardTitle>
                    <CardDescription>Citas de este día que aún no tienen cobro.</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li v-for="cita in porCobrar" :key="cita.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                            <span class="w-14 font-display font-bold tabular-nums">{{ cita.hora }}</span>
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="truncate font-medium">{{ cita.cliente }}</p>
                                <p class="truncate text-sm text-muted-foreground">{{ cita.servicios }} · {{ cita.barbero }}</p>
                            </div>
                            <span class="font-display font-bold tabular-nums">{{ formatoCOP(cita.precio_total) }}</span>
                            <Button size="sm" @click="cobrar(cita)"><Wallet /> Cobrar</Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Por método de pago -->
                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-xl">Por método de pago</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y">
                            <li
                                v-for="m in porMetodo"
                                :key="m.valor"
                                class="flex items-center justify-between py-2.5 text-sm"
                                :class="{ 'text-muted-foreground': !m.cantidad }"
                            >
                                <span>
                                    {{ m.etiqueta }} <span class="text-xs text-muted-foreground">· {{ m.cantidad }}</span>
                                </span>
                                <span class="font-semibold tabular-nums">{{ formatoCOP(m.total) }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Por barbero -->
                <Card>
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-xl">Por barbero</CardTitle>
                        <CardDescription>Lo que se le debe a cada uno hoy: comisión más propinas.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p v-if="!porBarbero.length" class="py-2 text-sm text-muted-foreground">Aún no hay ventas este día.</p>
                        <ul v-else class="divide-y">
                            <li v-for="b in porBarbero" :key="b.nombre" class="flex items-center justify-between gap-4 py-2.5 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ b.nombre }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ b.ventas }} {{ b.ventas === 1 ? 'venta' : 'ventas' }} · comisión {{ formatoCOP(b.comision) }} · propinas
                                        {{ formatoCOP(b.propinas) }}
                                    </p>
                                </div>
                                <span class="font-display text-lg font-bold tabular-nums">{{ formatoCOP(b.a_pagar) }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Ventas del día -->
            <section class="space-y-3">
                <h2 class="text-xl font-bold">Ventas del día</h2>

                <EmptyState
                    v-if="!ventas.length"
                    :icon="Receipt"
                    title="Aún no hay ventas"
                    description="Cuando cobres una cita o registres una venta, aparecerá aquí."
                />

                <ul v-else class="divide-y overflow-hidden rounded-xl border bg-card">
                    <li
                        v-for="venta in ventas"
                        :key="venta.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4"
                        :class="{ 'bg-muted/50': venta.anulada }"
                    >
                        <span class="w-14 font-display font-bold tabular-nums" :class="{ 'text-muted-foreground': venta.anulada }">{{
                            venta.hora
                        }}</span>
                        <div class="min-w-0 flex-1 basis-56" :class="{ 'text-muted-foreground line-through': venta.anulada }">
                            <p class="truncate font-medium">{{ venta.cliente ?? 'Venta de mostrador' }}</p>
                            <p class="truncate text-sm text-muted-foreground">{{ venta.servicios }} · {{ venta.barbero }}</p>
                            <p v-if="venta.descuento || venta.propina" class="text-xs text-muted-foreground">
                                <template v-if="venta.descuento">Descuento {{ formatoCOP(venta.descuento) }}</template>
                                <template v-if="venta.descuento && venta.propina"> · </template>
                                <template v-if="venta.propina">Propina {{ formatoCOP(venta.propina) }}</template>
                            </p>
                        </div>
                        <Badge v-if="venta.anulada" variant="muted"><Ban class="size-3" aria-hidden="true" /> Anulada</Badge>
                        <Badge v-else variant="outline">{{ venta.metodo_etiqueta }}</Badge>
                        <span
                            class="w-28 text-right font-display text-lg font-bold tabular-nums"
                            :class="{ 'text-muted-foreground line-through': venta.anulada }"
                            >{{ formatoCOP(venta.total) }}</span
                        >
                        <Button
                            v-if="esDueno && !venta.anulada && !cerrada"
                            variant="ghost"
                            size="sm"
                            class="hover:text-destructive"
                            :aria-label="`Anular la venta de las ${venta.hora}`"
                            @click="pedirAnular(venta)"
                        >
                            Anular
                        </Button>
                        <p v-if="venta.anulada && venta.motivo_anulacion" class="basis-full pl-[4.5rem] text-xs text-muted-foreground">
                            Motivo: {{ venta.motivo_anulacion }}
                        </p>
                    </li>
                </ul>
            </section>
        </div>

        <CobroDialog v-model:open="cobroAbierto" :cita="citaACobrar" :metodos="metodos" />

        <!-- Venta de mostrador -->
        <Dialog v-model:open="ventaAbierta">
            <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">Nueva venta</DialogTitle>
                    <DialogDescription>Para quien llega sin cita y paga al momento.</DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="guardarVenta">
                    <div class="grid gap-2">
                        <Label for="venta-barbero">Barbero</Label>
                        <select
                            id="venta-barbero"
                            v-model="ventaForm.barbero_id"
                            class="flex h-11 w-full rounded-md border border-input bg-background px-3 text-base md:h-10 md:text-sm"
                            required
                        >
                            <option :value="null" disabled>Elige un barbero</option>
                            <option v-for="b in barberos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                        </select>
                        <InputError :message="ventaForm.errors.barbero_id" />
                    </div>

                    <fieldset v-if="ventaForm.barbero_id" class="grid gap-1">
                        <legend class="mb-1 text-sm font-medium">Servicios</legend>
                        <p v-if="!serviciosDelBarbero.length" class="text-sm text-muted-foreground">Este barbero no tiene servicios asignados.</p>
                        <label v-for="s in serviciosDelBarbero" :key="s.id" class="flex min-h-11 items-center gap-3 text-sm">
                            <input v-model="ventaForm.servicios" type="checkbox" :value="s.id" class="size-5 accent-[hsl(var(--brand))]" />
                            <span class="flex-1">{{ s.nombre }}</span>
                            <span class="tabular-nums text-muted-foreground">{{ formatoCOP(s.precio) }}</span>
                        </label>
                        <InputError :message="ventaForm.errors.servicios" />
                    </fieldset>

                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">¿Cómo pagó?</legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <label v-for="m in metodos" :key="m.valor" class="block">
                                <input v-model="ventaForm.metodo_pago" type="radio" name="metodo-venta" :value="m.valor" class="peer sr-only" />
                                <span
                                    class="flex h-11 items-center justify-center rounded-lg border bg-card text-sm font-semibold transition-colors duration-150 hover:bg-accent peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                                    >{{ m.etiqueta }}</span
                                >
                            </label>
                        </div>
                        <InputError :message="ventaForm.errors.metodo_pago" />
                    </fieldset>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="venta-descuento">Descuento</Label>
                            <Input id="venta-descuento" v-model.number="ventaForm.descuento" type="number" min="0" step="500" inputmode="numeric" />
                            <InputError :message="ventaForm.errors.descuento" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="venta-propina">Propina</Label>
                            <Input id="venta-propina" v-model.number="ventaForm.propina" type="number" min="0" step="500" inputmode="numeric" />
                            <InputError :message="ventaForm.errors.propina" />
                        </div>
                    </div>

                    <p class="flex items-baseline justify-between rounded-xl bg-muted p-4">
                        <span class="font-semibold">Total a cobrar</span>
                        <span class="font-display text-2xl font-bold tabular-nums">{{ formatoCOP(totalVenta) }}</span>
                    </p>
                    <InputError :message="errores.cobro" />

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="ventaAbierta = false">Cancelar</Button>
                        <Button type="submit" :disabled="ventaForm.processing || !ventaForm.servicios.length">
                            <LoaderCircle v-if="ventaForm.processing" class="animate-spin" />
                            Registrar venta
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Anular venta -->
        <Dialog v-model:open="anularAbierto">
            <DialogContent v-if="ventaAAnular" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">Anular venta de {{ formatoCOP(ventaAAnular.total) }}</DialogTitle>
                    <DialogDescription>
                        Dejará de contar en la caja y en las comisiones. Si venía de una cita, la cita podrá cobrarse de nuevo.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="anular">
                    <div class="grid gap-2">
                        <Label for="motivo">Motivo</Label>
                        <Input id="motivo" v-model="anularForm.motivo" required minlength="3" placeholder="Cobro duplicado, método equivocado…" />
                        <InputError :message="anularForm.errors.motivo" />
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="anularAbierto = false">Volver</Button>
                        <Button type="submit" variant="destructive" :disabled="anularForm.processing">
                            <LoaderCircle v-if="anularForm.processing" class="animate-spin" />
                            Anular venta
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Cierre de caja -->
        <Dialog v-model:open="cierreAbierto">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">Cerrar la caja</DialogTitle>
                    <DialogDescription
                        >Cuenta el efectivo que hay y compáralo con lo que debería haber. Después no se podrá cobrar ni anular ese
                        día.</DialogDescription
                    >
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="cerrarCaja">
                    <p class="flex items-baseline justify-between rounded-xl bg-muted p-4 text-sm">
                        <span class="text-muted-foreground">Efectivo que debería haber</span>
                        <span class="font-display text-xl font-bold tabular-nums">{{ formatoCOP(resumen.efectivo_esperado) }}</span>
                    </p>
                    <div class="grid gap-2">
                        <Label for="contado">Efectivo contado</Label>
                        <Input
                            id="contado"
                            v-model.number="cierreForm.efectivo_contado"
                            type="number"
                            min="0"
                            step="500"
                            inputmode="numeric"
                            required
                        />
                        <p class="text-sm font-semibold" :class="diferencia === 0 ? 'text-success' : 'text-destructive'" aria-live="polite">
                            {{ textoDiferencia(diferencia) }}
                        </p>
                        <InputError :message="cierreForm.errors.efectivo_contado" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="notas-cierre">Notas (opcional)</Label>
                        <Input id="notas-cierre" v-model="cierreForm.notas" placeholder="Si hay diferencia, explica por qué" />
                        <InputError :message="cierreForm.errors.notas" />
                    </div>
                    <InputError :message="errores.cierre" />
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="cierreAbierto = false">Cancelar</Button>
                        <Button type="submit" :disabled="cierreForm.processing">
                            <LoaderCircle v-if="cierreForm.processing" class="animate-spin" />
                            Cerrar caja
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
