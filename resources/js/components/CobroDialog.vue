<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatoCOP } from '@/lib/format';
import { type CitaPorCobrar, type MetodoPagoOpcion } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    cita: CitaPorCobrar | null;
    metodos: MetodoPagoOpcion[];
}>();

const abierto = defineModel<boolean>('open', { required: true });

const form = useForm({
    metodo_pago: 'efectivo',
    descuento: 0,
    propina: 0,
});

const propinasRapidas = [0, 2000, 5000, 10000];

// Cada vez que se abre para otra cita se parte de cero.
watch(
    () => [abierto.value, props.cita?.id],
    () => {
        if (abierto.value) {
            form.reset();
            form.clearErrors();
        }
    },
);

const subtotal = computed(() => props.cita?.precio_total ?? 0);
const descuento = computed(() => Math.max(0, Number(form.descuento) || 0));
const propina = computed(() => Math.max(0, Number(form.propina) || 0));
const total = computed(() => Math.max(0, subtotal.value - descuento.value) + propina.value);
const descuentoInvalido = computed(() => descuento.value > subtotal.value);

const cobrar = () => {
    if (!props.cita || descuentoInvalido.value) return;

    form.post(route('caja.cobrar', props.cita.id), {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    });
};
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent v-if="cita" class="max-h-[90svh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-display text-xl">Cobrar a {{ cita.cliente }}</DialogTitle>
                <DialogDescription>{{ cita.servicios }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="cobrar">
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">¿Cómo pagó?</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label v-for="m in metodos" :key="m.valor" class="block">
                            <input v-model="form.metodo_pago" type="radio" name="metodo" :value="m.valor" class="peer sr-only" />
                            <span
                                class="flex h-11 items-center justify-center rounded-lg border bg-card text-sm font-semibold transition-colors duration-150 hover:bg-accent peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-2 peer-focus-visible:ring-ring"
                            >
                                {{ m.etiqueta }}
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.metodo_pago" />
                </fieldset>

                <div class="grid gap-2">
                    <Label for="descuento">Descuento (opcional)</Label>
                    <Input id="descuento" v-model.number="form.descuento" type="number" min="0" step="500" inputmode="numeric" />
                    <p v-if="descuentoInvalido" class="text-sm text-destructive">El descuento no puede ser mayor que el valor de los servicios.</p>
                    <InputError :message="form.errors.descuento" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Propina (opcional)</legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="p in propinasRapidas"
                            :key="p"
                            type="button"
                            :aria-pressed="propina === p"
                            class="h-10 rounded-lg border bg-card px-3 text-sm font-semibold tabular-nums transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="{ '!border-primary !bg-primary !text-primary-foreground': propina === p }"
                            @click="form.propina = p"
                        >
                            {{ p === 0 ? 'Sin propina' : formatoCOP(p) }}
                        </button>
                    </div>
                    <Input v-model.number="form.propina" type="number" min="0" step="500" inputmode="numeric" aria-label="Otro valor de propina" />
                    <InputError :message="form.errors.propina" />
                </fieldset>

                <dl class="space-y-1.5 rounded-xl bg-muted p-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Servicios</dt>
                        <dd class="tabular-nums">{{ formatoCOP(subtotal) }}</dd>
                    </div>
                    <div v-if="descuento" class="flex justify-between">
                        <dt class="text-muted-foreground">Descuento</dt>
                        <dd class="tabular-nums">− {{ formatoCOP(descuento) }}</dd>
                    </div>
                    <div v-if="propina" class="flex justify-between">
                        <dt class="text-muted-foreground">Propina</dt>
                        <dd class="tabular-nums">{{ formatoCOP(propina) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between border-t pt-2">
                        <dt class="font-semibold">Total a cobrar</dt>
                        <dd class="font-display text-2xl font-bold tabular-nums">{{ formatoCOP(total) }}</dd>
                    </div>
                </dl>

                <InputError :message="form.errors.cobro" />

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing || descuentoInvalido">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Registrar cobro
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
