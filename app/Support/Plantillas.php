<?php

namespace App\Support;

/** Plantillas CSV descargables (con BOM y ";" para que Excel en español las abra ya separadas en columnas). */
class Plantillas
{
    public static function csv(string $nombreArchivo, array $encabezado, array $ejemplos)
    {
        return response()->streamDownload(function () use ($encabezado, $ejemplos) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezado, ';');
            foreach ($ejemplos as $fila) {
                fputcsv($salida, $fila, ';');
            }
            fclose($salida);
        }, $nombreArchivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
