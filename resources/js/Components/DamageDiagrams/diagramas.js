// Geometría del diagrama de daños por tipo de unidad. Solo presentación.
// Las claves de zona DEBEN coincidir con App\Support\DiagramaDano::ZONAS (el backend valida y decide).
// Automóvil conserva exactamente las zonas y formas que ya usaba la app.
//
// Cada diagrama define:
//  - viewBox: encuadre propio del dibujo (la moto es alta y estrecha; el camión, más ancho).
//  - zonas:   áreas tocables. Cada una agrupa una o varias formas (path, rect o ellipse).
//  - decor:   siluetas de fondo, no interactivas, dibujadas bajo las zonas.
//  - detalles: trazos encima de las zonas (volante, manillar, juntas), siempre con pointer-events none.

export const DIAGRAMA_CAR = 'car';
export const DIAGRAMA_GENERIC = 'generic';

const R = (x, y, w, h, rx = 4) => ({ x, y, w, h, rx });
const P = (d) => ({ d });
const E = (cx, cy, rx, ry) => ({ cx, cy, rx, ry });

// Cada zona: { key, shapes }. Varias formas pueden pertenecer a la misma zona.
const Z = (key, ...shapes) => ({ key, shapes });

// Pieza no interactiva: relleno y trazo propios (sw = grosor de trazo)
const DEC = (shape, fill, stroke = 'none', sw = 1.5) => ({ ...shape, fill, stroke, sw });
// Trazo abierto (manillar, junta, borde): sin relleno
const LINEA = (d, stroke = '#475569', sw = 2) => ({ d, fill: 'none', stroke, sw });
// Contorno de volante o similar
const CONTORNO = (cx, cy, rx, ry, stroke = '#475569', sw = 2) => ({ cx, cy, rx, ry, fill: 'none', stroke, sw });

