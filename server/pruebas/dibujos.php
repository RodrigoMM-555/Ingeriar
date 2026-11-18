<?php

// Imágenes y documentos ficticios generados en PHP puro (sin la extensión gd) para los
// scripts de datos de ejemplo: fotos de paisajes de acampada, tiques de compra (PNG) y
// documentos PDF de una página con texto. No contienen datos de nadie.

// ---------- Dibujo en un lienzo de píxeles y codificación PNG ----------

class Lienzo
{
    public array $pixeles = [];

    public function __construct(public int $ancho, public int $alto, array $fondo)
    {
        $this->pixeles = array_fill(0, $alto, array_fill(0, $ancho, $fondo));
    }

    public function punto(int $x, int $y, array $color): void
    {
        if ($x >= 0 && $y >= 0 && $x < $this->ancho && $y < $this->alto) {
            $this->pixeles[$y][$x] = $color;
        }
    }

    public function rectangulo(int $x1, int $y1, int $x2, int $y2, array $color): void
    {
        for ($y = max(0, $y1); $y < min($this->alto, $y2); $y++) {
            for ($x = max(0, $x1); $x < min($this->ancho, $x2); $x++) {
                $this->pixeles[$y][$x] = $color;
            }
        }
    }

    public function circulo(int $cx, int $cy, int $radio, array $color): void
    {
        for ($y = $cy - $radio; $y <= $cy + $radio; $y++) {
            for ($x = $cx - $radio; $x <= $cx + $radio; $x++) {
                if (($x - $cx) ** 2 + ($y - $cy) ** 2 <= $radio ** 2) {
                    $this->punto($x, $y, $color);
                }
            }
        }
    }

    // Triángulo con la base horizontal (una tienda de campaña o un pino).
    public function triangulo(int $cx, int $arriba, int $base, int $semiancho, array $color): void
    {
        for ($y = $arriba; $y <= $base; $y++) {
            $mitad = (int) round($semiancho * ($y - $arriba) / max(1, $base - $arriba));
            for ($x = $cx - $mitad; $x <= $cx + $mitad; $x++) {
                $this->punto($x, $y, $color);
            }
        }
    }

    public function png(): string
    {
        $datos = '';
        foreach ($this->pixeles as $fila) {
            $datos .= "\x00";
            foreach ($fila as [$r, $g, $b]) {
                $datos .= chr($r) . chr($g) . chr($b);
            }
        }
        $trozo = static fn(string $tipo, string $contenido): string =>
            pack('N', strlen($contenido)) . $tipo . $contenido . pack('N', crc32($tipo . $contenido));
        return "\x89PNG\r\n\x1a\n"
            . $trozo('IHDR', pack('NNCCCCC', $this->ancho, $this->alto, 8, 2, 0, 0, 0))
            . $trozo('IDAT', gzcompress($datos, 6))
            . $trozo('IEND', '');
    }
}

