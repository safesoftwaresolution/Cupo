<script setup lang="ts">
import { useId } from 'vue';

defineProps<{
    label: string;
    description?: string;
}>();

const activo = defineModel<boolean>({ required: true });
const id = useId();
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 space-y-0.5">
            <label :for="id" class="cursor-pointer text-sm font-semibold">{{ label }}</label>
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <button
            :id="id"
            type="button"
            role="switch"
            :aria-checked="activo"
            class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :class="activo ? 'bg-brand' : 'bg-input'"
            @click="activo = !activo"
        >
            <span
                class="pointer-events-none block size-6 rounded-full bg-white shadow transition-transform duration-200"
                :class="activo ? 'translate-x-5' : 'translate-x-0'"
                aria-hidden="true"
            />
            <span class="sr-only">{{ activo ? 'Activado' : 'Desactivado' }}</span>
        </button>
    </div>
</template>