export const DIAGRAMAS = {
    car: {
        viewBox: '0 0 300 620',
        zonas: [
            Z('defensa_del', P('M92 40 C120 24 180 24 208 40 L220 78 L80 78 Z')),
            Z('capo', P('M80 96 C110 76 190 76 220 96 L224 196 L76 196 Z')),
            Z('parabrisas', P('M78 200 L222 200 L210 240 L90 240 Z')),
            Z('techo', P('M90 244 L210 244 L208 342 L92 342 Z')),
            Z('luneta', P('M90 346 L210 346 L222 396 L78 396 Z')),
            Z('cajuela', P('M78 400 L222 400 C228 436 230 486 226 512 L74 512 C70 486 72 436 78 400 Z')),
            Z('defensa_tra', P('M80 528 L220 528 C212 560 184 574 150 574 C116 574 88 560 80 528 Z')),
            Z('puerta_fi', R(64, 200, 22, 124)),
            Z('puerta_fd', R(214, 200, 22, 124)),
            Z('puerta_ti', R(64, 326, 22, 74)),
            Z('puerta_td', R(214, 326, 22, 74)),
            Z('espejo_i', R(60, 196, 12, 16)),
            Z('espejo_d', R(228, 196, 12, 16)),
            Z('llanta_fi', R(34, 112, 18, 62, 7)),
            Z('llanta_fd', R(248, 112, 18, 62, 7)),
            Z('llanta_ti', R(34, 432, 18, 62, 7)),
            Z('llanta_td', R(248, 432, 18, 62, 7)),
        ],
        // Carrocería y llantas decorativas del automóvil (sin interacción), como antes.
        decor: [
            DEC(R(36, 112, 18, 62, 7), '#1e293b'),
            DEC(R(246, 112, 18, 62, 7), '#1e293b'),
            DEC(R(36, 432, 18, 62, 7), '#1e293b'),
            DEC(R(246, 432, 18, 62, 7), '#1e293b'),
            DEC(P('M150 18 C192 18 222 42 228 98 L234 200 C237 262 238 300 236 332 C240 402 236 462 232 520 C228 562 198 582 150 583 C102 582 72 562 68 520 C64 462 60 402 64 332 C62 300 63 262 66 200 L72 98 C78 42 108 18 150 18 Z'), '#f8fafc', '#94a3b8', 2.5),
        ],
        detalles: [],
    },

    // Moto vista superior: frente arriba. Rueda, carenado, tanque, asiento, motor, escape y brazo.
    motorcycle: {
        viewBox: '0 0 200 600',
        zonas: [
            Z('moto_rueda_delantera', E(100, 72, 19, 50)),
            Z('moto_frontal', P('M74 150 Q100 134 126 150 L132 194 Q100 206 68 194 Z')),
            Z('moto_tanque', P('M66 202 Q66 194 80 194 L120 194 Q134 194 134 202 L138 262 Q100 276 62 262 Z')),
            Z('moto_asiento', P('M62 268 Q100 256 138 268 L142 330 Q140 344 124 346 L76 346 Q60 344 58 330 Z')),
            Z('moto_lateral_izq', R(40, 226, 14, 110, 6)),
            Z('moto_lateral_der', R(146, 226, 14, 110, 6)),
            Z('moto_escape', R(146, 352, 10, 120, 5), R(142, 420, 18, 50, 8)),
            Z('moto_rueda_trasera', E(100, 520, 22, 50)),
        ],
        decor: [
            DEC(R(94, 118, 12, 40, 3), '#cbd5e1'),   // horquilla delantera
            DEC(R(82, 348, 36, 70, 12), '#cbd5e1'),  // motor
            DEC(R(94, 400, 12, 80, 4), '#cbd5e1'),   // basculante
        ],
        detalles: [
            LINEA('M46 154 L154 154', '#334155', 5), // manillar
            DEC(R(40, 146, 12, 16, 6), '#334155'),   // puño izquierdo
            DEC(R(148, 146, 12, 16, 6), '#334155'),  // puño derecho
            DEC(E(100, 72, 6, 6), '#94a3b8'),        // buje delantero
            DEC(E(100, 520, 8, 8), '#94a3b8'),       // buje trasero
        ],
    },

    // Montacargas vista superior: horquillas al frente, mástil, techo protector, cabina y contrapeso atrás.
    forklift: {
        viewBox: '0 0 260 540',
        zonas: [
            Z('fork_horquillas', R(100, 10, 14, 62, 3), R(146, 10, 14, 62, 3)),
            Z('fork_mastil', R(90, 66, 80, 40, 5)),
            Z('fork_ruedas', R(60, 118, 20, 52, 7), R(180, 118, 20, 52, 7), R(64, 426, 18, 30, 6), R(178, 426, 18, 30, 6)),
            Z('fork_lateral_izq', R(84, 176, 10, 256, 4)),
            Z('fork_lateral_der', R(166, 176, 10, 256, 4)),
            Z('fork_techo', R(86, 244, 88, 54, 8)),
            Z('fork_cabina', R(94, 302, 72, 92, 10)),
            Z('fork_contrapeso', R(84, 460, 92, 62, 12)),
        ],
        decor: [
            DEC(R(80, 140, 100, 10, 3), '#cbd5e1'),  // eje delantero
            DEC(R(92, 398, 76, 58, 6), '#cbd5e1'),   // chasis entre cabina y contrapeso
        ],
        detalles: [
            LINEA('M104 66 L104 106', '#475569', 2), // guías del mástil
            LINEA('M156 66 L156 106', '#475569', 2),
            CONTORNO(130, 350, 12, 12),              // volante
        ],
    },

    // Camión vista superior: cofre y cabina al frente, caja o remolque atrás, ejes con ruedas.
    truck: {
        viewBox: '0 0 240 500',
        zonas: [
            Z('camion_frontal', P('M68 30 Q120 8 172 30 L176 52 L64 52 Z')),
            Z('camion_cofre_cabina', R(64, 56, 112, 96, 14)),
            Z('camion_techo', R(64, 156, 112, 76, 10)),
            Z('camion_lateral_izq', R(58, 64, 8, 168, 3)),
            Z('camion_lateral_der', R(174, 64, 8, 168, 3)),
            Z('camion_caja_remolque', R(58, 240, 124, 240, 6)),
            Z('camion_ruedas_delanteras', R(40, 96, 16, 60, 6), R(184, 96, 16, 60, 6)),
            Z('camion_ruedas_traseras', R(40, 330, 16, 64, 6), R(184, 330, 16, 64, 6), R(40, 410, 16, 64, 6), R(184, 410, 16, 64, 6)),
        ],
        decor: [],
        detalles: [
            LINEA('M70 140 Q120 146 170 140', '#475569', 2), // parabrisas
            LINEA('M90 40 L150 40', '#475569', 2),           // rejilla
        ],
    },

    // Maquinaria pesada genérica (cargador / retroexcavadora): implemento al frente, brazos, cabina, orugas.
    heavy_generic: {
        viewBox: '0 0 240 480',
        zonas: [
            Z('maq_implemento', P('M64 12 L176 12 L184 50 Q176 74 120 74 Q64 74 56 50 Z')),
            Z('maq_frontal', R(88, 80, 64, 50, 8)),
            Z('maq_estructura', R(84, 134, 72, 50, 6), R(84, 266, 72, 140, 8)),
            Z('maq_cabina', R(92, 190, 56, 72, 10)),
            Z('maq_posterior', R(78, 410, 84, 56, 10)),
            Z('maq_lateral_izq', R(60, 130, 12, 300, 4)),
            Z('maq_lateral_der', R(168, 130, 12, 300, 4)),
            Z('maq_tren_rodaje_ruedas', R(36, 90, 20, 380, 10), R(184, 90, 20, 380, 10)),
        ],
        decor: [
            DEC(R(74, 84, 12, 60, 4), '#cbd5e1'),   // brazo izquierdo
            DEC(R(154, 84, 12, 60, 4), '#cbd5e1'),  // brazo derecho
        ],
        detalles: [
            // Tacos de la oruga, a ambos lados
            ...Array.from({ length: 19 }, (_, i) => 100 + i * 20).flatMap((y) => [
                LINEA(`M36 ${y} L56 ${y}`, '#64748b', 2),
                LINEA(`M184 ${y} L204 ${y}`, '#64748b', 2),
            ]),
            LINEA('M96 222 L144 222', '#475569', 2), // ventanilla de cabina
        ],
    },

    // Genérico: rectángulos sin cambios de geometría (se usa cuando la unidad no tiene diagrama propio).
    generic: {
        viewBox: '0 0 240 620',
        zonas: [
            Z('gen_frontal', R(90, 20, 120, 50, 6)),
            Z('gen_techo', R(90, 90, 120, 110, 8)),
            Z('gen_estructura', R(90, 210, 120, 330, 8)),
            Z('gen_posterior', R(90, 550, 120, 50, 6)),
            Z('gen_lateral_izq', R(60, 90, 16, 440, 5)),
            Z('gen_lateral_der', R(224, 90, 16, 440, 5)),
        ],
        decor: [],
        detalles: [],
    },
};

// Diagrama válido o fallback seguro. Un valor desconocido nunca rompe la vista.
export function diagramaValido(clave) {
    return Object.prototype.hasOwnProperty.call(DIAGRAMAS, clave) ? clave : DIAGRAMA_GENERIC;
}

// Diagrama para un tipo de vehículo (catálogo con damage_diagram). Sin tipo => generic.
export function diagramaParaTipo(tipos, tipoId) {
    const tipo = (tipos ?? []).find((x) => x.id == tipoId);
    return diagramaValido(tipo?.damage_diagram ?? null);
}

export function zonasDe(clave) {
    return (DIAGRAMAS[diagramaValido(clave)] ?? DIAGRAMAS.generic).zonas.map((z) => z.key);
}

// Etiqueta de zona: car usa las claves históricas; los demás usan diagramasUi.zona_<clave>.
export function etiquetaZona(zona, t, te) {
    if (String(zona).startsWith('moto_') || String(zona).startsWith('fork_') || String(zona).startsWith('camion_') ||
        String(zona).startsWith('maq_') || String(zona).startsWith('gen_')) {
        return te(`diagramasUi.zona_${zona}`) ? t(`diagramasUi.zona_${zona}`) : zona;
    }
    return t('recepcion_form.zona_' + zona);
}
