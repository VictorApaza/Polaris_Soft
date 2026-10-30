<?php

namespace App\Services;

/**
 * Revisión automática de la foto del estudiante en el momento en que la sube.
 * Usa solo la extensión GD de PHP (sin servicios externos) y rechaza lo que se nota a simple vista:
 *   - borrosa (poca nitidez), muy oscura, muy clara o sin contraste;
 *   - imagen que no tiene forma de retrato / no se ve un rostro (casi no hay tono de piel en el centro);
 *   - lentes oscuros (franja de los ojos mucho más oscura que la frente y las mejillas).
 * Los umbrales se ajustan en config/sciem.php (variables SCIEM_FOTO_* del .env).
 *
 * Importante: es una revisión por reglas, no reconocimiento facial. Los lentes transparentes o un rostro
 * parcialmente tapado pueden pasar; por eso el docente conserva el botón "Observar foto".
 */
class ValidadorFotoRostro
{
    /** @return array{ok: bool, motivo: ?string, metricas: array<string,float|int>} */
    public function validar(string $ruta): array
    {
        if (! config('sciem.foto_validar', true)) {
            return ['ok' => true, 'motivo' => null, 'metricas' => []];
        }

        if (! function_exists('imagecreatefromstring')) {
            return ['ok' => true, 'motivo' => null, 'metricas' => []]; // sin GD no se puede revisar: decide el docente
        }

        $contenido = @file_get_contents($ruta);
        $origen = $contenido === false ? false : @imagecreatefromstring($contenido);
        if (! $origen) {
            return $this->rechazo('No se pudo leer la imagen. Sube otra foto en formato JPG o PNG.');
        }

        $w = imagesx($origen);
        $h = imagesy($origen);
        $proporcion = $w / max(1, $h);

        if ($proporcion < 0.5 || $proporcion > 1.8) {
            return $this->rechazo('La foto no es válida: debe ser de tu rostro, de frente, en formato vertical o cuadrado (no una imagen muy ancha o muy angosta).');
        }

        // Se trabaja con una copia pequeña: es suficiente y es rápido.
        $ancho = min(320, $w);
        $alto = max(8, (int) round($h * $ancho / $w));
        $copia = imagecreatetruecolor($ancho, $alto);
        imagefill($copia, 0, 0, imagecolorallocate($copia, 255, 255, 255));
        imagecopyresampled($copia, $origen, 0, 0, 0, 0, $ancho, $alto, $w, $h);

        // Luminosidad por píxel y marca de "tono de piel" (YCbCr).
        $gris = [];
        $piel = [];
        $suma = 0.0;
        $sumaCuad = 0.0;
        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $c = imagecolorat($copia, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $cb = 128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b;
                $cr = 128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b;
                $gris[$y][$x] = $l;
                $piel[$y][$x] = ($l > 40 && $cb >= 77 && $cb <= 127 && $cr >= 133 && $cr <= 173) ? 1 : 0;
                $suma += $l;
                $sumaCuad += $l * $l;
            }
        }
        $n = $ancho * $alto;
        $brillo = $suma / $n;
        $contraste = sqrt(max(0.0, $sumaCuad / $n - $brillo * $brillo));

        // Nitidez: varianza del Laplaciano (si está borrosa casi no hay bordes).
        $sl = 0.0;
        $sl2 = 0.0;
        $m = 0;
        for ($y = 1; $y < $alto - 1; $y++) {
            for ($x = 1; $x < $ancho - 1; $x++) {
                $lap = 4 * $gris[$y][$x] - $gris[$y - 1][$x] - $gris[$y + 1][$x] - $gris[$y][$x - 1] - $gris[$y][$x + 1];
                $sl += $lap;
                $sl2 += $lap * $lap;
                $m++;
            }
        }
        $nitidez = $m > 0 ? $sl2 / $m - ($sl / $m) ** 2 : 0.0;

        $metricas = ['nitidez' => round($nitidez, 1), 'brillo' => round($brillo, 1), 'contraste' => round($contraste, 1)];

        if ($nitidez < (float) config('sciem.foto_nitidez_min', 40)) {
            return $this->rechazo('La foto no es válida: está borrosa. Tómala de nuevo con la cámara quieta y enfocada.', $metricas);
        }
        if ($brillo < (float) config('sciem.foto_brillo_min', 55)) {
            return $this->rechazo('La foto no es válida: está muy oscura. Tómala con buena luz.', $metricas);
        }
        if ($brillo > (float) config('sciem.foto_brillo_max', 215)) {
            return $this->rechazo('La foto no es válida: está sobreexpuesta (demasiada luz). Evita el contraluz y el flash directo.', $metricas);
        }
        if ($contraste < (float) config('sciem.foto_contraste_min', 25)) {
            return $this->rechazo('La foto no es válida: se ve plana o poco clara. Tómala con buena luz y fondo liso.', $metricas);
        }

        // ¿Hay un rostro? Se mira el porcentaje de tono de piel en la zona central.
        $pielCentro = $this->promedio($piel, $ancho, $alto, 0.25, 0.75, 0.15, 0.85);
        $metricas['piel_centro'] = round($pielCentro * 100, 1);
        if ($pielCentro < (float) config('sciem.foto_piel_min', 0.08)) {
            return $this->rechazo('La foto no es válida: no se ve claramente tu rostro. Debe ser una foto de frente, con la cara centrada y descubierta.', $metricas);
        }

        // Lentes oscuros: la franja de los ojos es mucho más oscura que la frente y las mejillas.
        $ojos = $this->promedio($gris, $ancho, $alto, 0.22, 0.78, 0.28, 0.45);
        $frente = $this->promedio($gris, $ancho, $alto, 0.30, 0.70, 0.08, 0.25);
        $mejillas = $this->promedio($gris, $ancho, $alto, 0.28, 0.72, 0.50, 0.65);
        $referencia = max($frente, $mejillas);
        $metricas['ojos_vs_cara'] = $referencia > 0 ? round($ojos / $referencia, 2) : 1;
        if ($referencia > 0 && $ojos / $referencia < (float) config('sciem.foto_lentes_ratio', 0.5) && $ojos < 80) {
            return $this->rechazo('La foto no es válida: parece que llevas lentes oscuros. Quítatelos y sube otra foto con los ojos visibles.', $metricas);
        }

        return ['ok' => true, 'motivo' => null, 'metricas' => $metricas];
    }

    /** Promedio de una matriz [y][x] dentro de un rectángulo dado en fracciones del ancho y alto. */
    private function promedio(array $matriz, int $ancho, int $alto, float $x1, float $x2, float $y1, float $y2): float
    {
        $suma = 0.0;
        $cuenta = 0;
        for ($y = (int) ($alto * $y1); $y < (int) ($alto * $y2); $y++) {
            for ($x = (int) ($ancho * $x1); $x < (int) ($ancho * $x2); $x++) {
                $suma += $matriz[$y][$x];
                $cuenta++;
            }
        }

        return $cuenta ? $suma / $cuenta : 0.0;
    }

    private function rechazo(string $motivo, array $metricas = []): array
    {
        return ['ok' => false, 'motivo' => $motivo, 'metricas' => $metricas];
    }
}
