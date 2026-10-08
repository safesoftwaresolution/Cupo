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
