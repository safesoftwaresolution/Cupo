<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();
const mensaje = ref<string | null>(null);
let temporizador: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash?.success,
    (nuevo) => {
        if (!nuevo) return;

        mensaje.value = nuevo;
        clearTimeout(temporizador);
        temporizador = setTimeout(() => (mensaje.value = null), 4000);
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="mensaje"
        role="status"
        class="mx-4 mt-4 flex items-center gap-2 rounded-lg border border-success/30 bg-success-soft px-4 py-3 text-sm font-medium text-success md:mx-6"
    >
        <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
        {{ mensaje }}
    </div>
</template>
