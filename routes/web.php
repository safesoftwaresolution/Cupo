<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\BarberoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\ReservaPublicaController;
use App\Http\Controllers\ServicioController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Reserva pública de cada barbería. Sin sesión: la barbería sale del `slug`.
Route::middleware('reserva')->prefix('b/{slug}')->name('reserva.')->group(function () {
    Route::get('/', [ReservaPublicaController::class, 'show'])->name('show');
    Route::get('disponibilidad', [ReservaPublicaController::class, 'disponibilidad'])->middleware('throttle:60,1')->name('disponibilidad');
    Route::post('reservar', [ReservaPublicaController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('cita/{codigo}', [ReservaPublicaController::class, 'cita'])->middleware('throttle:60,1')->name('cita');
    Route::post('cita/{codigo}/cancelar', [ReservaPublicaController::class, 'cancelar'])->middleware('throttle:10,1')->name('cancelar');
});

Route::middleware(['auth', 'verified', 'barberia'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Agenda del día: dueño y recepción.
    Route::middleware('rol:dueno,recepcion')->prefix('agenda')->name('agenda.')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('index');
        Route::get('disponibilidad', [AgendaController::class, 'disponibilidad'])->name('disponibilidad');
        Route::post('citas', [AgendaController::class, 'store'])->name('citas.store');
        Route::patch('citas/{cita}/estado', [AgendaController::class, 'estado'])->name('citas.estado');
    });

    // Configuración de la barbería: solo el dueño.
    Route::middleware('rol:dueno')->group(function () {
        Route::resource('barberos', BarberoController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('servicios', ServicioController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('barberos/{barbero}/horario', [HorarioController::class, 'edit'])->name('barberos.horario.edit');
        Route::put('barberos/{barbero}/horario', [HorarioController::class, 'update'])->name('barberos.horario.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
