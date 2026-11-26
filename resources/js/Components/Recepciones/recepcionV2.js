// Recepción V2: formato de la lectura y del identificador. Solo muestran lo guardado; nunca convierten unidades.
// Un valor histórico (columna miles) sin tipo se muestra tal cual, marcando que la unidad no está especificada.
export const UNIDADES_LECTURA = ['km', 'mi', 'hr'];
export const TIPOS_IDENTIFICADOR = ['vin', 'vin_last_8', 'vin_last_10', 'serial', 'other', 'none'];

// textoSinUnidad: etiqueta traducida para la lectura histórica sin unidad conocida.
export function formatLectura(recepcion, textoSinUnidad = 'unidad histórica no especificada') {
    if (!recepcion) return null;

    const valor = recepcion.reading_value;
    if (recepcion.reading_type && valor !== null && valor !== undefined && valor !== '') {
        const n = Number(valor);
        if (Number.isFinite(n)) {
            return `${n.toLocaleString('es-MX', { maximumFractionDigits: 2 })} ${recepcion.reading_type}`;
        }
    }

    const historico = lecturaHistorica(recepcion);
    return historico === null ? null : `${historico} · ${textoSinUnidad}`;
}

// Valor histórico de miles sin tipo de lectura. Solo enteros se agrupan; el resto se muestra como se guardó.
export function lecturaHistorica(recepcion) {
    if (!recepcion || recepcion.reading_type) return null;

    const legado = String(recepcion.miles ?? '').trim();
    if (legado === '') return null;

    const n = Number(legado);
    if (legado !== '' && Number.isFinite(n) && Number.isInteger(n)) {
        return n.toLocaleString('es-MX', { maximumFractionDigits: 0 });
    }
    return legado;
}

// Identificador del vehículo. Un VIN legado sin tipo propio se trata como VIN.
export function identificadorDe(vehiculo) {
    if (!vehiculo) return { tipo: null, valor: null };

    let tipo = vehiculo.identifier_type ?? null;
    let valor = vehiculo.identifier_value ?? null;

    if (tipo === null && vehiculo.vin) {
        tipo = 'vin';
        valor = vehiculo.vin;
    }

    if (tipo === 'none' || !valor) return { tipo, valor: null };

    return { tipo, valor };
}
