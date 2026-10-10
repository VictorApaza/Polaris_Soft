<?php

use App\Http\Controllers\Api\HabilitacionApiController;
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
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'loginApi']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// RQ3: habilitación de estudiantes para un examen (carga individual y masiva).
// Protegidas: hace falta un token de API (Authorization: Bearer ...) de un usuario administrador.
Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {
    Route::post('/habilitaciones', [HabilitacionApiController::class, 'store']);
    Route::post('/habilitaciones/masiva', [HabilitacionApiController::class, 'masiva']);
    Route::get('/habilitaciones/examen/{id}', [HabilitacionApiController::class, 'porExamen']);
});
