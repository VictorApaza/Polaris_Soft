<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AsignacionApiController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

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
    //return view('welcome');
    return view('estudiantes.index');
});

Route::get('/estudiantes/{estudiante}/asignaciones/create', [AsignacionController::class, 'create'])
    ->whereNumber('estudiante')
    ->name('asignaciones.create');

// Estas rutas usan el middleware web para conservar la sesión y la protección CSRF.
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/materias', [AsignacionApiController::class, 'materias'])->name('materias.index');
    Route::get('/materias/{materia}/grupos', [AsignacionApiController::class, 'grupos'])
        ->whereNumber('materia')
        ->name('materias.grupos');
    Route::get('/grupos/{grupo}/docentes', [AsignacionApiController::class, 'docentes'])
        ->whereNumber('grupo')
        ->name('grupos.docentes');
    Route::post('/estudiantes/{estudiante}/asignaciones', [AsignacionApiController::class, 'store'])
        ->whereNumber('estudiante')
        ->name('estudiantes.asignaciones.store');
});
