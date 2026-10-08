const pesos = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

export const formatoCOP = (valor: number): string => pesos.format(valor);

export const formatoDuracion = (minutos: number): string => {
    if (minutos < 60) return `${minutos} min`;

    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    return resto === 0 ? `${horas} h` : `${horas} h ${resto} min`;
};

// Solo la primera letra en mayúscula ("viernes, 9 de octubre" → "Viernes, 9 de octubre"); `capitalize` de CSS pondría "De".
export const capitalizar = (texto: string): string => texto.charAt(0).toUpperCase() + texto.slice(1);

const compacto = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', notation: 'compact', maximumFractionDigits: 1 });

/** $ 1,2 M · $ 45 mil: para los ejes de las gráficas, donde el espacio es poco. */
export const formatoCOPCompacto = (valor: number): string => compacto.format(valor);

const decimal = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });

/** 12,4 (coma decimal, como se escribe en Colombia). */
export const formatoNumero = (valor: number): string => decimal.format(valor);

const aFecha = (fecha: string): Date => new Date(`${fecha}T12:00:00`);

/** "21 sept" desde "2026-09-21". */
export const fechaCorta = (fecha: string): string => aFecha(fecha).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' }).replace('.', '');

/** "Lunes, 21 de septiembre" desde "2026-09-21". */
export const fechaLarga = (fecha: string): string =>
    capitalizar(aFecha(fecha).toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }));
