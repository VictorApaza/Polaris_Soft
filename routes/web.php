<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AsignacionApiController;
use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\DocenteExamenController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\EstudianteQrController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\HabilitacionController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TransparenciaIngresoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VerificacionController;
use Illuminate\Support\Facades\Route;

// --- Login / Logout ---
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

// --- Recuperación de contraseña ---
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// --- Inicio: login para invitados y dashboard por rol para usuarios autenticados ---
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');
Route::view('/home', 'welcome')->name('welcome');
Route::get('/dashboard', [DashboardController::class, 'redirigir'])->middleware('auth')->name('dashboard');

// --- Administrador ---
Route::middleware(['auth', 'role:administrador'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');

    Route::resource('estudiantes', EstudianteController::class)->except(['create', 'show', 'edit']);
    Route::get('/estudiantes-importar', [EstudianteController::class, 'importarForm'])->name('estudiantes.importar.form');
    Route::post('/estudiantes-importar', [EstudianteController::class, 'importar'])->name('estudiantes.importar');
    Route::get('/estudiantes/{estudiante}/asignaciones/create', [AsignacionController::class, 'create'])
        ->whereNumber('estudiante');

    // Código QR del estudiante (RQ7)
    Route::get('/estudiantes/{estudiante}/qr', [EstudianteQrController::class, 'ver'])->name('estudiantes.qr');

    // Materias, docentes y grupos (RQ27)
    Route::resource('materias', MateriaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('docentes', DocenteController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('grupos', GrupoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('ambientes', AmbienteController::class)->only(['index', 'store', 'update', 'destroy']);

    // Asignar materia + grupo + docente a un estudiante (RQ27)
    Route::get('/estudiantes/{estudiante}/asignaciones', [AsignacionController::class, 'create'])->name('asignaciones.create');
    Route::post('/estudiantes/{estudiante}/asignaciones', [AsignacionController::class, 'store'])->name('asignaciones.store');
    Route::delete('/asignaciones/{asignacion}', [AsignacionController::class, 'destroy'])->name('asignaciones.destroy');

    // Estas rutas conservan la sesión y la protección CSRF de la aplicación.
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

    Route::resource('examenes', ExamenController::class)
        ->parameters(['examenes' => 'examen'])
        ->except(['create', 'show', 'edit']);

    Route::resource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->only(['index', 'store', 'update']);
    Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
});

// --- Habilitar / inhabilitar estudiantes por examen (RQ3 + RQ4): administrador y docente ---
Route::middleware(['auth', 'role:administrador,docente'])->group(function () {
    Route::get('/examenes/{examen}/habilitados', [HabilitacionController::class, 'index'])->name('habilitaciones.index');
    Route::post('/examenes/{examen}/habilitados', [HabilitacionController::class, 'habilitarMasiva'])->name('habilitaciones.masiva');
    Route::post('/examenes/{examen}/habilitados/{estudiante}/inhabilitar', [HabilitacionController::class, 'inhabilitar'])->name('habilitaciones.inhabilitar');
    Route::delete('/examenes/{examen}/habilitados/{estudiante}', [HabilitacionController::class, 'quitar'])->name('habilitaciones.quitar');
    // Enviar por correo el QR a los habilitados de un examen (RQ7)
    Route::post('/examenes/{examen}/habilitados-enviar-qr', [EstudianteQrController::class, 'enviarHabilitados'])->name('habilitaciones.enviar_qr');
});

// Página del QR que abre el estudiante desde el enlace firmado de su correo (sin iniciar sesión).
Route::get('/qr/{estudiante}', [EstudianteQrController::class, 'publico'])->middleware('signed')->name('qr.publico');

// --- Docente ---
Route::middleware(['auth', 'role:docente'])->group(function () {
    Route::get('/docente/dashboard', fn () => view('docente.dashboard'));
    Route::get('/docente/examenes', [DocenteExamenController::class, 'index'])->name('docente.examenes');
});

// --- Personal de control ---
Route::middleware(['auth', 'role:control'])->group(function () {
    Route::redirect('/control/dashboard', '/verificacion');
});

// --- Verificación de ingreso ---
Route::middleware(['auth', 'role:administrador,control'])->group(function () {
    Route::get('/verificacion', [VerificacionController::class, 'index'])->name('verificacion.index');
    Route::post('/verificacion/verificar', [VerificacionController::class, 'verificar'])->name('verificacion.verificar');
    Route::post('/verificacion/ingreso', [VerificacionController::class, 'ingreso'])->name('verificacion.ingreso');

    // Rutas cortas del plan del Sprint 2 (HU-008/009/010).
    Route::post('/verificar/codigo', [VerificacionController::class, 'verificarCodigo'])->name('verificar.codigo');
    Route::post('/verificar/ci', [VerificacionController::class, 'verificarCi'])->name('verificar.ci');
    Route::post('/verificar/qr', [VerificacionController::class, 'verificarQr'])->name('verificar.qr');
});

// Alias antiguo de la verificación: ahora también exige rol (antes cualquier usuario logueado podía usarlo).
Route::middleware(['auth', 'role:administrador,control'])->group(function () {
    Route::get('/control-ingreso', [VerificacionController::class, 'index'])->name('ingreso.index');
    Route::post('/control-ingreso/verificar', [VerificacionController::class, 'verificar'])->name('ingreso.verificar');
    Route::post('/control-ingreso/ingreso', [VerificacionController::class, 'ingreso'])->name('ingreso.ingreso');
});

// --- Transparencia de ingreso (RQ8) ---
// Bitácora inmutable de autorizaciones: auditoría en tiempo real (solo lectura) y registro de la autorización.
Route::middleware(['auth', 'role:administrador,control'])->group(function () {
    Route::get('/transparencia', [TransparenciaIngresoController::class, 'index'])->name('transparencia.index');
    Route::get('/transparencia/datos', [TransparenciaIngresoController::class, 'datos'])->name('transparencia.datos');
    Route::post('/transparencia/autorizar', [TransparenciaIngresoController::class, 'registrar'])->name('transparencia.registrar');
});
