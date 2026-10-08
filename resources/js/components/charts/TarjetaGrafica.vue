<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    titulo: string;
    descripcion?: string;
}>();
</script>

<template>
    <Card>
        <CardHeader class="space-y-1">
            <CardTitle class="text-xl">{{ titulo }}</CardTitle>
            <CardDescription v-if="descripcion">{{ descripcion }}</CardDescription>
        </CardHeader>
        <CardContent>
            <slot />
        </CardContent>
    </Card>
</template>
