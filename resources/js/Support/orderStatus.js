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

/** Estados que el tablero y el Expediente ofrecen al admin: solo operativos, nunca cierre. */
export const ESTADOS_TABLERO = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

/** Secuencia visual del ciclo de vida del taller. Rechazado no forma parte de ella. */
export const ESTADOS_SECUENCIA = ['recibido', 'diagnostico', 'espera-piezas', 'en-reparacion', 'reparado', 'entregado'];

/** Opciones de cambio de estado en el Expediente, por rol. El backend sigue siendo la autoridad. */
export function opcionesDeEstado(statuses, isAdmin) {
    const permitidos = isAdmin ? ESTADOS_TABLERO : ESTADOS_OPERATIVOS_MECANICO;
    return (statuses ?? []).filter(s => permitidos.includes(s.slug));
}

/**
 * Situación de cada etapa del lifecycle respecto a la orden actual:
 * 'completed' (anterior a la actual), 'current' (la actual), 'future' (posterior).
 * Rechazado no pertenece a la secuencia: no se infiere ningún recorrido; todas las etapas quedan 'future'.
 * Representación visual del estado actual, no historial ni fechas de transición.
 */
export function estadosDelLifecycle(slug) {
    const indice = ESTADOS_SECUENCIA.indexOf(slug);
    return ESTADOS_SECUENCIA.map((estado, i) => {
        let situacion = 'future';
        if (indice !== -1) {
            if (i < indice) situacion = 'completed';
            else if (i === indice) situacion = 'current';
        }
        return { slug: estado, situacion };
    });
}
