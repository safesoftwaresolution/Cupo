<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoCOP } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Download, Percent } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Fila {
    barbero: string;
    ventas: number;
    neto: number;
    comision: number;
    propinas: number;
    a_pagar: number;
    barberia: number;
}

const props = defineProps<{
    desde: string;
    hasta: string;
    filas: Fila[];
    totales: Omit<Fila, 'barbero'>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Comisiones', href: '/comisiones' }];

const desde = ref(props.desde);
const hasta = ref(props.hasta);

const aTexto = (d: Date) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');

// Atajos para los periodos de pago más comunes.
const atajos = [
    {
        nombre: 'Esta semana',
        rango: () => {
            const hoy = new Date();
            const lunes = new Date(hoy);
            lunes.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));

            return [lunes, hoy];
        },
    },
    {
        nombre: 'Esta quincena',
        rango: () => {
            const hoy = new Date();

            return [new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() <= 15 ? 1 : 16), hoy];
        },
    },
    {
        nombre: 'Este mes',
        rango: () => {
            const hoy = new Date();

            return [new Date(hoy.getFullYear(), hoy.getMonth(), 1), hoy];
        },
    },
    {
        nombre: 'Mes pasado',
        rango: () => {
            const hoy = new Date();

            return [new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1), new Date(hoy.getFullYear(), hoy.getMonth(), 0)];
        },
    },
];

const consultar = (d: string, h: string) => {
    desde.value = d;
    hasta.value = h;
    router.get(route('comisiones.index'), { desde: d, hasta: h }, { preserveScroll: true, preserveState: true });
};

const aplicarAtajo = (atajo: (typeof atajos)[number]) => {
    const [d, h] = atajo.rango();
    consultar(aTexto(d), aTexto(h));
};

const urlCsv = computed(() => route('comisiones.exportar', { desde: props.desde, hasta: props.hasta }));

const legible = (fecha: string) => new Date(`${fecha}T12:00:00`).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Comisiones" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Comisiones" :description="`Liquidación del ${legible(desde)} al ${legible(hasta)}`">
                <Button variant="outline" as-child>
                    <a :href="urlCsv"><Download /> Descargar CSV</a>
                </Button>
            </PageHeader>

            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5">
                    <Label for="desde">Desde</Label>
                    <Input id="desde" v-model="desde" type="date" class="w-44" @change="consultar(desde, hasta)" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="hasta">Hasta</Label>
                    <Input id="hasta" v-model="hasta" type="date" class="w-44" @change="consultar(desde, hasta)" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-for="a in atajos" :key="a.nombre" variant="outline" size="sm" @click="aplicarAtajo(a)">{{ a.nombre }}</Button>
                </div>
            </div>

            <EmptyState
                v-if="!filas.length"
                :icon="Percent"
                title="No hay ventas en este periodo"
                description="Cuando cobres citas o registres ventas dentro de estas fechas, verás aquí cuánto le corresponde a cada barbero."
            />

            <template v-else>
                <div class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full min-w-[44rem] text-sm">
                        <caption class="sr-only">
                            Comisiones por barbero
                        </caption>
                        <thead class="border-b bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-semibold">Barbero</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Ventas</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Servicios</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Comisión</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Propinas</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-foreground">A pagar</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Barbería</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y tabular-nums">
                            <tr v-for="f in filas" :key="f.barbero">
                                <th scope="row" class="px-4 py-3 text-left font-medium">{{ f.barbero }}</th>
                                <td class="px-4 py-3 text-right">{{ f.ventas }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(f.neto) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(f.comision) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(f.propinas) }}</td>
                                <td class="px-4 py-3 text-right font-display text-base font-bold">{{ formatoCOP(f.a_pagar) }}</td>
                                <td class="px-4 py-3 text-right text-muted-foreground">{{ formatoCOP(f.barberia) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 bg-muted/60 font-semibold tabular-nums">
                            <tr>
                                <th scope="row" class="px-4 py-3 text-left">Total</th>
                                <td class="px-4 py-3 text-right">{{ totales.ventas }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(totales.neto) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(totales.comision) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(totales.propinas) }}</td>
                                <td class="px-4 py-3 text-right font-display text-base font-bold">{{ formatoCOP(totales.a_pagar) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatoCOP(totales.barberia) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="max-w-2xl text-sm text-muted-foreground">
                    <strong class="text-foreground">A pagar</strong> es la comisión más las propinas de cada barbero. La comisión se calcula sobre el
                    valor de los servicios después del descuento y con el porcentaje que tenía el barbero al momento de cobrar. Las ventas anuladas no
                    cuentan.
                </p>
            </template>
        </div>
    </AppLayout>
</template>
