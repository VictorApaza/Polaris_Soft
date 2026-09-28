<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Estudiante1Controller;
use App\Http\Controllers\ExamenController;

// RUTAS DE ESTUDIANTES HU1

Route::get('/estudiantes', [Estudiante1Controller::class, 'index']);
Route::post('/estudiantes', [Estudiante1Controller::class, 'store']);
Route::get('/estudiantes/{id}', [Estudiante1Controller::class, 'show']);
Route::put('/estudiantes/{id}', [Estudiante1Controller::class, 'update']);
Route::delete('/estudiantes/{id}', [Estudiante1Controller::class, 'destroy']);


// RUTAS DE EXAMENES HU3

Route::get('/examenes', [ExamenController::class, 'index']);
Route::post('/examenes', [ExamenController::class, 'store']);
Route::get('/examenes/{id}', [ExamenController::class, 'show']);
Route::put('/examenes/{id}', [ExamenController::class, 'update']);
Route::delete('/examenes/{id}', [ExamenController::class, 'destroy']);

// --- Login / Logout ---
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// --- Paneles según rol (ejemplo, ajusta las vistas a las que ya tengan) ---
Route::middleware(['auth', 'role:administrador'])->group(function () {
    Route::get('/admin/dashboard', fn () => view('admin.dashboard'));
});

Route::middleware(['auth', 'role:docente'])->group(function () {
    Route::get('/docente/dashboard', fn () => view('docente.dashboard'));
});

Route::middleware(['auth', 'role:control'])->group(function () {
    Route::get('/control/dashboard', fn () => view('control.dashboard'));
});

Route::get('/', function () {
    return view('estudiantes.index');
})->name('estudiantes.index');