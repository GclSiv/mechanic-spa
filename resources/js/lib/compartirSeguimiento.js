// Utilidades para comunicar el seguimiento al cliente. Nunca envía nada: solo prepara el texto y abre WhatsApp.

// Formato WhatsApp de México: 52 + 10 dígitos (mismo criterio que el taller ya usaba).
export const normalizarTelefono = (tel) => {
    const d = (tel ?? '').replace(/\D/g, '');
    if (!d) return null;
    if (d.length === 10) return '52' + d;
    if (d.length === 13 && d.startsWith('521')) return '52' + d.slice(3);
    return d;
};

// Teléfono utilizable para WhatsApp (E.164 razonable). Si no lo es, la UI oculta o deshabilita el botón.
export const telefonoWhatsApp = (tel) => {
    const n = normalizarTelefono(tel);
    return n && n.length >= 11 && n.length <= 15 ? n : null;
};

export const urlWhatsApp = (tel, texto) => {
    const text = encodeURIComponent(texto);
    return tel ? `https://wa.me/${tel}?text=${text}` : `https://wa.me/?text=${text}`;
};

// Mensaje prellenado. Solo menciona autorización si hay una cotización realmente enviada y pendiente.
export const mensajeSeguimiento = (t, { nombre, vehiculo, url, esperandoAutorizacion }) => {
    const datos = {
        nombre: nombre || t('comunicacion.clienteGenerico'),
        vehiculo: vehiculo || t('comunicacion.vehiculoGenerico'),
        url,
    };
    return esperandoAutorizacion
        ? t('comunicacion.waAutorizar', datos)
        : t('comunicacion.waSeguimiento', datos);
};

// Copia con el método que funcione: el moderno (https/localhost) o, si no, un respaldo con selección de texto.
function copiarRespaldo(texto) {
    const area = document.createElement('textarea');
    area.value = texto;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(area);
    return ok;
}

export const copiarTexto = async (texto) => {
    let ok = false;
    if (window.isSecureContext && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(texto);
            ok = true;
        } catch (e) {
            ok = false;
        }
    }
    if (!ok) ok = copiarRespaldo(texto);
    return ok;
};
