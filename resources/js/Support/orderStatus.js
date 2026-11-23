// Espejo de App\Models\RepairOrderStatus::OPERATIVOS_MECANICO / CERRADOS.
// La autoridad real es el servidor (updateStatus); esto solo oculta opciones que no se pueden usar.
export const ESTADOS_OPERATIVOS_MECANICO = ['diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];
export const ESTADOS_CERRADOS = ['entregado', 'rechazado'];

/** Estados que el usuario puede asignar a una orden. */
export function estadosPermitidos(statuses, isAdmin) {
    if (isAdmin) return statuses ?? [];
    return (statuses ?? []).filter(s => ESTADOS_OPERATIVOS_MECANICO.includes(s.slug));
}

/** El mecánico no puede cambiar el estado de una orden cerrada. */
export function puedeCambiarEstado(orden, isAdmin) {
    if (isAdmin) return true;
    return !ESTADOS_CERRADOS.includes(orden?.status?.slug);
}

/**
 * Variante semántica del Design System v1 por slug de estado de orden.
 * Centraliza el mapeo: no usar color_class de base de datos para la semántica visual.
 * "Autorización pendiente" NO es un estado de orden: se representa con InlineStatus.
 */
export const ORDER_STATUS_VARIANT = {
    recibido: 'neutral',
    diagnostico: 'info',
    'espera-piezas': 'warning',
    'en-reparacion': 'info',
    reparado: 'success',
    entregado: 'neutral',
    rechazado: 'danger',
};

export function orderStatusVariant(slug) {
    return ORDER_STATUS_VARIANT[slug] ?? 'neutral';
}
