<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Exporta un reporte (título, datos de cabecera, resumen y una o más tablas) a PDF o Excel (.xlsx).
 * No necesita librerías extra: el PDF se arma a mano (A4 horizontal, fuente Helvetica) y el .xlsx con ZipArchive.
 *
 * Estructura del reporte:
 *   [
 *     'titulo'   => 'Reporte de asistencia',
 *     'meta'     => ['Examen' => 'Cálculo I', 'Fecha' => '10/12/2026'],   // datos de cabecera
 *     'resumen'  => ['Habilitados' => 120, 'Ingresaron' => 110],           // cifras clave
 *     'secciones'=> [ ['titulo' => 'Ingresos', 'columnas' => ['A','B'], 'filas' => [[1,2]], 'alinear' => ['left','right'], 'vacio' => 'Sin registros'] ],
 *   ]
 */
class ExportadorTabla
{
    public const FORMATOS = ['pdf', 'xlsx'];

    public static function descargar(string $formato, string $archivoBase, array $reporte): Response
    {
        $reporte['generado'] = $reporte['generado'] ?? self::ahora();

        if ($formato === 'xlsx') {
            $contenido = self::xlsx($reporte);
            $tipo = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $nombre = $archivoBase.'.xlsx';
        } else {
            $contenido = self::pdf($reporte);
            $tipo = 'application/pdf';
            $nombre = $archivoBase.'.pdf';
        }

        return response($contenido, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function ahora(): string
    {
        return now()->timezone(config('sciem.zona_horaria'))->format('d/m/Y H:i');
    }

    /** Nombre de archivo seguro: "reporte-asistencia-calculo-i-2026-12-10". */
    public static function nombreArchivo(string ...$partes): string
    {
        $texto = implode('-', array_filter($partes, fn ($p) => $p !== null && $p !== ''));
        $texto = \Illuminate\Support\Str::slug($texto);

        return $texto !== '' ? $texto : 'reporte';
    }

    // =====================================================================
    //  EXCEL (.xlsx)
    // =====================================================================

    public static function xlsx(array $r): string
    {
        $r['generado'] = $r['generado'] ?? self::ahora();
        $filas = [];            // cada fila: lista de [valor, estilo]
        $anchos = [];           // ancho máximo (caracteres) por columna
        $combinar = [];
        $nuevaFila = function (array $celdas) use (&$filas, &$anchos) {
            foreach ($celdas as $i => [$valor]) {
                $largo = min(60, max(1, mb_strlen((string) $valor)));
                $anchos[$i] = max($anchos[$i] ?? 8, $largo + 2);
            }
            $filas[] = $celdas;

            return count($filas);
        };

        // Los títulos y datos de cabecera no deben ensanchar las columnas de la tabla.
        $anchosAntes = $anchos;
        $filas[] = [[$r['titulo'], 1]];
        $filas[] = [['SCIEM · Sistema de Control de Exámenes Masivos', 5]];
        foreach ($r['meta'] ?? [] as $k => $v) {
            $filas[] = [[(string) $k, 3], [self::texto($v), 0]];
        }
        $filas[] = [['Generado', 3], [$r['generado'], 0]];
        if (! empty($r['resumen'])) {
            $filas[] = [];
            $filas[] = [['Resumen', 4]];
            foreach ($r['resumen'] as $k => $v) {
                $filas[] = [[(string) $k, 3], [$v, 6]];
            }
        }

        $congelar = null;
        foreach ($r['secciones'] ?? [] as $s) {
            $filas[] = [];
            if (! empty($s['titulo'])) {
                $filas[] = [[$s['titulo'], 4]];
            }
            $nuevaFila(array_map(fn ($c) => [(string) $c, 2], $s['columnas']));
            $primeraFilaTabla = count($filas);
            if (empty($s['filas'])) {
                $filas[] = [[$s['vacio'] ?? 'Sin registros.', 0]];
            }
            foreach ($s['filas'] as $fila) {
                $nuevaFila(array_map(fn ($v) => [is_int($v) || is_float($v) ? $v : self::texto($v), is_int($v) || is_float($v) ? 6 : 0], array_values($fila)));
            }
            if ($congelar === null && count($r['secciones']) === 1) {
                $congelar = $primeraFilaTabla;
            }
        }

        $xmlFilas = '';
        foreach ($filas as $n => $celdas) {
            $xmlFilas .= '<row r="'.($n + 1).'">';
            foreach ($celdas as $i => [$valor, $estilo]) {
                $ref = self::columna($i).($n + 1);
                if (is_int($valor) || is_float($valor)) {
                    $xmlFilas .= '<c r="'.$ref.'" s="'.$estilo.'"><v>'.$valor.'</v></c>';
                } else {
                    $xmlFilas .= '<c r="'.$ref.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) $valor).'</t></is></c>';
                }
            }
            $xmlFilas .= '</row>';
        }

        $cols = '';
        ksort($anchos);
        foreach ($anchos as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.min(55, $w).'" customWidth="1"/>';
        }

        $panel = $congelar ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$congelar.'" topLeftCell="A'.($congelar + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '';

        $hoja = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$panel.'<cols>'.$cols.'</cols><sheetData>'.$xmlFilas.'</sheetData></worksheet>';

        $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="6">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="15"/><color rgb="FF0A2350"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF0A2350"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0A2350"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $libro = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::xml(self::nombreHoja($r['titulo'])).'" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $libro);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $estilos);
        $zip->addFromString('xl/worksheets/sheet1.xml', $hoja);
        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private static function columna(int $i): string
    {
        $letras = '';
        for ($n = $i; $n >= 0; $n = intdiv($n, 26) - 1) {
            $letras = chr(65 + $n % 26).$letras;
        }

        return $letras;
    }

    private static function xml(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function nombreHoja(string $titulo): string
    {
        $limpio = trim(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $titulo));

        return mb_substr($limpio !== '' ? $limpio : 'Reporte', 0, 31);
    }

    private static function texto($valor): string
    {
        return $valor === null || $valor === '' ? '—' : (string) $valor;
    }

    // =====================================================================
    //  PDF (A4 horizontal, Helvetica, sin librerías)
    // =====================================================================

    private const W = 842.0;   // A4 apaisado en puntos
    private const H = 595.0;
    private const MARGEN = 36.0;

    /** Anchos de Helvetica (1/1000 em) para los caracteres 32..126. */
    private const HELV = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    private const HELV_B = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    public static function pdf(array $r): string
    {
        $r['generado'] = $r['generado'] ?? self::ahora();
        $paginas = [];      // cada página: lista de comandos de contenido
        $cmd = [];
        $y = 0.0;
        $columnasActuales = null;

        $nuevaPagina = function () use (&$paginas, &$cmd, &$y) {
            if ($cmd) {
                $paginas[] = $cmd;
            }
            $cmd = [];
            $y = self::H - self::MARGEN;
        };
        $nuevaPagina();

        $ancho = self::W - 2 * self::MARGEN;

        // ---- Cabecera del reporte (solo en la primera página) ----
        $cmd[] = self::texto2(self::MARGEN, $y - 8, 'SCIEM · Sistema de Control de Exámenes Masivos', 8, false, '0.39 0.45 0.55');
        $y -= 26;
        $cmd[] = self::texto2(self::MARGEN, $y, $r['titulo'], 17, true, '0.04 0.14 0.31');
        $y -= 8;

        foreach ($r['meta'] ?? [] as $k => $v) {
            $y -= 13;
            $cmd[] = self::texto2(self::MARGEN, $y, $k.':', 9, true, '0.12 0.16 0.23');
            $cmd[] = self::texto2(self::MARGEN + 78, $y, self::cortar((string) self::texto($v), 9, false, $ancho - 80), 9, false, '0.12 0.16 0.23');
        }
        $y -= 13;
        $cmd[] = self::texto2(self::MARGEN, $y, 'Generado:', 9, true, '0.12 0.16 0.23');
        $cmd[] = self::texto2(self::MARGEN + 78, $y, $r['generado'], 9, false, '0.12 0.16 0.23');

        // ---- Resumen: tarjetas con cifras ----
        if (! empty($r['resumen'])) {
            $y -= 12;
            $n = count($r['resumen']);
            $porFila = min($n, 6);
            $gap = 8.0;
            $w = ($ancho - $gap * ($porFila - 1)) / $porFila;
            $i = 0;
            foreach ($r['resumen'] as $k => $v) {
                $col = $i % $porFila;
                if ($i > 0 && $col === 0) {
                    $y -= 46;
                }
                $x = self::MARGEN + $col * ($w + $gap);
                $cmd[] = self::rect($x, $y - 40, $w, 40, '0.945 0.957 0.976');
                $cmd[] = self::texto2($x + 8, $y - 13, mb_strtoupper(self::cortar((string) $k, 7, true, $w - 14)), 7, true, '0.39 0.45 0.55');
                // La cifra baja de tamaño (15 → 8 pt) hasta caber en la tarjeta, para no cortar fechas ni horas largas.
                $tamV = 15;
                while ($tamV > 8 && self::medir(self::texto((string) $v), $tamV, true) > $w - 14) {
                    $tamV--;
                }
                $cmd[] = self::texto2($x + 8, $y - 32 + (15 - $tamV) / 2, self::cortar((string) $v, $tamV, true, $w - 14), $tamV, true, '0.04 0.14 0.31');
                $i++;
            }
            $y -= 46;
        }

        // ---- Secciones (tablas) ----
        foreach ($r['secciones'] ?? [] as $s) {
            $cols = $s['columnas'];
            $nCols = count($cols);
            $alinear = $s['alinear'] ?? array_fill(0, $nCols, 'left');
            $w = self::anchosColumnas($cols, $s['filas'], $ancho);
            $pad = 4.0;
            $tam = 8.0;
            $lh = 10.5;

            $encabezadoSeccion = function () use (&$cmd, &$y, $s) {
                if (! empty($s['titulo'])) {
                    $cmd[] = self::texto2(self::MARGEN, $y - 12, $s['titulo'], 11.5, true, '0.04 0.14 0.31');
                    $y -= 20;
                }
            };

            $dibujarEncabezado = function () use (&$cmd, &$y, $cols, $w, $pad, $tam, $lh, $ancho) {
                $lineas = [];
                $max = 1;
                foreach ($cols as $i => $c) {
                    $lineas[$i] = self::envolver((string) $c, $w[$i] - 2 * $pad, $tam, true);
                    $max = max($max, count($lineas[$i]));
                }
                $alto = $max * $lh + 2 * $pad;
                $cmd[] = self::rect(self::MARGEN, $y - $alto, $ancho, $alto, '0.04 0.14 0.31');
                $x = self::MARGEN;
                foreach ($cols as $i => $c) {
                    foreach ($lineas[$i] as $j => $linea) {
                        $cmd[] = self::texto2($x + $pad, $y - $pad - ($j + 1) * $lh + 2.5, $linea, $tam, true, '1 1 1');
                    }
                    $x += $w[$i];
                }
                $y -= $alto;
            };

            // Si no cabe el título + encabezado + una fila, pasa a otra página.
            if ($y < self::MARGEN + 90) {
                $nuevaPagina();
            }
            $y -= 14;
            $encabezadoSeccion();
            $dibujarEncabezado();

            if (empty($s['filas'])) {
                $cmd[] = self::texto2(self::MARGEN + $pad, $y - 14, (string) ($s['vacio'] ?? 'Sin registros.'), 9, false, '0.39 0.45 0.55');
                $y -= 24;

                continue;
            }

            foreach ($s['filas'] as $nFila => $fila) {
                $fila = array_values($fila);
                $lineas = [];
                $max = 1;
                foreach ($cols as $i => $_) {
                    $lineas[$i] = self::envolver((string) self::texto($fila[$i] ?? ''), $w[$i] - 2 * $pad, $tam, false);
                    $max = max($max, count($lineas[$i]));
                }
                $alto = $max * $lh + 2 * $pad - 1;

                if ($y - $alto < self::MARGEN + 18) {
                    $nuevaPagina();
                    $dibujarEncabezado();
                }

                if ($nFila % 2 === 1) {
                    $cmd[] = self::rect(self::MARGEN, $y - $alto, $ancho, $alto, '0.972 0.98 0.992');
                }
                $x = self::MARGEN;
                foreach ($cols as $i => $_) {
                    foreach ($lineas[$i] as $j => $linea) {
                        $tx = $x + $pad;
                        if (($alinear[$i] ?? 'left') === 'right') {
                            $tx = $x + $w[$i] - $pad - self::medir($linea, $tam, false);
                        }
                        $cmd[] = self::texto2($tx, $y - $pad - ($j + 1) * $lh + 2.5, $linea, $tam, false, '0.12 0.16 0.23');
                    }
                    $x += $w[$i];
                }
                $cmd[] = self::linea(self::MARGEN, $y - $alto, self::MARGEN + $ancho, $y - $alto);
                $y -= $alto;
            }
        }

        if ($cmd) {
            $paginas[] = $cmd;
        }

        return self::armarPdf($paginas, $r);
    }

    private static function armarPdf(array $paginas, array $r): string
    {
        $total = count($paginas);
        $objetos = [];      // índice 1..n => contenido
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $siguiente = 5;
        foreach ($paginas as $n => $comandos) {
            $comandos[] = self::linea(self::MARGEN, self::MARGEN - 6, self::W - self::MARGEN, self::MARGEN - 6);
            $comandos[] = self::texto2(self::MARGEN, self::MARGEN - 18, 'Generado el '.$r['generado'].' · '.$r['titulo'], 7.5, false, '0.39 0.45 0.55');
            $pie = 'Página '.($n + 1).' de '.$total;
            $comandos[] = self::texto2(self::W - self::MARGEN - self::medir($pie, 7.5, false), self::MARGEN - 18, $pie, 7.5, false, '0.39 0.45 0.55');

            $flujo = gzcompress(implode("\n", $comandos), 6);
            $idContenido = $siguiente++;
            $idPagina = $siguiente++;
            $objetos[$idContenido] = '<< /Length '.strlen($flujo)." /Filter /FlateDecode >>\nstream\n".$flujo."\nendstream";
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$idContenido.' 0 R >>';
            $kids[] = $idPagina.' 0 R';
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$total.' >>';
        $idInfo = $siguiente++;
        $objetos[$idInfo] = '<< /Title ('.self::escapar($r['titulo']).') /Producer (SCIEM) /Creator (SCIEM) >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objetos as $id => $contenido) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$contenido."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($objetos as $id => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= 'trailer << /Size '.(count($objetos) + 1).' /Root 1 0 R /Info '.$idInfo." 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    // ---- primitivas de dibujo ----

    private static function texto2(float $x, float $y, string $texto, float $tam, bool $negrita, string $color): string
    {
        return $color.' rg BT /'.($negrita ? 'F2' : 'F1').' '.$tam.' Tf '.round($x, 2).' '.round($y, 2).' Td ('.self::escapar($texto).') Tj ET';
    }

    private static function rect(float $x, float $y, float $w, float $h, string $color): string
    {
        return $color.' rg '.round($x, 2).' '.round($y, 2).' '.round($w, 2).' '.round($h, 2).' re f';
    }

    private static function linea(float $x1, float $y1, float $x2, float $y2): string
    {
        return '0.89 0.91 0.94 RG 0.5 w '.round($x1, 2).' '.round($y1, 2).' m '.round($x2, 2).' '.round($y2, 2).' l S';
    }

    /** Escapa y convierte a WinAnsi (CP1252) para que se vean tildes y la ñ. */
    private static function escapar(string $s): string
    {
        $s = str_replace(["\r", "\n", "\t"], ' ', $s);
        $s = strtr($s, ['—' => '-', '–' => '-', '→' => '>', '≥' => '>=', '≤' => '<=', '✓' => 'Si', '✗' => 'No', "\u{00A0}" => ' ']);
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    // ---- medición y ajuste de texto ----

    private static function medir(string $s, float $tam, bool $negrita): float
    {
        $tabla = $negrita ? self::HELV_B : self::HELV;
        $base = self::sinAcentos($s);
        $suma = 0;
        $len = strlen($base);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($base[$i]);
            $suma += ($o >= 32 && $o <= 126) ? $tabla[$o - 32] : 556;
        }

        return $suma * $tam / 1000;
    }

    private static function sinAcentos(string $s): string
    {
        static $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            '·' => '.', '—' => '-', '–' => '-', '→' => '>', '≥' => '>', '≤' => '<', '✓' => 'v', '✗' => 'x',
        ];

        return strtr($s, $mapa);
    }

    /** Parte el texto en líneas que quepan en $ancho (corta palabras muy largas). */
    private static function envolver(string $texto, float $ancho, float $tam, bool $negrita): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return [''];
        }
        $lineas = [];
        $actual = '';
        foreach (explode(' ', $texto) as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;
            if (self::medir($prueba, $tam, $negrita) <= $ancho) {
                $actual = $prueba;

                continue;
            }
            if ($actual !== '') {
                $lineas[] = $actual;
                $actual = '';
            }
            // palabra más ancha que la columna: se parte por caracteres
            while (self::medir($palabra, $tam, $negrita) > $ancho && mb_strlen($palabra) > 1) {
                $corte = mb_strlen($palabra) - 1;
                while ($corte > 1 && self::medir(mb_substr($palabra, 0, $corte), $tam, $negrita) > $ancho) {
                    $corte--;
                }
                $lineas[] = mb_substr($palabra, 0, $corte);
                $palabra = mb_substr($palabra, $corte);
            }
            $actual = $palabra;
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas ?: [''];
    }

    private static function cortar(string $texto, float $tam, bool $negrita, float $ancho): string
    {
        if (self::medir($texto, $tam, $negrita) <= $ancho) {
            return $texto;
        }
        while (mb_strlen($texto) > 1 && self::medir($texto.'…', $tam, $negrita) > $ancho) {
            $texto = mb_substr($texto, 0, -1);
        }

        return $texto.'...';
    }

    /** Reparte el ancho útil entre las columnas según lo que contienen (con mínimo y máximo razonables). */
    private static function anchosColumnas(array $cols, array $filas, float $total): array
    {
        $pesos = [];
        foreach ($cols as $i => $c) {
            $mayor = mb_strlen((string) $c) * 0.8;
            foreach (array_slice($filas, 0, 200) as $fila) {
                $fila = array_values($fila);
                $mayor = max($mayor, min(40, mb_strlen((string) ($fila[$i] ?? ''))));
            }
            $pesos[$i] = max(5, $mayor);
        }
        $suma = array_sum($pesos);

        return array_map(fn ($p) => $total * $p / $suma, $pesos);
    }
}
