<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientPhotoController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\RepairOrderController;
use App\Http\Controllers\RepairOrderItemController;
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
    
    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- Módulo de Recepción Técnica ---
    Route::prefix('recepcion')->group(function () {
        Route::get('/create', [RecepcionController::class, 'create'])->name('recepcion.create');
        Route::post('/', [RecepcionController::class, 'store'])->name('recepcion.store');
        Route::get('/pdf/{id}', [RecepcionController::class, 'print'])->name('recepcion.pdf');
        Route::get('/exportar', [RecepcionController::class, 'export'])->name('recepcion.export');
        Route::get('/{id}/editar', [RecepcionController::class, 'edit'])->name('recepcion.edit');
        Route::delete('/{id}', [RecepcionController::class, 'destroy'])->name('recepcion.destroy');
    });

    // --- Módulo de Gestión de Órdenes y Cotización ---
    // Ver panel de gestión (Show.vue)
    Route::get('/repair-orders/{id}', [RepairOrderController::class, 'show'])
        ->name('repair-orders.show');

    // Guardar items (Mano de obra/Refacciones)
    Route::post('/repair-order-items', [RepairOrderItemController::class, 'store'])
        ->name('repair-order-items.store');

    // Generar PDF de la Cotización (PDF de Salida con Taxes de CA)
    Route::get('/cotizacion/pdf/{id}', [RepairOrderItemController::class, 'descargarCotizacion'])
        ->name('cotizacion.pdf');

    // --- Gestión de Fotos y Archivos ---
    Route::post('/clients/{client}/photos', [ClientPhotoController::class, 'store'])->name('clients.photos.store');
    Route::delete('/photos/{photo}', [ClientPhotoController::class, 'destroy'])->name('clients.photos.destroy');

    // --- Configuración y Perfil ---
    Route::get('/configuracion', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracion', [SettingController::class, 'update'])->name('settings.update');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

require __DIR__.'/auth.php';