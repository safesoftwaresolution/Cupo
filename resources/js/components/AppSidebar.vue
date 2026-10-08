<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, LayoutGrid, Scissors, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const mainNavItems = computed<NavItem[]>(() => {
    const rol = page.props.auth.user.rol;
    const items: NavItem[] = [{ title: 'Inicio', href: '/dashboard', icon: LayoutGrid }];

    // La agenda la manejan el dueño y la recepción; la configuración de la barbería es solo del dueño.
    if (rol === 'dueno' || rol === 'recepcion') {
        items.push({ title: 'Agenda', href: '/agenda', icon: CalendarDays });
    }

    if (rol === 'dueno') {
        items.push({ title: 'Barberos', href: '/barberos', icon: Users }, { title: 'Servicios', href: '/servicios', icon: Scissors });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
