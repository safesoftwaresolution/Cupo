<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { capitalizar } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { FlaskConical, MessageCircle } from 'lucide-vue-next';

interface Mensaje {
    id: number;
    cliente: string;
    telefono: string;
    cita: string | null;
    creado: string;
    estado: string;
    estado_etiqueta: string;
    respuesta: string | null;
    error: string | null;
}

defineProps<{
    mensajes: Mensaje[];
    modoPrueba: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mensajes', href: '/mensajes' }];

const variante = (estado: string) =>
    ['entregado', 'leido'].includes(estado) ? 'success' : estado === 'fallido' ? 'muted' : estado === 'omitido' ? 'outline' : 'brand';

const cuando = (iso: string) =>
    capitalizar(new Date(iso).toLocaleString('es-CO', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }));
</script>

<template>
    <Head title="Mensajes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Mensajes" description="Los recordatorios de WhatsApp que envió Cupo y qué pasó con cada uno." />

            <p v-if="modoPrueba" class="flex items-start gap-3 rounded-xl border border-brand/40 bg-brand-soft p-4 text-sm">
                <FlaskConical class="mt-0.5 size-5 shrink-0 text-brand" aria-hidden="true" />
                <span>
                    <strong>Modo de pruebas.</strong> Los mensajes se registran pero no llegan a los clientes. Para enviarlos de verdad hay que
                    conectar la cuenta de WhatsApp Business (pasos en <code class="rounded bg-background/60 px-1">docs/whatsapp.md</code>).
                </span>
            </p>

            <EmptyState
                v-if="!mensajes.length"
                :icon="MessageCircle"
                title="Aún no hay mensajes"
                description="Cuando una cita esté por llegar y el cliente haya autorizado los mensajes, el recordatorio aparecerá aquí."
            />

            <ul v-else class="divide-y overflow-hidden rounded-xl border bg-card">
                <li v-for="m in mensajes" :key="m.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
                    <div class="min-w-0 flex-1 basis-56">
                        <p class="truncate font-medium">{{ m.cliente }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            <template v-if="m.cita">Cita: {{ cuando(m.cita) }} · </template>Recordatorio enviado {{ cuando(m.creado) }}
                        </p>
                        <p v-if="m.error" class="mt-1 text-xs text-destructive">{{ m.error }}</p>
                    </div>
                    <Badge v-if="m.respuesta === 'confirmo'" variant="success">Confirmó</Badge>
                    <Badge v-else-if="m.respuesta === 'cancelo'" variant="muted">Canceló</Badge>
                    <Badge :variant="variante(m.estado)">{{ m.estado_etiqueta }}</Badge>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
