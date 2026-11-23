<?php

namespace App\Services;

use App\Models\Estudiante;

/**
 * Evita que la MISMA foto se use para estudiantes distintos.
 * Compara el SHA-256 (copia exacta) y una huella perceptual dHash de 64 bits (misma foto reescalada,
 * recomprimida o con otro nombre). Dos fotos distintas de la misma persona NO se consideran iguales.
 */
class FotoDuplicadaService
{
    /** @return array{hash: string, huella: ?string} */
    public function calcular(string $contenido): array
    {
        return ['hash' => hash('sha256', $contenido), 'huella' => $this->huella($contenido)];
    }

    /** Devuelve el otro estudiante que ya tiene esa misma foto (o null si es única). */
    public function duplicadaDe(string $contenido, ?int $estudianteId = null): ?Estudiante
    {
        $datos = $this->calcular($contenido);

        $exacta = Estudiante::where('foto_hash', $datos['hash'])
            ->when($estudianteId, fn ($q) => $q->where('id_estudiante', '!=', $estudianteId))
            ->first();
        if ($exacta) {
            return $exacta;
        }

        if ($datos['huella'] === null) {
            return null;
        }

        $maxima = (int) config('sciem.foto_duplicada_distancia', 4);
        $candidatas = Estudiante::whereNotNull('foto_huella')
            ->when($estudianteId, fn ($q) => $q->where('id_estudiante', '!=', $estudianteId))
            ->get(['id_estudiante', 'nombres', 'apellidos', 'codigo_universitario', 'foto_huella']);

        foreach ($candidatas as $otro) {
            if ($this->distancia($datos['huella'], (string) $otro->foto_huella) <= $maxima) {
                return $otro;
            }
        }

        return null;
    }

    /** Huella perceptual dHash (9x8 en grises): 64 bits como 16 caracteres hexadecimales. null si no hay GD. */
    public function huella(string $contenido): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $origen = @imagecreatefromstring($contenido);
        if (! $origen) {
            return null;
        }

        $chica = imagecreatetruecolor(9, 8);
        imagefill($chica, 0, 0, imagecolorallocate($chica, 255, 255, 255));
        imagecopyresampled($chica, $origen, 0, 0, 0, 0, 9, 8, imagesx($origen), imagesy($origen));

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $this->gris($chica, $x, $y) > $this->gris($chica, $x + 1, $y) ? '1' : '0';
            }
        }

        return sprintf('%08x%08x', bindec(substr($bits, 0, 32)), bindec(substr($bits, 32, 32)));
    }

    /** Bits distintos entre dos huellas (0 = idénticas). */
    public function distancia(string $a, string $b): int
    {
        if (strlen($a) !== 16 || strlen($b) !== 16) {
            return 64;
        }
        $xor1 = hexdec(substr($a, 0, 8)) ^ hexdec(substr($b, 0, 8));
        $xor2 = hexdec(substr($a, 8, 8)) ^ hexdec(substr($b, 8, 8));

        return substr_count(decbin($xor1), '1') + substr_count(decbin($xor2), '1');
    }

    private function gris($imagen, int $x, int $y): float
    {
        $c = imagecolorat($imagen, $x, $y);

        return 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
    }
}
