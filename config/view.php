<?php

$compiled = env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views')));
if (env('VIEW_CACHE_PRIVATE_TEMP', false)) {
    // En alojamiento compartido PHP puede no ser el propietario de las carpetas FTP.
    // Solo las vistas regenerables van al temporal; sesiones y datos permanecen en BD.
    $compiled = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).'/sciem-views-'
        .hash('sha256', base_path().env('APP_KEY'));
    if (! is_dir($compiled) && ! mkdir($compiled, 0700, true) && ! is_dir($compiled)) {
        throw new RuntimeException('No se pudo crear la carpeta privada de vistas.');
    }
    if (is_link($compiled) || (fileperms($compiled) & 0077) !== 0
        || (function_exists('posix_geteuid') && fileowner($compiled) !== posix_geteuid())) {
        throw new RuntimeException('La carpeta de vistas no tiene un propietario y permisos privados válidos.');
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    'compiled' => $compiled,

];
