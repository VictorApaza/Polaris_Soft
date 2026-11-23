<?php

namespace Tests\Unit;

use App\Services\FotoDuplicadaService;
use App\Support\LectorTabla;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/** Lectura de planillas CSV y Excel (.xlsx) de la importación, y distancia entre huellas de fotos. */
class LectorTablaTest extends TestCase
{
    /** Arma un .xlsx mínimo con una sola hoja; $filas es una lista de listas de textos/números. */
    private function xlsx(array $filas, string $hoja = 'sheet7.xml'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($filas as $i => $fila) {
            $xml .= '<row r="'.($i + 1).'">';
            foreach ($fila as $j => $valor) {
                $ref = chr(65 + $j).($i + 1);
                // Un valor "#123" se escribe como celda numérica (<v>), igual que hace Excel con los números.
                $numerica = is_int($valor) || is_float($valor) || (is_string($valor) && str_starts_with($valor, '#'));
                $xml .= $numerica
                    ? '<c r="'.$ref.'"><v>'.ltrim((string) $valor, '#').'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $valor).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        $zip->addFromString('xl/worksheets/'.$hoja, $xml);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hoja1" sheetId="1" r:id="rId9"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="x" Target="worksheets/'.$hoja.'"/></Relationships>');
        $zip->close();

        return new UploadedFile($ruta, 'estudiantes.xlsx', null, null, true);
    }

    public function test_xlsx_con_columnas_normales(): void
    {
        $filas = LectorTabla::leer($this->xlsx([
            ['codigo_universitario', 'ci', 'nombres'],
            [202400034, 7123456, 'Ana'],
        ]));

        $this->assertSame('202400034', $filas[0]['codigo_universitario']);
        $this->assertSame('7123456', $filas[0]['ci']);
    }

    public function test_xlsx_con_todo_pegado_en_la_columna_a(): void
    {
        // Excel abrió el CSV (separado con ";") sin separar las columnas y se guardó como .xlsx.
        $filas = LectorTabla::leer($this->xlsx([
            ['codigo_universitario;ci;ci_complemento;nombres;apellidos'],
            ['202400034;7123456;A1;Gabriel Fernando;Romero Silva'],
        ]));

        $this->assertSame('202400034', $filas[0]['codigo_universitario']);
        $this->assertSame('A1', $filas[0]['ci_complemento']);
        $this->assertSame('Romero Silva', $filas[0]['apellidos']);
    }

    public function test_xlsx_numero_en_notacion_cientifica_y_hoja_con_otro_nombre(): void
    {
        $filas = LectorTabla::leer($this->xlsx([
            ['codigo_universitario', 'ci'],
            ['#2.02400034E8', '#7123456.0'],
        ]));

        $this->assertSame('202400034', $filas[0]['codigo_universitario']);
        $this->assertSame('7123456', $filas[0]['ci']);
    }

    public function test_csv_con_punto_y_coma_sigue_funcionando(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'c').'.csv';
        file_put_contents($ruta, "\xEF\xBB\xBFcodigo_universitario;ci;nombres\n202400034;7123456;Ana\n");

        $filas = LectorTabla::leer(new UploadedFile($ruta, 'plantilla.csv', null, null, true));

        $this->assertSame('202400034', $filas[0]['codigo_universitario']);
        $this->assertSame('Ana', $filas[0]['nombres']);
    }

    public function test_distancia_entre_huellas(): void
    {
        $servicio = new FotoDuplicadaService();

        $this->assertSame(0, $servicio->distancia('00000000ffffffff', '00000000ffffffff'));
        $this->assertSame(64, $servicio->distancia('0000000000000000', 'ffffffffffffffff'));
        $this->assertSame(1, $servicio->distancia('0000000000000000', '0000000000000001'));
    }
}
