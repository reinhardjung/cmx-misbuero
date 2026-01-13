<?php
namespace CLOUDMEISTER\CMX\Buero;

$required_user = '';
$required_pass = '';

$query_user = isset($_GET['user']) ? (string) $_GET['user'] : '';
$query_pass = isset($_GET['pw']) ? (string) $_GET['pw'] : '';

if ($required_user !== '' || $required_pass !== '') {
    if ($query_user !== $required_user || $query_pass !== $required_pass) {
        header('Content-Type: text/plain; charset=utf-8', true, 401);
        echo 'Unauthorized';
        exit;
    }
}

if (!defined('ABSPATH')) {
    $dir = __DIR__;
    $wp_load = '';
    while ($dir !== '/' && $dir !== '.' && $dir !== '') {
        if (is_file($dir . '/wp-load.php')) {
            $wp_load = $dir . '/wp-load.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    if ($wp_load !== '') {
        require_once $wp_load;
    }
}

function cmx_anyboard_pie_svg_local(float $rechnungen, float $ausgaben): string
{
    $total = $rechnungen + $ausgaben;
    $size = 240;
    $cx = $size / 2;
    $cy = $size / 2;
    $r = 100;

    if ($total <= 0) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="#7a7a7a"/>'
            . '</svg>';
    }

    $angle_a = ($rechnungen / $total) * 360.0;
    $path_a = cmx_anyboard_pie_slice_path_local($cx, $cy, $r, 0.0, $angle_a);
    $path_b = cmx_anyboard_pie_slice_path_local($cx, $cy, $r, $angle_a, 360.0 - $angle_a);

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
        . '<path d="' . $path_a . '" fill="#2ecc71"/>'
        . '<path d="' . $path_b . '" fill="#e74c3c"/>'
        . '<circle cx="' . $cx . '" cy="' . $cy . '" r="50" fill="#1f2430"/>'
        . '</svg>';
}

function cmx_anyboard_pie_slice_path_local(float $cx, float $cy, float $r, float $start, float $sweep): string
{
    $start_rad = deg2rad($start - 90.0);
    $end_rad = deg2rad($start + $sweep - 90.0);

    $x1 = $cx + $r * cos($start_rad);
    $y1 = $cy + $r * sin($start_rad);
    $x2 = $cx + $r * cos($end_rad);
    $y2 = $cy + $r * sin($end_rad);

    $large_arc = $sweep > 180.0 ? 1 : 0;

    return 'M ' . $cx . ' ' . $cy . ' L ' . $x1 . ' ' . $y1 . ' A ' . $r . ' ' . $r . ' 0 ' . $large_arc . ' 1 ' . $x2 . ' ' . $y2 . ' Z';
}

$year = function_exists('current_time') ? (int) current_time('Y') : (int) date('Y');
$rechnungen_sum = 0.0;
$ausgaben_sum = 0.0;

if (function_exists(__NAMESPACE__ . '\\cmx_anyboard_rechnungen_stats')) {
    $rechnungen = cmx_anyboard_rechnungen_stats($year);
    $rechnungen_sum = (float) ($rechnungen['paid_sum'] ?? 0.0);
}

if (function_exists(__NAMESPACE__ . '\\cmx_anyboard_ausgaben_stats')) {
    $ausgaben = cmx_anyboard_ausgaben_stats($year);
    $ausgaben_sum = (float) ($ausgaben['paid_sum'] ?? 0.0);
}

if (function_exists('imagecreatetruecolor')) {
    $size = 240;
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $green = imagecolorallocate($img, 46, 204, 113);
    $red = imagecolorallocate($img, 231, 76, 60);
    $gray = imagecolorallocate($img, 120, 120, 120);

    $total = $rechnungen_sum + $ausgaben_sum;
    if ($total <= 0) {
        imagefilledarc($img, $size / 2, $size / 2, 200, 200, 0, 360, $gray, IMG_ARC_PIE);
    } else {
        $angle_rechnungen = ($rechnungen_sum / $total) * 360.0;
        $start = 0.0;
        imagefilledarc($img, $size / 2, $size / 2, 200, 200, $start, $start + $angle_rechnungen, $green, IMG_ARC_PIE);
        imagefilledarc($img, $size / 2, $size / 2, 200, 200, $start + $angle_rechnungen, 360.0, $red, IMG_ARC_PIE);
    }

    header('Content-Type: image/png');
    imagepng($img);
    imagedestroy($img);
    exit;
}

$svg = cmx_anyboard_pie_svg_local($rechnungen_sum, $ausgaben_sum);
header('Content-Type: image/svg+xml; charset=utf-8');
echo $svg;
