<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Sprint 5 · RQ26: respaldo (backup) y restauración de la información registrada.
 *
 * Un respaldo es UN archivo .zip en storage/app/backups que contiene:
 *   - manifest.json        → fecha, quién lo hizo, tablas y cantidad de filas de cada una (sirve para verificar al restaurar)
 *   - base_de_datos.sql    → toda la base (MySQL): estructura, datos y los triggers de inmutabilidad (RQ8/RQ11)
 *     base_de_datos.sqlite → (solo si el sistema corre sobre SQLite, p. ej. en desarrollo)
 *   - archivos/…           → fotos de estudiantes y evidencias de incidencias (carpetas de config('sciem.respaldo_carpetas'))
 * Junto al .zip se guarda un .sha256 con su huella para detectar archivos dañados o alterados.
 *
 * No depende de mysqldump: arma el volcado con PDO, así funciona igual en el servidor de la TIS.
 */
class RespaldoService
{
    public const DIRECTORIO = 'backups';

    /** Línea que separa una sentencia SQL de la siguiente dentro de base_de_datos.sql. */
    private const SEPARADOR = '-- @@FIN@@';

    public const TIPOS = [
        'manual' => 'Manual',
        'automatico' => 'Automático',
        'previo' => 'Previo a restauración',
    ];

    // ------------------------------------------------------------------
    //  Crear
    // ------------------------------------------------------------------

