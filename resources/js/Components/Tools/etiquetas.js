// Etiquetas y estilos de presentación para herramientas. Solo formatean; no calculan nada.
export const ETIQUETAS_ESTADO = {
    disponible: 'Disponible',
    en_uso: 'En uso',
    prestada: 'Prestada',
    mantenimiento: 'Mantenimiento',
    'dañada': 'Dañada',
    baja: 'Baja',
};

export const CLASES_ESTADO = {
    disponible: 'bg-green-100 text-green-700',
    en_uso: 'bg-blue-100 text-blue-700',
    prestada: 'bg-amber-100 text-amber-700',
    mantenimiento: 'bg-orange-100 text-orange-700',
    'dañada': 'bg-red-100 text-red-700',
    baja: 'bg-gray-200 text-gray-600',
};

export const ETIQUETAS_CONDICION = {
    excelente: 'Excelente',
    buena: 'Buena',
    regular: 'Regular',
    mala: 'Mala',
};

export function dinero(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(valor));
}
