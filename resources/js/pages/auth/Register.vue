<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    barberia: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Crea tu cuenta" description="Registra tu barbería y empieza a recibir citas. 60 días de prueba gratis.">
        <Head title="Crear cuenta" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="barberia">Nombre de tu barbería</Label>
                    <Input id="barberia" type="text" required autofocus tabindex="1" v-model="form.barberia" placeholder="Barbería El Corte" />
                    <InputError :message="form.errors.barberia" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Tu nombre</Label>
                    <Input id="name" type="text" required tabindex="2" autocomplete="name" v-model="form.name" placeholder="Nombre completo" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input id="email" type="email" required tabindex="3" autocomplete="email" v-model="form.email" placeholder="correo@ejemplo.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Contraseña"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        tabindex="5"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Repite la contraseña"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink :href="route('login')" class="underline underline-offset-4" tabindex="7">Inicia sesión</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
