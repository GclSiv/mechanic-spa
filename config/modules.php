<?php

/*
|--------------------------------------------------------------------------
| Módulos del sistema
|--------------------------------------------------------------------------
| El dueño activa o desactiva cada módulo por instalación desde /dueno/modulos.
| Un módulo apagado desaparece del menú y responde 404 si se escribe la ruta.
| Los módulos no listados aquí (recepción, órdenes, cotizaciones, Kanban,
| clientes, configuración) forman el núcleo y siempre están disponibles.
*/

return [
    'caja' => [
        'label'       => 'Caja y cuentas por cobrar',
        'description' => 'Pagos, anticipos, saldos pendientes y estado de cuenta.',
        'default'     => true,
    ],
    'reportes' => [
        'label'       => 'Reportes',
        'description' => 'Reporte de ingresos en pantalla y en PDF.',
        'default'     => true,
    ],
    'importar_clientes' => [
        'label'       => 'Importación de clientes',
        'description' => 'Carga masiva de clientes desde CSV.',
        'default'     => true,
    ],
];
