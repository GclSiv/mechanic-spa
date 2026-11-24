// Presentación de estados de herramientas: claves de traducción y variantes del Design System.
// Solo formatea; el estado real lo define el backend.
export const CLAVE_ESTADO = {
    disponible: 'estado_disponible',
    en_uso: 'estado_en_uso',
    prestada: 'estado_prestada',
    mantenimiento: 'estado_mantenimiento',
    'dañada': 'estado_danada',
    baja: 'estado_baja',
};

export const VARIANTE_ESTADO = {
    disponible: 'success',
    en_uso: 'info',
    prestada: 'warning',
    mantenimiento: 'warning',
    'dañada': 'danger',
    baja: 'neutral',
};

export const CLAVE_CONDICION = {
    excelente: 'condicion_excelente',
    buena: 'condicion_buena',
    regular: 'condicion_regular',
    mala: 'condicion_mala',
};
