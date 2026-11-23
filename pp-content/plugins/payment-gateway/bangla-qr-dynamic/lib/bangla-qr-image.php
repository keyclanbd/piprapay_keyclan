<?php
/**
 * Renders a QR module matrix to PNG (GD) or SVG (no extension required).
 */

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/**
 * True when the GD extension is available for PNG rendering.
 *
 * @return bool
 */
function bqr_can_render_png()
{
    return function_exists('imagecreate') && function_exists('imagepng');
}

/**
 * Render a matrix to a PNG binary string.
 *
 * @param array{size:int, modules:array<int, array<int, int>>} $qr
 * @param int $scale  Pixels per module.
 * @param int $border Quiet zone in modules (the spec requires at least 4).
 * @return string|false PNG bytes, or false when GD is unavailable or fails.
 */
function bqr_render_png(array $qr, $scale = 8, $border = 4)
{
    if (!bqr_can_render_png()) {
        return false;
    }

    $size = (int) $qr['size'];
    $scale = max(1, min(40, (int) $scale));
    $border = max(0, min(20, (int) $border));
    $modules = $qr['modules'];

    $px = ($size + $border * 2) * $scale;
    if ($px < 1 || $px > 4000) {
        return false;
    }

    $image = imagecreate($px, $px);
    if ($image === false) {
        return false;
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, $px - 1, $px - 1, $white);

    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (empty($modules[$y][$x])) {
                continue;
            }
            $x1 = ($x + $border) * $scale;
            $y1 = ($y + $border) * $scale;
            imagefilledrectangle($image, $x1, $y1, $x1 + $scale - 1, $y1 + $scale - 1, $black);
        }
    }

    ob_start();
    $ok = imagepng($image, null, 9);
    $png = ob_get_clean();
    imagedestroy($image);

    if (!$ok || !is_string($png) || $png === '') {
        return false;
    }

    return $png;
}

/**
 * Render a matrix to an SVG document. Used when GD is missing and for crisp
 * print output.
 *
 * @param array{size:int, modules:array<int, array<int, int>>} $qr
 * @param int $scale
 * @param int $border
 * @return string
 */
function bqr_render_svg(array $qr, $scale = 8, $border = 4)
{
    $size = (int) $qr['size'];
    $scale = max(1, min(40, (int) $scale));
    $border = max(0, min(20, (int) $border));
    $modules = $qr['modules'];

    $dim = $size + $border * 2;
    $px = $dim * $scale;

    $path = '';
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (empty($modules[$y][$x])) {
                continue;
            }
            $path .= 'M' . ($x + $border) . ' ' . ($y + $border) . 'h1v1h-1z';
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $px . '" height="' . $px
        . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges">'
        . '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>'
        . '<path d="' . $path . '" fill="#000000"/>'
        . '</svg>';
}

/**
 * A data URI for direct embedding in an <img> tag.
 *
 * @param array $qr
 * @param int $scale
 * @param int $border
 * @return string
 */
function bqr_render_data_uri(array $qr, $scale = 8, $border = 4)
{
    $png = bqr_render_png($qr, $scale, $border);
    if ($png !== false) {
        return 'data:image/png;base64,' . base64_encode($png);
    }

    return 'data:image/svg+xml;base64,' . base64_encode(bqr_render_svg($qr, $scale, $border));
}

/**
 * Load an uploaded image as a GD truecolour resource, flattening transparency
 * onto white so dark-on-transparent QR codes still read correctly.
 *
 * @param string $path
 * @return resource|\GdImage|null
 */
function bqr_load_image($path)
{
    if (!function_exists('imagecreatefromstring') || !is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $source = @imagecreatefromstring($raw);
    if ($source === false || $source === null) {
        return null;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000) {
        imagedestroy($source);
        return null;
    }

    $flat = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefilledrectangle($flat, 0, 0, $width - 1, $height - 1, $white);
    imagecopy($flat, $source, 0, 0, 0, 0, $width, $height);
    imagedestroy($source);

    return $flat;
}

/**
 * Convert a GD image to a grayscale matrix of 0-255 luminance values.
 *
 * @param resource|\GdImage $image
 * @return array{w:int, h:int, pixels:array<int, array<int, int>>}
 */
function bqr_image_luminance($image)
{
    $width = imagesx($image);
    $height = imagesy($image);
    $pixels = array();

    for ($y = 0; $y < $height; $y++) {
        $row = array();
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // Rec. 601 luma, integer arithmetic.
            $row[] = (int) (($r * 299 + $g * 587 + $b * 114) / 1000);
        }
        $pixels[] = $row;
    }

    return array('w' => $width, 'h' => $height, 'pixels' => $pixels);
}
