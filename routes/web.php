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
        'settings'    => \App\Models\Setting::first(),
    ]);
});

// --- 2. RUTAS PROTEGIDAS (REQUIEREN LOGIN Y VERIFICACIÓN) ---
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Recepción Técnica (Formulario y Guardado)
    Route::get('/recepcion/create', [RecepcionController::class, 'create'])->name('recepcion.create');
    Route::post('/recepcion', [RecepcionController::class, 'store'])->name('recepcion.store');

    // Generación de PDF (Esta es la ruta que llama el Modal de Éxito)
    Route::get('/recepcion/pdf/{id}', [RecepcionController::class, 'print'])->name('recepcion.pdf');

    // Gestión de Fotos
    Route::post('/clients/{client}/photos', [ClientPhotoController::class, 'store'])->name('clients.photos.store');
    Route::delete('/photos/{photo}', [ClientPhotoController::class, 'destroy'])->name('clients.photos.destroy');

    // Configuración del Taller
    Route::get('/configuracion', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracion', [SettingController::class, 'update'])->name('settings.update');

    // Perfil de Usuario
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

}); // <--- Aquí cerramos correctamente el grupo de middleware

// --- Gestión de Recepciones (Dashboard Acciones) ---
    Route::get('/recepciones/exportar', [\App\Http\Controllers\RecepcionController::class, 'export'])->name('recepcion.export');
    Route::delete('/recepciones/{id}', [\App\Http\Controllers\RecepcionController::class, 'destroy'])->name('recepcion.destroy');

    Route::get('/recepciones/{id}/editar', [\App\Http\Controllers\RecepcionController::class, 'edit'])->name('recepcion.edit');
require __DIR__.'/auth.php';