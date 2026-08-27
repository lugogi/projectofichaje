<?php

/**
 * Genera los iconos PNG de la PWA (reloj sobre fondo índigo).
 * Uso: php scripts/generate-pwa-icons.php
 */

$outputDir = __DIR__ . '/../public/icons';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

/**
 * @param int  $size    Lado del icono en píxeles
 * @param bool $maskable Añade margen de seguridad para iconos "maskable"
 */
function renderIcon(int $size, bool $maskable = false): \GdImage
{
    $ss = 4; // supersampling para bordes suaves
    $canvas = imagecreatetruecolor($size * $ss, $size * $ss);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $w = $size * $ss;
    $indigo = imagecolorallocate($canvas, 79, 70, 229);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $indigoDark = imagecolorallocate($canvas, 55, 48, 163);

    imagefilledrectangle($canvas, 0, 0, $w, $w, $indigo);

    $cx = $w / 2;
    $cy = $w / 2;
    // El icono maskable necesita que el contenido quepa en el 80% central
    $dialRadius = $maskable ? $w * 0.30 : $w * 0.36;

    imagefilledellipse($canvas, (int) $cx, (int) $cy, (int) ($dialRadius * 2), (int) ($dialRadius * 2), $white);
    imagefilledellipse($canvas, (int) $cx, (int) $cy, (int) ($dialRadius * 1.82), (int) ($dialRadius * 1.82), $indigo);
    imagefilledellipse($canvas, (int) $cx, (int) $cy, (int) ($dialRadius * 1.66), (int) ($dialRadius * 1.66), $white);

    // Manecillas apuntando a las 10:10
    $thickness = max(1, (int) ($dialRadius * 0.11));

    $drawHand = function (float $angleDeg, float $lengthFactor) use ($canvas, $cx, $cy, $dialRadius, $thickness, $indigoDark) {
        $rad = deg2rad($angleDeg - 90);
        $x = $cx + cos($rad) * $dialRadius * $lengthFactor;
        $y = $cy + sin($rad) * $dialRadius * $lengthFactor;
        imagesetthickness($canvas, $thickness);
        imageline($canvas, (int) $cx, (int) $cy, (int) $x, (int) $y, $indigoDark);
    };

    $drawHand(300, 0.60); // hora
    $drawHand(60, 0.82);  // minuto

    imagefilledellipse($canvas, (int) $cx, (int) $cy, $thickness * 2, $thickness * 2, $indigoDark);

    $final = imagecreatetruecolor($size, $size);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    imagecopyresampled($final, $canvas, 0, 0, 0, 0, $size, $size, $w, $w);
    imagedestroy($canvas);

    return $final;
}

$targets = [
    ['file' => 'icon-192.png', 'size' => 192, 'maskable' => false],
    ['file' => 'icon-512.png', 'size' => 512, 'maskable' => false],
    ['file' => 'icon-maskable-512.png', 'size' => 512, 'maskable' => true],
    ['file' => 'apple-touch-icon.png', 'size' => 180, 'maskable' => false],
    ['file' => 'badge-72.png', 'size' => 72, 'maskable' => false],
];

foreach ($targets as $target) {
    $image = renderIcon($target['size'], $target['maskable']);
    imagepng($image, $outputDir . '/' . $target['file']);
    imagedestroy($image);
    echo "Generado: {$target['file']} ({$target['size']}x{$target['size']})\n";
}

echo "Iconos creados en public/icons\n";
