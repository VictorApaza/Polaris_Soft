# RQ8 · Cambios hechos en archivos que ya existían

Solo se tocaron 2 archivos existentes (el resto son archivos nuevos). Si al integrar hay conflicto, estos son los fragmentos exactos.

## 1) `routes/web.php`

Agregar el `use` junto a los demás controladores (por ejemplo debajo de `PasswordResetController`):

```php
use App\Http\Controllers\TransparenciaIngresoController;
```

Y agregar **al final del archivo**:

```php
// --- Transparencia de ingreso (RQ8) ---
// Bitácora inmutable de autorizaciones: auditoría en tiempo real (solo lectura) y registro de la autorización.
Route::middleware(['auth', 'role:administrador,control'])->group(function () {
    Route::get('/transparencia', [TransparenciaIngresoController::class, 'index'])->name('transparencia.index');
    Route::get('/transparencia/datos', [TransparenciaIngresoController::class, 'datos'])->name('transparencia.datos');
    Route::post('/transparencia/autorizar', [TransparenciaIngresoController::class, 'registrar'])->name('transparencia.registrar');
});
```

## 2) `resources/views/layouts/app.blade.php`

En el `<nav class="nav">`, justo **después** del bloque `@if ($es('administrador')) ... @endif` (después del enlace "Usuarios") y **antes** de `</nav>`:

```blade
            @if ($es('administrador', 'control'))
                <a href="{{ route('transparencia.index') }}" @class(['active' => request()->routeIs('transparencia.*')])>
                    <svg class="i"><use href="#i-shield"/></svg> Transparencia</a>
            @endif
```
