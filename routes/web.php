<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientPhotoController;
use App\Http\Controllers\RecepcionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- 1. PANTALLA DE BIENVENIDA (PÚBLICA) ---
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
        'settings'    => \App\Models\Setting::first(), // Se envía como 'settings' (plural)
    ]);
});

// --- 2. RUTAS PROTEGIDAS (REQUIEREN LOGIN) ---
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Recepción Técnica (Formulario de alta)
    Route::get('/recepcion/create', [RecepcionController::class, 'create'])->name('recepcion.create');
    Route::post('/recepcion', [RecepcionController::class, 'store'])->name('recepcion.store');

    // Impresión y Gestión de Clientes/Fotos
    Route::get('/recepcion/{id}/imprimir', [ClientController::class, 'print'])->name('recepcion.print');
    Route::post('/clients/{client}/photos', [ClientPhotoController::class, 'store'])->name('clients.photos.store');
    Route::delete('/photos/{photo}', [ClientPhotoController::class, 'destroy'])->name('clients.photos.destroy');

    // Configuración del Taller
    Route::get('/configuracion', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracion', [SettingController::class, 'update'])->name('settings.update');

    // Perfil de Usuario
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['auth'])->group(function () {
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});
});

require __DIR__.'/auth.php';