function mezclar(array $a, array $b, float $t): array
{
    return [
        (int) round($a[0] + ($b[0] - $a[0]) * $t),
        (int) round($a[1] + ($b[1] - $a[1]) * $t),
        (int) round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

// Paisaje de acampada: cielo en degradado, sol, montañas, pinos y tiendas.
function dibujarPaisaje(int $semilla): string
{
    mt_srand($semilla);
    $ancho = 480;
    $alto = 320;
    $atardecer = mt_rand(0, 2) === 0;
    $cieloArriba = $atardecer ? [70, 60, 140] : [70, 140, 220];
    $cieloAbajo = $atardecer ? [250, 160, 90] : [190, 225, 250];
    $lienzo = new Lienzo($ancho, $alto, $cieloArriba);
    $horizonte = mt_rand(170, 210);
    for ($y = 0; $y < $horizonte; $y++) {
        $color = mezclar($cieloArriba, $cieloAbajo, $y / $horizonte);
        $lienzo->rectangulo(0, $y, $ancho, $y + 1, $color);
    }
    $lienzo->circulo(mt_rand(60, 420), mt_rand(40, 110), mt_rand(18, 30), $atardecer ? [255, 200, 80] : [255, 240, 150]);

    // Montañas al fondo.
    $colorMontana = $atardecer ? [90, 70, 110] : [110, 130, 150];
    for ($i = 0; $i < 4; $i++) {
        $lienzo->triangulo(mt_rand(0, $ancho), $horizonte - mt_rand(60, 120), $horizonte, mt_rand(80, 150), $colorMontana);
    }
    // Suelo con una ligera ondulación.
    $verde = [70 + mt_rand(0, 40), 140 + mt_rand(0, 40), 70];
    for ($x = 0; $x < $ancho; $x++) {
        $ondulacion = (int) round(6 * sin($x / 40 + $semilla));
        $lienzo->rectangulo($x, $horizonte + $ondulacion, $x + 1, $alto, $verde);
    }
    // Pinos y tiendas de campaña.
    for ($i = 0; $i < mt_rand(4, 9); $i++) {
        $x = mt_rand(0, $ancho);
        $base = $horizonte + mt_rand(10, 40);
        $lienzo->rectangulo($x - 3, $base, $x + 3, $base + 12, [100, 70, 40]);
        $lienzo->triangulo($x, $base - mt_rand(40, 70), $base, mt_rand(14, 22), [30, 90, 50]);
    }
    $coloresTienda = [[200, 60, 50], [240, 170, 40], [60, 110, 200], [90, 160, 80]];
    for ($i = 0; $i < mt_rand(1, 4); $i++) {
        $x = mt_rand(40, $ancho - 40);
        $base = $horizonte + mt_rand(60, 100);
        $lienzo->triangulo($x, $base - mt_rand(35, 50), $base, mt_rand(28, 40), $coloresTienda[mt_rand(0, 3)]);
        $lienzo->triangulo($x, $base - 18, $base, 8, [40, 40, 40]);
    }
    return $lienzo->png();
}

// Tique de compra: papel blanco, cabecera de color y líneas que simulan texto.
function dibujarTique(int $semilla): string
{
    mt_srand($semilla);
    $ancho = 240;
    $alto = 340;
    $lienzo = new Lienzo($ancho, $alto, [230, 230, 225]);
    $lienzo->rectangulo(14, 10, $ancho - 14, $alto - 10, [255, 255, 252]);
    $cabeceras = [[180, 40, 40], [40, 100, 170], [40, 140, 80], [120, 60, 150], [200, 120, 30]];
    $lienzo->rectangulo(14, 10, $ancho - 14, 52, $cabeceras[mt_rand(0, 4)]);
    $lienzo->rectangulo(40, 24, $ancho - 40, 32, [255, 255, 255]);
    $lienzo->rectangulo(70, 38, $ancho - 70, 43, [235, 235, 235]);
    $y = 70;
    $lineas = mt_rand(6, 12);
    for ($i = 0; $i < $lineas; $i++) {
        $lienzo->rectangulo(28, $y, 28 + mt_rand(60, 120), $y + 5, [150, 150, 150]);
        $lienzo->rectangulo($ancho - 28 - mt_rand(25, 40), $y, $ancho - 28, $y + 5, [120, 120, 120]);
        $y += 16;
    }
    // Línea discontinua y total.
    for ($x = 28; $x < $ancho - 28; $x += 8) {
        $lienzo->rectangulo($x, $y + 4, $x + 4, $y + 6, [120, 120, 120]);
    }
    $lienzo->rectangulo(28, $y + 18, 90, $y + 27, [60, 60, 60]);
    $lienzo->rectangulo($ancho - 90, $y + 16, $ancho - 28, $y + 29, [30, 30, 30]);
    // Código de barras al pie.
    for ($x = 50; $x < $ancho - 50; $x += mt_rand(2, 4)) {
        $lienzo->rectangulo($x, $alto - 50, $x + mt_rand(1, 2), $alto - 24, [40, 40, 40]);
    }
    return $lienzo->png();
}

// PDF mínimo de una página (A4) con un título y unas líneas de texto, para fichas,
// circulares y plantillas de ejemplo. Solo admite texto en latín-1 (se convierte).
function crearPdf(string $titulo, array $lineas): string
{
    $escapar = function (string $texto): string {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto) ?: $texto;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    };
    $contenido = "BT /F1 20 Tf 60 770 Td (" . $escapar($titulo) . ") Tj ET\n";
    $y = 730;
    foreach ($lineas as $linea) {
        $contenido .= "BT /F1 12 Tf 60 {$y} Td (" . $escapar($linea) . ") Tj ET\n";
        $y -= 22;
    }
    // Recuadros para firmar o marcar, como en un formulario en papel.
    $contenido .= "0.6 G 60 " . ($y - 90) . " 220 70 re S 320 " . ($y - 90) . " 220 70 re S\n";
    $objetos = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "endstream",
    ];
    $pdf = "%PDF-1.4\n";
    $posiciones = [];
    foreach ($objetos as $indice => $objeto) {
        $posiciones[] = strlen($pdf);
        $pdf .= ($indice + 1) . " 0 obj\n" . $objeto . "\nendobj\n";
    }
    $inicioTabla = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    foreach ($posiciones as $posicion) {
        $pdf .= sprintf("%010d 00000 n \n", $posicion);
    }
    return $pdf . "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n{$inicioTabla}\n%%EOF\n";
}