    /**
     * Genera un respaldo completo y devuelve sus datos (los mismos de listar()).
     *
     * @throws RuntimeException si no se puede escribir el archivo o el motor de base de datos no es compatible
     */
    public function crear(string $tipo = 'manual', ?User $usuario = null, ?bool $conArchivos = null): array
    {
        $tipo = array_key_exists($tipo, self::TIPOS) ? $tipo : 'manual';
        $conArchivos ??= (bool) config('sciem.respaldo_con_archivos', true);

        return $this->conCerrojo(function () use ($tipo, $usuario, $conArchivos) {
            @set_time_limit(0);
            $disco = Storage::disk('local');
            $disco->makeDirectory(self::DIRECTORIO);

            $zona = config('sciem.zona_horaria');
            $ahora = Carbon::now($zona);
            $nombre = 'respaldo-'.$ahora->format('Ymd-His').'-'.$tipo;
            $ruta = $disco->path(self::DIRECTORIO.'/'.$nombre.'.zip');
            $tmpBd = tempnam(sys_get_temp_dir(), 'bd');

            try {
                $driver = DB::connection()->getDriverName();
                $tablas = match ($driver) {
                    'mysql', 'mariadb' => $this->volcarMysql($tmpBd),
                    'sqlite' => $this->copiarSqlite($tmpBd),
                    default => throw new RuntimeException("El respaldo no soporta el motor de base de datos «{$driver}»."),
                };

                $zip = new ZipArchive();
                if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('No se pudo crear el archivo del respaldo en storage/app/'.self::DIRECTORIO.'.');
                }

                $zip->addFile($tmpBd, $driver === 'sqlite' ? 'base_de_datos.sqlite' : 'base_de_datos.sql');

                $cantidadArchivos = 0;
                if ($conArchivos) {
                    foreach ((array) config('sciem.respaldo_carpetas', ['fotos_estudiantes', 'incidencias']) as $carpeta) {
                        foreach ($disco->allFiles($carpeta) as $archivo) {
                            $zip->addFile($disco->path($archivo), 'archivos/'.$archivo);
                            $cantidadArchivos++;
                        }
                    }
                }

                $zip->addFromString('manifest.json', json_encode([
                    'sistema' => 'SCIEM',
                    'version' => 1,
                    'creado_en' => $ahora->toIso8601String(),
                    'zona_horaria' => $zona,
                    'tipo' => $tipo,
                    'creado_por' => $usuario ? ['id' => $usuario->id, 'nombre' => $usuario->name] : null,
                    'motor' => $driver,
                    'base_de_datos' => DB::connection()->getDatabaseName() ? basename((string) DB::connection()->getDatabaseName()) : null,
                    'tablas' => $tablas,
                    'total_tablas' => count($tablas),
                    'total_filas' => array_sum($tablas),
                    'con_archivos' => $conArchivos,
                    'total_archivos' => $cantidadArchivos,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

                if (! $zip->close()) {
                    throw new RuntimeException('No se pudo terminar de escribir el respaldo (¿disco lleno?).');
                }
            } catch (Throwable $e) {
                @unlink($ruta);
                throw $e instanceof RuntimeException ? $e : new RuntimeException('No se pudo crear el respaldo: '.$e->getMessage(), 0, $e);
            } finally {
                @unlink($tmpBd);
            }

            $disco->put(self::DIRECTORIO.'/'.$nombre.'.zip.sha256', hash_file('sha256', $ruta));

            return $this->detalle($nombre);
        });
    }

    // ------------------------------------------------------------------
    //  Consultar
    // ------------------------------------------------------------------

    /** Respaldos guardados, el más reciente primero. */
    public function listar(): array
    {
        $disco = Storage::disk('local');
        if (! $disco->exists(self::DIRECTORIO)) {
            return [];
        }

        $lista = [];
        foreach ($disco->files(self::DIRECTORIO) as $archivo) {
            if (preg_match('/^'.preg_quote(self::DIRECTORIO, '/').'\/(respaldo-\d{8}-\d{6}(?:-[a-z]+)?)\.zip$/', $archivo, $m)) {
                $lista[] = $this->detalle($m[1]);
            }
        }

        usort($lista, fn ($a, $b) => strcmp($b['nombre'], $a['nombre']));

        return $lista;
    }

    public function existe(string $nombre): bool
    {
        return $this->nombreValido($nombre) && Storage::disk('local')->exists($this->rutaRelativa($nombre));
    }

    public function rutaAbsoluta(string $nombre): string
    {
        abort_unless($this->existe($nombre), 404, 'No existe ese respaldo.');

        return Storage::disk('local')->path($this->rutaRelativa($nombre));
    }

    public function nombreValido(string $nombre): bool
    {
        return (bool) preg_match('/^respaldo-\d{8}-\d{6}(?:-[a-z]+)?$/', $nombre);
    }

    /** Datos de un respaldo para mostrar en pantalla. */
    public function detalle(string $nombre): array
    {
        $disco = Storage::disk('local');
        $rel = $this->rutaRelativa($nombre);
        $manifiesto = $this->leerManifiesto($disco->path($rel)) ?? [];

        return [
            'nombre' => $nombre,
            'archivo' => $nombre.'.zip',
            'bytes' => $disco->exists($rel) ? (int) $disco->size($rel) : 0,
            'tipo' => $manifiesto['tipo'] ?? 'manual',
            'tipo_etiqueta' => self::TIPOS[$manifiesto['tipo'] ?? 'manual'] ?? 'Manual',
            'creado_en' => isset($manifiesto['creado_en']) ? Carbon::parse($manifiesto['creado_en'])->timezone(config('sciem.zona_horaria')) : null,
            'creado_por' => $manifiesto['creado_por']['nombre'] ?? null,
            'motor' => $manifiesto['motor'] ?? null,
            'tablas' => (int) ($manifiesto['total_tablas'] ?? 0),
            'filas' => (int) ($manifiesto['total_filas'] ?? 0),
            'archivos' => (int) ($manifiesto['total_archivos'] ?? 0),
            'legible' => $manifiesto !== [],
        ];
    }

    // ------------------------------------------------------------------
    //  Verificar integridad (sin tocar la base)
    // ------------------------------------------------------------------

    /**
     * Comprueba que el .zip no esté dañado ni alterado: huella SHA-256, estructura del zip y manifiesto.
     *
     * @return array{ok:bool,mensajes:array<int,string>}
     */
    public function verificar(string $nombre): array
    {
        abort_unless($this->existe($nombre), 404, 'No existe ese respaldo.');
        $disco = Storage::disk('local');
        $ruta = $disco->path($this->rutaRelativa($nombre));
        $mensajes = [];
        $ok = true;

        $huellaGuardada = $disco->exists($this->rutaRelativa($nombre).'.sha256') ? trim($disco->get($this->rutaRelativa($nombre).'.sha256')) : null;
        $huellaActual = hash_file('sha256', $ruta);
        if ($huellaGuardada === null) {
            $mensajes[] = 'No se encontró la huella SHA-256 del respaldo (se verifica solo su contenido).';
        } elseif (! hash_equals($huellaGuardada, $huellaActual)) {
            $ok = false;
            $mensajes[] = 'La huella SHA-256 no coincide: el archivo se dañó o fue modificado después de crearse.';
        } else {
            $mensajes[] = 'La huella SHA-256 coincide con la registrada al crear el respaldo.';
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CHECKCONS) !== true) {
            return ['ok' => false, 'mensajes' => array_merge($mensajes, ['El archivo .zip está dañado y no se puede abrir.'])];
        }

        $manifiesto = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifiesto)) {
            $ok = false;
            $mensajes[] = 'Falta el manifiesto (manifest.json) o está dañado.';
        } else {
            $esperado = ($manifiesto['motor'] ?? '') === 'sqlite' ? 'base_de_datos.sqlite' : 'base_de_datos.sql';
            if ($zip->locateName($esperado) === false) {
                $ok = false;
                $mensajes[] = "Falta el archivo {$esperado} dentro del respaldo.";
            } else {
                $mensajes[] = 'Contiene la base de datos: '.($manifiesto['total_tablas'] ?? 0).' tabla(s) y '.number_format((int) ($manifiesto['total_filas'] ?? 0), 0, ',', '.').' fila(s).';
            }

            $archivos = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_starts_with((string) $zip->getNameIndex($i), 'archivos/')) {
                    $archivos++;
                }
            }
            if ($archivos !== (int) ($manifiesto['total_archivos'] ?? 0)) {
                $ok = false;
                $mensajes[] = "El manifiesto dice {$manifiesto['total_archivos']} archivo(s) y el zip trae {$archivos}.";
            } else {
                $mensajes[] = "Contiene {$archivos} archivo(s) (fotos y evidencias).";
            }
        }
        $zip->close();

        return ['ok' => $ok, 'mensajes' => $mensajes];
    }

    // ------------------------------------------------------------------
    //  Restaurar
    // ------------------------------------------------------------------

    /**
     * Restaura la base de datos (y los archivos) desde un respaldo y comprueba que cada tabla quedó
     * con las mismas filas que tenía al respaldarse.
     *
     * Antes de tocar nada verifica el respaldo y guarda una copia "previa" del estado actual, por si hay que volver atrás.
     *
     * @return array{ok:bool,previo:string,tablas:int,filas:int,archivos:int,diferencias:array<int,string>}
     */
    public function restaurar(string $nombre, ?User $usuario = null): array
    {
        $verificacion = $this->verificar($nombre);
        if (! $verificacion['ok']) {
            throw new RuntimeException('El respaldo no pasó la verificación: '.implode(' ', $verificacion['mensajes']));
        }

        // El respaldo previo toma su propio cerrojo, por eso se crea antes de tomar el de la restauración.
        $previo = $this->crear('previo', $usuario);

        return $this->conCerrojo(function () use ($nombre, $previo) {
            @set_time_limit(0);
            $zip = new ZipArchive();
            $zip->open($this->rutaAbsoluta($nombre));
            $manifiesto = json_decode((string) $zip->getFromName('manifest.json'), true);
            $driver = DB::connection()->getDriverName();

            if (($manifiesto['motor'] ?? '') !== $driver && ! (in_array($driver, ['mysql', 'mariadb'], true) && in_array($manifiesto['motor'] ?? '', ['mysql', 'mariadb'], true))) {
                $zip->close();
                throw new RuntimeException("El respaldo es de una base «{$manifiesto['motor']}» y el sistema usa «{$driver}»: no se puede restaurar aquí.");
            }

            try {
                if ($driver === 'sqlite') {
                    $this->restaurarSqlite($zip);
                } else {
                    $this->restaurarMysql($zip);
                }
                $archivos = $this->restaurarArchivos($zip);
            } finally {
                $zip->close();
            }

            $diferencias = $this->compararConteos($manifiesto['tablas'] ?? []);

            return [
                'ok' => $diferencias === [],
                'previo' => $previo['nombre'],
                'tablas' => count($manifiesto['tablas'] ?? []),
                'filas' => (int) ($manifiesto['total_filas'] ?? 0),
                'archivos' => $archivos,
                'diferencias' => $diferencias,
            ];
        });
    }

    // ------------------------------------------------------------------
    //  Eliminar y retención
    // ------------------------------------------------------------------

    public function eliminar(string $nombre): void
    {
        abort_unless($this->existe($nombre), 404, 'No existe ese respaldo.');
        $rel = $this->rutaRelativa($nombre);
        Storage::disk('local')->delete([$rel, $rel.'.sha256']);
    }

    /**
     * Borra los respaldos AUTOMÁTICOS más viejos que $dias (siempre conserva los 3 últimos).
     * Los manuales y los previos a una restauración nunca se borran solos.
     */
    public function aplicarRetencion(?int $dias = null): int
    {
        $dias ??= (int) config('sciem.respaldo_conservar_dias', 14);
        if ($dias <= 0) {
            return 0;
        }

        $limite = Carbon::now()->subDays($dias);
        $automaticos = array_values(array_filter($this->listar(), fn ($r) => $r['tipo'] === 'automatico'));
        $borrados = 0;

        foreach (array_slice($automaticos, 3) as $r) {
            if ($r['creado_en'] && $r['creado_en']->lt($limite)) {
                $this->eliminar($r['nombre']);
                $borrados++;
            }
        }

        return $borrados;
    }

    // ==================================================================
    //  MySQL / MariaDB
    // ==================================================================

    /** Escribe el volcado en $destino y devuelve [tabla => filas]. */
    private function volcarMysql(string $destino): array
    {
        $pdo = DB::connection()->getPdo();
        $fh = fopen($destino, 'wb');
        if (! $fh) {
            throw new RuntimeException('No se pudo escribir el volcado temporal.');
        }

        $sentencia = function (string $sql) use ($fh) {
            fwrite($fh, rtrim($sql, ";\n ").";\n".self::SEPARADOR."\n");
        };

        fwrite($fh, "-- SCIEM · respaldo de la base de datos · ".now()->toDateTimeString()."\n");
        $sentencia('SET NAMES utf8mb4');
        $sentencia('SET FOREIGN_KEY_CHECKS=0');
        $sentencia('SET UNIQUE_CHECKS=0');
        $sentencia("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

        $conteos = [];
        $tablas = array_map(fn ($f) => array_values((array) $f)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
        sort($tablas);

        foreach ($tablas as $tabla) {
            $id = $this->ident($tabla);
            $crear = (array) DB::selectOne("SHOW CREATE TABLE {$id}");
            $sentencia("DROP TABLE IF EXISTS {$id}");
            $sentencia($crear['Create Table']);

            $total = 0;
            $lote = [];
            $columnas = null;
            foreach (DB::table($tabla)->cursor() as $fila) {
                $fila = (array) $fila;
                $columnas ??= implode(', ', array_map(fn ($c) => $this->ident($c), array_keys($fila)));
                $lote[] = '('.implode(', ', array_map(fn ($v) => $this->valorSql($pdo, $v), array_values($fila))).')';
                $total++;
                if (count($lote) >= 100) {
                    $sentencia("INSERT INTO {$id} ({$columnas}) VALUES\n".implode(",\n", $lote));
                    $lote = [];
                }
            }
            if ($lote) {
                $sentencia("INSERT INTO {$id} ({$columnas}) VALUES\n".implode(",\n", $lote));
            }
            $conteos[$tabla] = $total;
        }

        // Triggers al final (los de ingreso y transparencia_ingreso impiden UPDATE/DELETE; el INSERT no les afecta).
        try {
            foreach (DB::select('SHOW TRIGGERS') as $t) {
                $t = (array) $t;
                $def = (array) DB::selectOne('SHOW CREATE TRIGGER '.$this->ident($t['Trigger']));
                $sql = $def['SQL Original Statement'] ?? null;
                if ($sql) {
                    $sentencia('DROP TRIGGER IF EXISTS '.$this->ident($t['Trigger']));
                    fwrite($fh, "DELIMITER ;;\n".preg_replace('/^CREATE\s+DEFINER=\S+\s+TRIGGER/i', 'CREATE TRIGGER', trim($sql)).";;\nDELIMITER ;\n".self::SEPARADOR."\n");
                }
            }
        } catch (Throwable $e) {
            fwrite($fh, '-- No se pudieron leer los triggers: '.str_replace(["\n", "\r"], ' ', $e->getMessage())."\n");
        }

        $sentencia('SET FOREIGN_KEY_CHECKS=1');
        fclose($fh);

        return $conteos;
    }

    private function restaurarMysql(ZipArchive $zip): void
    {
        $flujo = $zip->getStream('base_de_datos.sql');
        if (! $flujo) {
            throw new RuntimeException('El respaldo no trae base_de_datos.sql.');
        }

        $pdo = DB::connection()->getPdo();
        $modoSql = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $bloque = '';
        try {
            while (($linea = fgets($flujo)) !== false) {
                if (rtrim($linea) === self::SEPARADOR) {
                    $this->ejecutarBloque($pdo, $bloque);
                    $bloque = '';

                    continue;
                }
                $bloque .= $linea;
            }
            $this->ejecutarBloque($pdo, $bloque);
        } finally {
            fclose($flujo);
            // El volcado apaga la revisión de claves foráneas; se vuelve a encender pase lo que pase.
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                $pdo->exec('SET UNIQUE_CHECKS=1');
                $pdo->exec('SET SQL_MODE='.$pdo->quote($modoSql));
            } catch (Throwable) {
            }
        }
    }

    private function ejecutarBloque(PDO $pdo, string $bloque): void
    {
        $sql = trim(preg_replace('/^(--[^\n]*\n)+/', '', ltrim($bloque)) ?? '');
        if ($sql === '') {
            return;
        }
        if (str_starts_with($sql, 'DELIMITER')) {
            $sql = trim(preg_replace('/^DELIMITER[^\n]*\n/', '', $sql) ?? '');
            $sql = trim(preg_replace('/\nDELIMITER[^\n]*$/', '', $sql) ?? '');
            $sql = preg_replace('/;;\s*$/', '', $sql) ?? $sql;
        } else {
            $sql = preg_replace('/;\s*$/', '', $sql) ?? $sql;
        }

        $pdo->exec($sql);
    }

    private function ident(string $nombre): string
    {
        return '`'.str_replace('`', '``', $nombre).'`';
    }

    private function valorSql(PDO $pdo, $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.15F', $v), '0'), '.') ?: '0';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        $v = (string) $v;
        if ($v !== '' && ! mb_check_encoding($v, 'UTF-8')) {
            return '0x'.bin2hex($v);   // contenido binario
        }

        return $pdo->quote($v);
    }

    // ==================================================================
    //  SQLite (desarrollo y pruebas)
    // ==================================================================

    private function copiarSqlite(string $destino): array
    {
        $archivo = (string) DB::connection()->getConfig('database');
        if ($archivo === '' || $archivo === ':memory:' || ! is_file($archivo)) {
            throw new RuntimeException('El respaldo con SQLite necesita una base en archivo (no en memoria).');
        }

        $conteos = $this->conteosSqlite();
        if (! copy($archivo, $destino)) {
            throw new RuntimeException('No se pudo copiar la base SQLite.');
        }

        return $conteos;
    }

    private function conteosSqlite(): array
    {
        $conteos = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $t) {
            $conteos[$t->name] = (int) DB::table($t->name)->count();
        }

        return $conteos;
    }

    private function restaurarSqlite(ZipArchive $zip): void
    {
        $archivo = (string) DB::connection()->getConfig('database');
        $flujo = $zip->getStream('base_de_datos.sqlite');
        if (! $flujo) {
            throw new RuntimeException('El respaldo no trae base_de_datos.sqlite.');
        }

        $nombre = DB::connection()->getName();
        DB::disconnect($nombre);
        $salida = fopen($archivo, 'wb');
        stream_copy_to_stream($flujo, $salida);
        fclose($salida);
        fclose($flujo);
        DB::reconnect($nombre);
    }

    // ==================================================================
    //  Archivos y verificación posterior
    // ==================================================================

    /** Copia de vuelta las fotos y evidencias del respaldo (solo escribe; no borra nada). */
    private function restaurarArchivos(ZipArchive $zip): int
    {
        $disco = Storage::disk('local');
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entrada = (string) $zip->getNameIndex($i);
            if (! str_starts_with($entrada, 'archivos/') || str_ends_with($entrada, '/')) {
                continue;
            }
            $relativa = substr($entrada, strlen('archivos/'));
            // Defensa contra rutas maliciosas dentro del zip.
            if ($relativa === '' || str_contains($relativa, '..') || str_starts_with($relativa, '/') || str_contains($relativa, '\\')) {
                continue;
            }
            $flujo = $zip->getStream($entrada);
            if ($flujo) {
                $disco->put($relativa, $flujo);
                fclose($flujo);
                $total++;
            }
        }

        return $total;
    }

    /** Cuenta las filas de cada tabla y las compara con el manifiesto. Devuelve la lista de diferencias (vacía = todo igual). */
    private function compararConteos(array $esperado): array
    {
        $diferencias = [];
        foreach ($esperado as $tabla => $filas) {
            try {
                $actual = (int) DB::table($tabla)->count();
            } catch (Throwable) {
                $diferencias[] = "La tabla {$tabla} no existe tras restaurar.";

                continue;
            }
            if ($actual !== (int) $filas) {
                $diferencias[] = "{$tabla}: se esperaban {$filas} fila(s) y hay {$actual}.";
            }
        }

        return $diferencias;
    }

    // ==================================================================
    //  Utilidades
    // ==================================================================

    private function rutaRelativa(string $nombre): string
    {
        return self::DIRECTORIO.'/'.$nombre.'.zip';
    }

    private function leerManifiesto(string $ruta): ?array
    {
        if (! is_file($ruta)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            return null;
        }
        $datos = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        return is_array($datos) ? $datos : null;
    }

    /** Evita que dos respaldos/restauraciones corran a la vez. */
    private function conCerrojo(callable $accion)
    {
        $cerrojo = null;
        try {
            $cerrojo = Cache::lock('sciem-respaldo', 1800);
            if (! $cerrojo->get()) {
                throw new RuntimeException('Ya hay un respaldo o una restauración en curso. Espera a que termine.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            $cerrojo = null;   // el almacén de caché no soporta cerrojos: se sigue sin él
        }

        try {
            return $accion();
        } finally {
            optional($cerrojo)->release();
        }
    }
}
