<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
$destination = realpath($argv[1] ?? '');
if ($config['host'] !== 'db' || $config['database'] !== 'polaris_demo'
    || ! $destination || ! is_file($destination.'/application.zip')) {
    throw new RuntimeException('Solo se exporta la base ficticia local dentro de una entrega preparada.');
}
$file = $destination.'/initial-database.sql';
if (file_exists($file)) {
    throw new RuntimeException('La exportación ya existe; se conserva sin sobrescribir.');
}
$command = ['mariadb-dump', '--no-defaults', '--host='.$config['host'], '--user='.$config['username'],
    '--single-transaction', '--skip-add-drop-table', '--skip-add-locks', '--skip-disable-keys',
    '--skip-comments', '--skip-dump-date', '--hex-blob', '--triggers',
    '--ignore-table-data=polaris_demo.password_reset_tokens', '--ignore-table-data=polaris_demo.failed_jobs',
    $config['database']];
$process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $file, 'x'],
    2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['MYSQL_PWD' => $config['password']]));
if (! is_resource($process)) {
    throw new RuntimeException('No se pudo iniciar la exportación.');
}
$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Falló la exportación; revisa el servicio local de base de datos.');
}
$sql = file_get_contents($file);
// El servidor de destino debe crear los triggers con su propio usuario.
$sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $sql);
if (preg_match('/\b(?:DROP\s+(?:TABLE|DATABASE)|CREATE\s+DATABASE|USE\s+`|DEFINER\s*=)/i', $sql)) {
    throw new RuntimeException('La exportación contiene instrucciones no admitidas para una instalación inicial.');
}
file_put_contents($file, $sql);
chmod($file, 0600);
$manifestPath = $destination.'/manifest.json';
$manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$manifest['initial_database_sha256'] = hash_file('sha256', $file);
$manifest['initial_database_only'] = true;
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Exportación inicial privada preparada; sin contraseñas de conexión ni tokens de recuperación.'.PHP_EOL;
