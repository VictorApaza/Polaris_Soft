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

        $matriz = self::separarSiVieneEnUnaColumna($matriz);

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

        $hoja = $zip->getFromName(self::rutaPrimeraHoja($zip));
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
                    $valor = isset($celda->is->t) ? (string) $celda->is->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($celda->is->r, false)));
                } else {
                    $valor = self::limpiarNumero((string) $celda->v);
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


    /**
     * Excel (con la configuración regional en inglés) abre el CSV de la plantilla (separado con ";")
     * con TODO en la columna A: "202400034;7123456;A1;Ana;Perez". Al guardarlo como .xlsx queda así y
     * ninguna columna se reconoce (de ahí el falso "no tiene 9 dígitos"). Aquí se vuelve a separar.
     */
    private static function separarSiVieneEnUnaColumna(array $matriz): array
    {
        $primera = $matriz[0] ?? [];
        $conContenido = array_values(array_filter($primera, fn ($v) => trim((string) $v) !== ''));
        if (count($conContenido) !== 1) {
            return $matriz;
        }

        $texto = (string) $conContenido[0];
        $separador = substr_count($texto, ';') >= substr_count($texto, ',') ? ';' : ',';
        if (substr_count($texto, $separador) < 1) {
            return $matriz;
        }

        return array_map(function ($linea) use ($separador) {
            $celda = trim((string) (array_values(array_filter($linea, fn ($v) => trim((string) $v) !== ''))[0] ?? ''));

            return $celda === '' ? [] : str_getcsv($celda, $separador, '"', '');
        }, $matriz);
    }

    /** "2.02400034E8" -> "202400034"; "7123456.0" -> "7123456"; los textos no se tocan. */
    private static function limpiarNumero(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', $valor)) {
            return $valor;
        }

        $numero = (float) $valor;
        if ($numero == floor($numero) && abs($numero) < 1e15) {
            return number_format($numero, 0, '', '');
        }

        return $valor;
    }

    /** Ruta del XML de la primera hoja según xl/workbook.xml (no siempre se llama sheet1.xml). */
    private static function rutaPrimeraHoja(ZipArchive $zip): string
    {
        $porDefecto = 'xl/worksheets/sheet1.xml';
        $libro = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro === false || $relaciones === false) {
            return $porDefecto;
        }

        $xmlLibro = @simplexml_load_string($libro);
        $xmlRel = @simplexml_load_string($relaciones);
        if (! $xmlLibro || ! $xmlRel) {
            return $porDefecto;
        }

        $hojas = $xmlLibro->sheets->sheet ?? null;
        if (! $hojas || ! isset($hojas[0])) {
            return $porDefecto;
        }
        $idRel = (string) $hojas[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        foreach ($xmlRel->Relationship as $rel) {
            if ((string) $rel['Id'] === $idRel) {
                $destino = ltrim((string) $rel['Target'], '/');

                return str_starts_with($destino, 'xl/') ? $destino : 'xl/'.$destino;
            }
        }

        return $porDefecto;
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
