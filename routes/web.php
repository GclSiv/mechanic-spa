<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientPhotoController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\RepairOrderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Setting;

// --- 1. PANTALLA DE BIENVENIDA (PÚBLICA) ---
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'settings'    => Setting::current()->publicContactData(),
    ]);
});

// --- 2. RUTAS PROTEGIDAS (SISTEMA JK AUTOMOTIVE) ---
Route::middleware(['auth'])->group(function () {


    // Verificación de placa/VIN y de RFC/cliente (AJAX): solo recepción (admin/owner)
    Route::get('/vehicles/check', [\App\Http\Controllers\RecepcionController::class, 'checkVehicle'])
        ->middleware('role:admin')
        ->name('vehicles.check');

    Route::get('/clients/check', [\App\Http\Controllers\RecepcionController::class, 'checkClient'])
        ->middleware('role:admin')
        ->name('clients.check');

    // ── Seguimiento de cotizaciones enviadas a clientes ───────────────────
    // Listado global y exportación: solo admin/dueño (incluye datos de clientes de todas las órdenes).
    Route::get('/cotizaciones', [\App\Http\Controllers\QuoteFollowUpController::class, 'index'])
        ->name('quotes.follow-up')
        ->middleware('role:admin');
    Route::get('/cotizaciones/exportar', [\App\Http\Controllers\QuoteFollowUpController::class, 'exportar'])
        ->name('quotes.follow-up.export')
        ->middleware('role:admin');

    // ── Módulo de Cuentas / Finanzas (solo admin) ─────────────────────────
    Route::get('/cuentas', [\App\Http\Controllers\FinanceController::class, 'index'])
        ->name('finance.index')
        ->middleware(['role:admin', 'module:caja']);

    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Categorías de inventario (solo admin) ─────────────────────────────
    Route::middleware('role:admin')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/',          [\App\Http\Controllers\CategoryController::class, 'index'])->name('index');
        Route::post('/',         [\App\Http\Controllers\CategoryController::class, 'store'])->name('store');
        Route::put('/{category}',[\App\Http\Controllers\CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [\App\Http\Controllers\CategoryController::class, 'destroy'])->name('destroy');
    });

    // Listado de clientes (solo admin/owner; el mecánico no ve la lista completa)
    Route::get('/clientes', [\App\Http\Controllers\ClientController::class, 'index'])
        ->middleware('role:admin')
        ->name('clients.index');

    // Expediente del cliente (B1.1). whereNumber evita colisión con /clientes/importar.
    Route::get('/clientes/{client}', [\App\Http\Controllers\ClientController::class, 'show'])
        ->whereNumber('client')
        ->middleware('role:admin')
        ->name('clients.show');

    // ========================================================
    // MÓDULO 1: RECEPCIÓN TÉCNICA (RESTful y Route Model Binding)
    // Solo admin/owner: crear, consultar, modificar, eliminar y generar órdenes.
    // ========================================================
    Route::controller(RecepcionController::class)
        ->prefix('recepciones') // Todo unificado a plural
        ->middleware('role:admin')
        ->group(function () {
            
            // ⚠️ Rutas estáticas van primero para evitar colisiones
            Route::get('/', 'index')->name('recepcion.index');
            Route::get('/create', 'create')->name('recepcion.create');
            Route::post('/', 'store')->name('recepcion.store');

            // ✅ Route Model Binding: Inyectamos el modelo automáticamente
            Route::get('/{recepcion}', 'showRecepcion')->name('recepcion.show');
            Route::get('/{recepcion}/edit', 'edit')->name('recepcion.edit');
            Route::put('/{recepcion}', 'update')->name('recepcion.update');
            Route::delete('/{recepcion}', 'destroy')->name('recepcion.destroy');
            Route::get('/{recepcion}/pdf', 'print')->name('recepcion.pdf');
            Route::post('/{recepcion}/generate-order', 'generateOrder')->name('recepcion.generate-order');
    });
    Route::get('/recepciones/{recepcion}/fotos/{indice}', [\App\Http\Controllers\RecepcionController::class, 'foto'])->whereNumber('indice')->name('recepcion.foto');

    // ========================================================
    // MÓDULO 5: GESTIÓN DE MECÁNICOS (solo admin)
    // ========================================================
    Route::resource('mechanics', \App\Http\Controllers\MechanicController::class)
        ->except(['show'])
        ->names('mechanics')
        ->middleware('role:admin');

    // ========================================================
    // CATÁLOGO DE SERVICIOS con comisión fija (solo admin/owner)
    // ========================================================
    Route::resource('services', \App\Http\Controllers\ServiceController::class)
        ->except(['show'])
        ->middleware('role:admin');

    // ========================================================
    // MÓDULO DE EXPORTACIÓN (solo admin)
    // ========================================================
    Route::get('/exportar/ordenes', [\App\Http\Controllers\ExportController::class, 'ordenesCSV'])
        ->name('export.ordenes')
        ->middleware('role:admin');
    // ========================================================
    // ── Herramientas y activos (solo admin/owner) ─────────────────────
    Route::resource('tools', \App\Http\Controllers\ToolController::class)
        ->names('tools')
        ->middleware('role:admin');
    Route::post('tools/{tool}/evidencia', [\App\Http\Controllers\ToolMediaController::class, 'store'])
        ->name('tools.media.store')
        ->middleware('role:admin');
    Route::get('tools/{tool}/evidencia/{media}', [\App\Http\Controllers\ToolMediaController::class, 'show'])
        ->name('tools.media.show')
        ->middleware('role:admin');
    Route::delete('tools/{tool}/evidencia/{media}', [\App\Http\Controllers\ToolMediaController::class, 'destroy'])
        ->name('tools.media.destroy')
        ->middleware('role:admin');

    Route::resource('parts', \App\Http\Controllers\PartController::class)
        ->except(['show'])
        ->names('parts')
        ->middleware('role:admin');
    Route::get('parts/escanear', [\App\Http\Controllers\PartController::class, 'scan'])
        ->name('parts.scan')
        ->middleware('role:admin');
    Route::get('parts/{part}/kardex', [\App\Http\Controllers\PartController::class, 'kardex'])
        ->name('parts.kardex')
        ->middleware('role:admin');
    Route::post('parts/{part}/movimientos', [\App\Http\Controllers\PartController::class, 'movimiento'])
        ->name('parts.movimientos.store')
        ->middleware('role:admin');

    // ========================================================
    // MÓDULO 7: PAGOS Y COBRANZA
    // ========================================================
    Route::post('/repair-orders/{order}/payments', [\App\Http\Controllers\PaymentController::class, 'store'])
        ->name('payments.store');
    Route::delete('/repair-orders/{order}/payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'destroy'])
        ->name('payments.destroy');
    Route::get('/repair-orders/{order}/payments/{payment}/ticket', [\App\Http\Controllers\PaymentController::class, 'ticket'])
        ->name('payments.ticket');

    // FOTOS DEL AVANCE (taller)
    Route::post('/repair-orders/{order}/fotos-avance', [\App\Http\Controllers\RepairOrderPhotoController::class, 'store'])
        ->middleware('own.order')
        ->name('repair-orders.avance.store');
    Route::get('/repair-orders/{order}/fotos-avance/{photo}', [\App\Http\Controllers\RepairOrderPhotoController::class, 'show'])
        ->middleware('own.order')
        ->name('repair-orders.avance.photo');
    Route::delete('/repair-orders/{order}/fotos-avance/{photo}', [\App\Http\Controllers\RepairOrderPhotoController::class, 'destroy'])
        ->middleware('own.order')
        ->name('repair-orders.avance.destroy');

    // ========================================================
    // MÓDULO 2: ÓRDENES DE REPARACIÓN Y COTIZACIÓN
    // ========================================================
    // Lista con saldos y pagos: solo admin/owner
    Route::get('/repair-orders/lista', [\App\Http\Controllers\RepairOrderListController::class, 'index'])
        ->middleware('role:admin')
        ->name('repair-orders.lista');

    Route::controller(RepairOrderController::class)
        ->prefix('repair-orders')
        ->name('repair-orders.')
        ->middleware('own.order')
        ->group(function () {
            
            // Ver panel de gestión de la orden
            Route::get('/', 'index')->name('index');
            Route::get('/{order}', 'show')->name('show');

            // Gestión de conceptos (Refacciones y Mano de obra)
            Route::post('/{order}/items', 'addItem')->name('items.store');
            Route::delete('/{order}/items/{item}', 'removeItem')->name('items.destroy');

            // PDF con precios (cotización): solo admin/owner
            Route::get('/{order}/pdf', 'downloadPdf')->name('pdf')->middleware('role:admin');

            // Fase 3: Cambio de estado de la orden
            Route::patch('/{order}/status', 'updateStatus')->name('status.update');

            // Fase 4: Asignación de mecánico (solo admin/owner) y bitácora
            Route::patch('/{order}/mechanic', 'assignMechanic')->name('mechanic.assign')->middleware('role:admin');
            Route::post('/{order}/follow-ups', [\App\Http\Controllers\FollowUpController::class, 'store'])->name('follow-ups.store');
            Route::delete('/{order}/follow-ups/{followUp}', [\App\Http\Controllers\FollowUpController::class, 'destroy'])->name('follow-ups.destroy');
            Route::get('/{order}/notas/{followUp}/fotos/{indice}', [\App\Http\Controllers\FollowUpController::class, 'foto'])->whereNumber('indice')->name('follow-ups.foto');

            // Nuevas rutas operativas
            Route::post('/{order}/diagnosis-complete', 'diagnosisComplete')->name('diagnosis.complete');
            Route::post('/{order}/reassign-mechanic',  'reassignMechanic')->name('mechanic.reassign')->middleware('role:admin');
            Route::patch('/{order}/tax-rate', 'updateTaxRate')->name('tax.update')->middleware('role:admin');
    });

    // ========================================================
    // MÓDULO 3: GESTIÓN DE FOTOS Y ARCHIVOS
    // ========================================================
    Route::get('/vehiculos/{vehicle}/historial', [\App\Http\Controllers\VehicleHistoryController::class, 'show'])
        ->middleware('role:admin')
        ->name('vehicles.history');

    // Catálogo de marca y modelo desde Nueva/Editar recepción (admin/owner; mecánico 403).
    Route::post('/catalogo/marcas', [\App\Http\Controllers\BrandController::class, 'store'])
        ->middleware('role:admin')
        ->name('brands.store');
    Route::post('/catalogo/marcas/{brand}/modelos', [\App\Http\Controllers\VehicleModelController::class, 'store'])
        ->middleware('role:admin')
        ->name('brands.models.store');

    Route::controller(ClientPhotoController::class)->middleware('role:admin')->group(function () {
        Route::post('/clients/{client}/photos', 'store')->name('clients.photos.store');
        Route::get('/clients/{client}/photos/{photo}', 'show')->name('clients.photos.show');
        Route::delete('/photos/{photo}', 'destroy')->name('clients.photos.destroy');
    });

    // ========================================================
    // MÓDULO 4: CONFIGURACIÓN Y PERFIL
    // ========================================================
    Route::controller(SettingController::class)->middleware('role:admin')->group(function () {
        Route::get('/configuracion', 'edit')->name('settings.edit');
        Route::put('/configuracion', 'update')->name('settings.update');
    });
    
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // ========================================================
    // MÓDULO 8: REPORTES FINANCIEROS MENSUALES (solo admin)
    // ========================================================
    Route::middleware('module:reportes')->prefix('reportes')
        ->name('reports.')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\FinancialReportController::class, 'index'])
                ->name('index');
            Route::get('/pdf', [\App\Http\Controllers\FinancialReportController::class, 'downloadPdf'])
                ->name('pdf');
            Route::get('/mecanicos', [\App\Http\Controllers\MechanicProductivityController::class, 'index'])
                ->name('mechanics');
        });

});

