<?php

// Se ejecuta solo por CLI, en la base local vacía utilizada para preparar la demostración.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (config('database.connections.mysql.host') !== 'db'
    || config('database.connections.mysql.database') !== 'polaris_demo') {
    throw new RuntimeException('Este preparador solo admite la base local polaris_demo del entorno Docker.');
}
if (User::exists()) {
    throw new RuntimeException('La base ya contiene usuarios. Se conserva sin ejecutar los seeders ni cambiar contraseñas.');
}

$accesos = DB::transaction(function () {
    Artisan::call('db:seed', ['--force' => true]);
    User::create(['name' => 'Control Demo Uno', 'nombre' => 'Control', 'apellido' => 'Demo Uno',
        'email' => 'control.uno@polaris.test', 'rol' => 'control', 'estado' => 'activo', 'password' => Str::random(32)]);
    User::create(['name' => 'Control Demo Dos', 'nombre' => 'Control', 'apellido' => 'Demo Dos',
        'email' => 'control.dos@polaris.test', 'rol' => 'control', 'estado' => 'activo', 'password' => Str::random(32)]);

    return User::all()->map(function (User $usuario) {
        $password = Str::random(24);
        $usuario->password = $password;
        $usuario->save();

        return ['email' => $usuario->email, 'rol' => $usuario->rol,
            'estado' => $usuario->estado, 'password' => $password];
    })->all();
});

$path = storage_path('app/demo-access.json');
file_put_contents($path, json_encode($accesos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
chmod($path, 0600);
echo 'Datos ficticios preparados. Accesos guardados en storage/app/demo-access.json, fuera del paquete público.'.PHP_EOL;
