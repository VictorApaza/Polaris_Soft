<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// --- Login / Logout ---
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// --- Inicio: siempre pasa por el login / dashboard según rol ---
Route::redirect('/', '/dashboard');
Route::get('/dashboard', [DashboardController::class, 'redirigir'])->middleware('auth')->name('dashboard');

// --- Administrador ---
Route::middleware(['auth', 'role:administrador'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');

    Route::resource('estudiantes', EstudianteController::class)->except(['create', 'show', 'edit']);
    Route::get('/estudiantes/{estudiante}/asignaciones/create', [AsignacionController::class, 'create'])
        ->whereNumber('estudiante');

    // Materias, docentes y grupos (RQ27)
    Route::resource('materias', MateriaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('docentes', DocenteController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('grupos', GrupoController::class)->only(['index', 'store', 'update', 'destroy']);

    // Asignar materia + grupo + docente a un estudiante (RQ27)
    Route::get('/estudiantes/{estudiante}/asignaciones', [AsignacionController::class, 'create'])->name('asignaciones.create');
    Route::post('/estudiantes/{estudiante}/asignaciones', [AsignacionController::class, 'store'])->name('asignaciones.store');
    Route::delete('/asignaciones/{asignacion}', [AsignacionController::class, 'destroy'])->name('asignaciones.destroy');

    Route::resource('examenes', ExamenController::class)
        ->parameters(['examenes' => 'examen'])
        ->except(['create', 'show', 'edit']);

    Route::resource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->only(['index', 'store', 'update']);
    Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
});

// --- Docente ---
Route::middleware(['auth', 'role:docente'])->group(function () {
    Route::get('/docente/dashboard', fn () => view('docente.dashboard'));
});

// --- Personal de control ---
Route::middleware(['auth', 'role:control'])->group(function () {
    Route::get('/control/dashboard', fn () => view('control.dashboard'));
});
