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
        'canRegister' => Route::has('register'),
        'settings'    => Setting::first(),
    ]);
});

// --- 2. RUTAS PROTEGIDAS (SISTEMA JK AUTOMOTIVE) ---
Route::middleware(['auth', 'verified'])->group(function () {

Route::post('/settings/tax', [SettingController::class, 'updateTax'])->name('settings.updateTax');
    // El puente que crea la cotización desde el Dashboard
Route::get('/recepciones/{recepcion}/generate-order', [App\Http\Controllers\RecepcionController::class, 'generateOrder'])->name('recepcion.generateOrder');

// Las rutas de la pantalla de Cotizaciones
Route::get('/repair-orders/{order}', [RepairOrderController::class, 'show'])->name('repair-orders.show');
Route::post('/repair-orders/{order}/items', [RepairOrderController::class, 'addItem'])->name('repair-orders.addItem');
Route::delete('/repair-orders/{order}/items/{item}', [RepairOrderController::class, 'removeItem'])->name('repair-orders.removeItem');
    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ========================================================
    // MÓDULO 1: RECEPCIÓN TÉCNICA (RESTful y Route Model Binding)
    // ========================================================
    Route::controller(RecepcionController::class)
        ->prefix('recepciones') // Todo unificado a plural
        ->group(function () {
            
            // ⚠️ Rutas estáticas van primero para evitar colisiones
            Route::get('/', 'index')->name('recepcion.index');
            Route::get('/exportar', 'export')->name('recepcion.export');
            Route::get('/create', 'create')->name('recepcion.create');
            Route::post('/', 'store')->name('recepcion.store');

            // ✅ Route Model Binding: Inyectamos el modelo automáticamente
            Route::get('/{recepcion}', 'show')->name('recepcion.show');
            Route::get('/{recepcion}/edit', 'edit')->name('recepcion.edit');
            Route::delete('/{recepcion}', 'destroy')->name('recepcion.destroy');
            Route::get('/{recepcion}/pdf', 'print')->name('recepcion.pdf');
            Route::post('/{recepcion}/generate-order', 'generateOrder')->name('recepcion.generate-order');
    });

    // ========================================================
    // MÓDULO 2: ÓRDENES DE REPARACIÓN Y COTIZACIÓN
    // ========================================================
    Route::controller(RepairOrderController::class)
        ->prefix('repair-orders')
        ->name('repair-orders.')
        ->group(function () {
            
            // Ver panel de gestión de la orden
            Route::get('/{order}', 'show')->name('show');

            // Gestión de conceptos (Refacciones y Mano de obra)
            Route::post('/{order}/items', 'addItem')->name('items.store');
            Route::delete('/{order}/items/{item}', 'removeItem')->name('items.destroy');

            // Generación de PDF centralizada en el controlador principal
            Route::get('/{order}/pdf', 'downloadPdf')->name('pdf');

            // Fase 3: Cambio de estado de la orden
            Route::patch('/{order}/status', 'updateStatus')->name('status.update');

            // Fase 4: Asignación de mecánico y bitácora
            Route::patch('/{order}/mechanic', 'assignMechanic')->name('mechanic.assign');
            Route::post('/{order}/follow-ups', 'storeFollowUp')->name('follow-ups.store');
    });

    // ========================================================
    // MÓDULO 3: GESTIÓN DE FOTOS Y ARCHIVOS
    // ========================================================
    Route::controller(ClientPhotoController::class)->group(function () {
        Route::post('/clients/{client}/photos', 'store')->name('clients.photos.store');
        Route::delete('/photos/{photo}', 'destroy')->name('clients.photos.destroy');
    });

    // ========================================================
    // MÓDULO 4: CONFIGURACIÓN Y PERFIL
    // ========================================================
    Route::controller(SettingController::class)->group(function () {
        Route::get('/configuracion', 'edit')->name('settings.edit');
        Route::put('/configuracion', 'update')->name('settings.update');
    });
    
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

});

require __DIR__.'/auth.php';