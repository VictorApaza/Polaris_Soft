<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Lee una planilla subida (CSV o Excel .xlsx) y la devuelve como filas asociativas
 * con las columnas en minúscula: [['ci' => '123', 'nombres' => 'Ana'], ...].
 * No necesita librerías extra: el .xlsx se lee con ZipArchive + SimpleXML.
 */
class LectorTabla
{
    /** @return array<int,array<string,string|null>>|null  null si no se pudo leer */
    public static function leer(UploadedFile $archivo): ?array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        $matriz = $extension === 'xlsx'
            ? self::matrizDesdeXlsx($archivo->getRealPath())
            : self::matrizDesdeCsv($archivo->getRealPath());

        if (! $matriz) {
            return null;
        }

        $encabezado = array_map(fn ($c) => self::normalizarColumna((string) $c), array_shift($matriz));
        $filas = [];

        foreach ($matriz as $linea) {
            if (count(array_filter($linea, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fila vacía
            }
            $linea = array_slice(array_pad($linea, count($encabezado), null), 0, count($encabezado));
            $filas[] = array_combine($encabezado, array_map(fn ($v) => $v === null ? null : trim((string) $v), $linea));
        }

        return $filas;
    }

    /** "Código Universitario" -> "codigo_universitario" */
    public static function normalizarColumna(string $texto): string
    {
        $texto = mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $texto)), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $texto), '_');
    }

    private static function matrizDesdeCsv(string $ruta): ?array
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false || trim($contenido) === '') {
            return null;
        }

        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1'); // CSV guardado por Excel en Windows
        }

        // Excel en español separa con ";" y en inglés con ",": se detecta por la primera línea.
        $primera = strtok($contenido, "\r\n") ?: '';
        $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        $manejador = fopen('php://temp', 'r+');
        fwrite($manejador, $contenido);
        rewind($manejador);

        $matriz = [];
        while (($linea = fgetcsv($manejador, 0, $separador)) !== false) {
            $matriz[] = $linea;
        }
        fclose($manejador);

        return $matriz ?: null;
    }

    private static function matrizDesdeXlsx(string $ruta): ?array
    {
        if (! class_exists(ZipArchive::class)) {
            return null; // falta la extensión zip de PHP: guardar la planilla como CSV
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            return null;
        }

        $compartidas = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sst = @simplexml_load_string($xml);
            if ($sst) {
                foreach ($sst->si as $si) {
                    $texto = '';
                    if (isset($si->t)) {
                        $texto = (string) $si->t;
                    } else {
                        foreach ($si->r as $r) {
                            $texto .= (string) $r->t;
                        }
                    }
                    $compartidas[] = $texto;
                }
            }
        }

        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($hoja === false) {
            return null;
        }

        $sheet = @simplexml_load_string($hoja);
        if (! $sheet || ! isset($sheet->sheetData)) {
            return null;
        }

        $matriz = [];
        foreach ($sheet->sheetData->row as $fila) {
            $linea = [];
            foreach ($fila->c as $celda) {
                $columna = self::indiceColumna((string) $celda['r']);
                $tipo = (string) $celda['t'];

                if ($tipo === 's') {
                    $valor = $compartidas[(int) $celda->v] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = (string) $celda->is->t;
                } else {
                    $valor = (string) $celda->v;
                    // Los números enteros (CI, códigos) a veces llegan como "7123456.0".
                    if (preg_match('/^\d+\.0+$/', $valor)) {
                        $valor = (string) (int) $valor;
                    }
                }

                $linea[$columna] = $valor;
            }

            if ($linea) {
                $max = max(array_keys($linea));
                $completa = [];
                for ($i = 0; $i <= $max; $i++) {
                    $completa[] = $linea[$i] ?? '';
                }
                $matriz[] = $completa;
            }
        }

        return $matriz ?: null;
    }

    /** "C5" -> 2 */
    private static function indiceColumna(string $referencia): int
    {
        $letras = preg_replace('/[^A-Z]/', '', strtoupper($referencia));
        $n = 0;
        foreach (str_split($letras) as $l) {
            $n = $n * 26 + (ord($l) - 64);
        }

        return max(0, $n - 1);
    }
}
