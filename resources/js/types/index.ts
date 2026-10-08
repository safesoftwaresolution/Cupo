import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    barberia: { id: number; nombre: string; slug: string; maneja_comisiones: boolean } | null;
    flash: { success: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    rol: 'dueno' | 'recepcion' | 'barbero';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Servicio {
    id: number;
    nombre: string;
    descripcion: string | null;
    duracion_minutos: number;
    precio: number;
    activo: boolean;
}

export interface Barbero {
    id: number;
    nombre: string;
    telefono: string | null;
    comision_porcentaje: number;
    color: string | null;
    activo: boolean;
    servicios: number[];
    franjas: number;
}

export interface Franja {
    dia_semana: number;
    hora_inicio: string;
    hora_fin: string;
}

export interface PasoConfiguracion {
    clave: string;
    titulo: string;
    hecho: boolean;
    href: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface CitaPorCobrar {
    id: number;
    cliente: string;
    servicios: string;
    precio_total: number;
}

export interface MetodoPagoOpcion {
    valor: string;
    etiqueta: string;
}

export interface Indicador {
    valor: number;
    anterior: number;
    cambio: number | null;
    serie?: number[];
}

export interface Analitica {
    periodo: { desde: string; hasta: string; dias: number };
    kpis: {
        ventas: Indicador;
        atenciones: Indicador;
        ticket: Indicador;
        ocupacion: { valor: number | null; anterior: number | null; cambio: number | null };
        inasistencia: { valor: number | null; anterior: number | null; cambio: number | null; casos: number };
        barberia?: Indicador;
    };
    ventas_por_dia: { fecha: string; valor: number; anterior: number }[];
    demanda: { horas: number[]; dias: string[]; celdas: number[][]; maximo: number; total: number };
    servicios: { nombre: string; cantidad: number; ingresos: number }[];
    barberos: { nombre: string; atenciones: number; ventas: number; comision: number }[];
    metodos: { etiqueta: string; total: number; cantidad: number }[];
    origen: { etiqueta: string; cantidad: number }[];
    clientes: { nuevos: number; recurrentes: number; sin_registro: number };
    en_riesgo: { id: number; nombre: string; telefono: string; visitas: number; ultima: string; dias: number }[];
}
