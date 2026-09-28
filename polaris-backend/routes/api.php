<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Estudiante1Controller;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [AuthController::class, 'me']);

});


/*
|--------------------------------------------------------------------------
| Estudiantes
|--------------------------------------------------------------------------
*/

Route::get('/estudiantes', [Estudiante1Controller::class, 'index']);
Route::post('/estudiantes', [Estudiante1Controller::class, 'store']);
Route::get('/estudiantes/{id}', [Estudiante1Controller::class, 'show']);
Route::put('/estudiantes/{id}', [Estudiante1Controller::class, 'update']);
Route::delete('/estudiantes/{id}', [Estudiante1Controller::class, 'destroy']);


/*
|--------------------------------------------------------------------------
| Exámenes
|--------------------------------------------------------------------------
*/

Route::get('/examenes', [ExamenController::class, 'index']);
Route::post('/examenes', [ExamenController::class, 'store']);
Route::get('/examenes/{id}', [ExamenController::class, 'show']);
Route::put('/examenes/{id}', [ExamenController::class, 'update']);
Route::delete('/examenes/{id}', [ExamenController::class, 'destroy']);


/*
|--------------------------------------------------------------------------
| Materias
|--------------------------------------------------------------------------
*/

Route::get('/materias', [MateriaController::class, 'index']);
Route::post('/materias', [MateriaController::class, 'store']);
Route::get('/materias/{id}', [MateriaController::class, 'show']);
Route::put('/materias/{id}', [MateriaController::class, 'update']);
Route::delete('/materias/{id}', [MateriaController::class, 'destroy']);


/*
|--------------------------------------------------------------------------
| Docentes
|--------------------------------------------------------------------------
*/

Route::get('/docentes', [DocenteController::class, 'index']);
Route::post('/docentes', [DocenteController::class, 'store']);
Route::get('/docentes/{id}', [DocenteController::class, 'show']);
Route::put('/docentes/{id}', [DocenteController::class, 'update']);
Route::delete('/docentes/{id}', [DocenteController::class, 'destroy']);


/*
|--------------------------------------------------------------------------
| Grupos
|--------------------------------------------------------------------------
*/

Route::get('/grupos', [GrupoController::class, 'index']);
Route::post('/grupos', [GrupoController::class, 'store']);
Route::get('/grupos/{id}', [GrupoController::class, 'show']);
Route::put('/grupos/{id}', [GrupoController::class, 'update']);
Route::delete('/grupos/{id}', [GrupoController::class, 'destroy']);


/*
|--------------------------------------------------------------------------
| Asignaciones
|--------------------------------------------------------------------------
*/

Route::get('/asignaciones', [AsignacionController::class, 'index']);
Route::post('/asignaciones', [AsignacionController::class, 'store']);
Route::get('/asignaciones/{id}', [AsignacionController::class, 'show']);
Route::put('/asignaciones/{id}', [AsignacionController::class, 'update']);
Route::delete('/asignaciones/{id}', [AsignacionController::class, 'destroy']);