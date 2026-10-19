<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$source = realpath($argv[1] ?? '');
$commit = $argv[2] ?? '';
if (! $source || ! is_file($source.'/source.zip') || ! preg_match('/^[a-f0-9]{40}$/', $commit)) {
    throw new RuntimeException('Uso: php deployment/build-package.php DIRECTORIO_RELEASE COMMIT_COMPLETO');
}
if (file_exists($source.'/application.zip')) {
    throw new RuntimeException('La entrega ya existe; utiliza otro identificador para conservarla.');
}

$stage = sys_get_temp_dir().'/polaris-package-'.bin2hex(random_bytes(8));
mkdir($stage, 0755, true);
$archive = new ZipArchive;
if ($archive->open($source.'/source.zip') !== true) {
    throw new RuntimeException('No se pudo abrir el código exportado.');
}
$archive->extractTo($stage);
$archive->close();
rename($stage.'/public', $stage.'/public_html');
foreach (['storage/app/public', 'storage/logs', 'storage/framework/cache/data',
    'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'] as $directory) {
    if (! is_dir($stage.'/'.$directory)) {
        mkdir($stage.'/'.$directory, 0755, true);
    }
}
copy(__DIR__.'/env.webtis.example', $stage.'/env.webtis.example');
file_put_contents($stage.'/env.webtis.example', str_replace('VERSION_RELEASE', basename($source),
    file_get_contents($stage.'/env.webtis.example')));
if (file_exists($stage.'/.env.example')) {
    unlink($stage.'/.env.example');
}

$process = proc_open(['composer', 'install', '--working-dir='.$stage, '--no-dev', '--prefer-dist',
    '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-ansi'],
    [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
if (! is_resource($process) || proc_close($process) !== 0) {
    throw new RuntimeException('Falló la instalación de dependencias de producción.');
}

// Las cachés de rutas, configuración y vistas deben corresponder al servidor definitivo.
foreach (glob($stage.'/bootstrap/cache/*.php') as $cache) {
    if (! in_array(basename($cache), ['packages.php', 'services.php'], true)) {
        unlink($cache);
    }
}
$manifest = ['version' => basename($source), 'commit' => $commit, 'php_build' => PHP_VERSION,
    'created_at_utc' => gmdate(DATE_ATOM), 'layout' => 'public_html',
    'migrations' => array_map('basename', glob($stage.'/database/migrations/*.php'))];
file_put_contents($stage.'/release.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$zip = new ZipArchive;
if ($zip->open($source.'/application.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('No se pudo crear el paquete.');
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST);
foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($stage) + 1);
    if ($file->isDir()) {
        $zip->addEmptyDir($relative);
    } else {
        $zip->addFile($file->getPathname(), $relative);
    }
}
$zip->close();
$manifest['application_sha256'] = hash_file('sha256', $source.'/application.zip');
file_put_contents($source.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Paquete preparado con dependencias de producción, public_html y manifiesto SHA-256.'.PHP_EOL;
