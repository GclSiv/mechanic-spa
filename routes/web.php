<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\RepairOrderController;
use App\Http\Controllers\RepairOrderItemController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientPhotoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Setting;

// --- 1. PANTALLA DE BIENVENIDA ---
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'settings' => Setting::first(),
    ]);
});

// --- 2. RUTAS PROTEGIDAS (JK AUTOMOTIVE) ---
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Módulo de Recepción Técnica
    Route::prefix('recepcion')->group(function () {
        Route::get('/create', [RecepcionController::class, 'create'])->name('recepcion.create');
        Route::post('/', [RecepcionController::class, 'store'])->name('recepcion.store');
        Route::get('/pdf/{id}', [RecepcionController::class, 'print'])->name('recepcion.pdf');
        Route::get('/{id}/editar', [RecepcionController::class, 'edit'])->name('recepcion.edit');
        Route::delete('/{id}', [RecepcionController::class, 'destroy'])->name('recepcion.destroy');
    });

    // Gestión de Órdenes y Cotización (Botón 💰)
    Route::get('/repair-orders/{id}', [RepairOrderController::class, 'show'])->name('repair-orders.show');
    Route::post('/repair-order-items', [RepairOrderItemController::class, 'store'])->name('repair-order-items.store');
    Route::get('/cotizacion/pdf/{id}', [RepairOrderItemController::class, 'descargarCotizacion'])->name('cotizacion.pdf');

    // Configuración y Perfil
    Route::get('/configuracion', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracion', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

//require __DIR__.'/auth.php';