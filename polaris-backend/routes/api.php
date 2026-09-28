<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\Estudiante1Controller;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\MateriaController;

Route::get('/estudiantes', [Estudiante1Controller::class, 'index']);
Route::post('/estudiantes', [Estudiante1Controller::class, 'store']);
Route::get('/estudiantes/{id}', [Estudiante1Controller::class, 'show']);
Route::put('/estudiantes/{id}', [Estudiante1Controller::class, 'update']);
Route::delete('/estudiantes/{id}', [Estudiante1Controller::class, 'destroy']);

Route::get('/examenes', [ExamenController::class, 'index']);
Route::post('/examenes', [ExamenController::class, 'store']);
Route::get('/examenes/{id}', [ExamenController::class, 'show']);
Route::put('/examenes/{id}', [ExamenController::class, 'update']);
Route::delete('/examenes/{id}', [ExamenController::class, 'destroy']);

Route::get('/materias', [MateriaController::class, 'index']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
