<?php
// SOLUCIÓN 3: esta es la causa exacta del error que viste. La vista
// index.blade.php usa route('estudiantes.index'), route('estudiantes.create'),
// route('estudiantes.edit', $e), route('estudiantes.destroy', $e), etc.,
// pero antes ninguna de esas rutas estaba registrada. Con esta única línea
// (Route::resource) Laravel crea automáticamente las 7 rutas del CRUD con
// esos nombres exactos: estudiantes.index, .create, .store, .show, .edit,
// .update, .destroy.

use App\Http\Controllers\EstudianteController;
use Illuminate\Support\Facades\Route;

Route::resource('estudiantes', EstudianteController::class);

Route::get('/', function () {
    return redirect()->route('estudiantes.index');
});