Route::get('/logo-taller', [\App\Http\Controllers\SettingController::class, 'logo'])->name('settings.logo');

require __DIR__.'/auth.php';
/*
|--------------------------------------------------------------------------
| Cotizaciones (personal del taller)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('repair-orders/{order}/quotes')
    ->name('repair-orders.quotes.')
    ->middleware(['own.order', 'role:admin'])
    ->group(function () {
        Route::post('/', [\App\Http\Controllers\QuoteController::class, 'store'])->name('store');
        Route::patch('/{quote}/send', [\App\Http\Controllers\QuoteController::class, 'send'])->name('send');
        Route::patch('/{quote}/approve', [\App\Http\Controllers\QuoteController::class, 'approve'])->name('approve');
        Route::patch('/{quote}/reject', [\App\Http\Controllers\QuoteController::class, 'reject'])->name('reject');
        Route::get('/{quote}/pdf', [\App\Http\Controllers\QuoteController::class, 'pdf'])->name('pdf');
        Route::delete('/{quote}', [\App\Http\Controllers\QuoteController::class, 'destroy'])->name('destroy');
    });

/*
|--------------------------------------------------------------------------
| Seguimiento público del cliente (sin login, acceso por token)
|--------------------------------------------------------------------------
*/
Route::get('/seguimiento/{token}', [\App\Http\Controllers\TrackingController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('tracking.show');
Route::get('/seguimiento/{token}/cotizaciones/{quote}/pdf', [\App\Http\Controllers\TrackingController::class, 'quotePdf'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('tracking.quote.pdf');
Route::post('/seguimiento/{token}/cotizaciones/{quote}/aceptar', [\App\Http\Controllers\TrackingController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:10,1')
    ->name('tracking.quote.accept');
Route::post('/seguimiento/{token}/cotizaciones/{quote}/rechazar', [\App\Http\Controllers\TrackingController::class, 'reject'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:10,1')
    ->name('tracking.quote.reject');
Route::get('/seguimiento/{token}/fotos/{photo}', [\App\Http\Controllers\TrackingController::class, 'avancePhoto'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('tracking.avance.photo');

/*
|--------------------------------------------------------------------------
| Importación de clientes (solo administrador)
|--------------------------------------------------------------------------
*/
// auth va en el grupo externo, igual que el resto de las pantallas administrativas: el invitado
// se redirige al login (302) antes de evaluar role:admin, que responde 403 a un usuario sin permiso.
Route::middleware(['auth'])->group(function () {
    Route::middleware(['role:admin', 'module:importar_clientes'])
        ->prefix('clientes')
        ->name('clients.import.')
        ->group(function () {
            Route::get('/importar', [\App\Http\Controllers\ClientImportController::class, 'index'])->name('index');
            Route::get('/importar/plantilla', [\App\Http\Controllers\ClientImportController::class, 'template'])->name('template');
            Route::post('/importar', [\App\Http\Controllers\ClientImportController::class, 'store'])->name('store');
        });
});

/*
|--------------------------------------------------------------------------
| Panel del dueño (solo rol owner)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:owner'])
    ->prefix('dueno')
    ->name('owner.')
    ->group(function () {
        Route::get('/modulos', [\App\Http\Controllers\Owner\ModuleController::class, 'index'])->name('modules.index');
        Route::put('/modulos', [\App\Http\Controllers\Owner\ModuleController::class, 'update'])->name('modules.update');
    });
