<?php

if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/../includes/auth.php';
    require_role('admin');
} else {
    require_once __DIR__ . '/../config/config.php';
}

$iconDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icons';
$sourcePath = $iconDir . DIRECTORY_SEPARATOR . 'source.png';
$faviconPath = $iconDir . DIRECTORY_SEPARATOR . 'favicon.ico';
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$generated = [];
$errors = [];

function icon_color($image, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
}

function draw_rounded_rect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function create_brand_icon(int $size)
{
    $image = imagecreatetruecolor($size, $size);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    $radius = (int) round($size * 0.265);
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / max(1, $size - 1);
        $r = (int) round(99 + (14 - 99) * $ratio);
        $g = (int) round(102 + (165 - 102) * $ratio);
        $b = (int) round(241 + (233 - 241) * $ratio);
        $color = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $size, $y, $color);
    }

    $mask = imagecreatetruecolor($size, $size);
    imagealphablending($mask, false);
    imagesavealpha($mask, true);
    imagefill($mask, 0, 0, $transparent);
    draw_rounded_rect($mask, 0, 0, $size - 1, $size - 1, $radius, imagecolorallocate($mask, 0, 0, 0));

    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            $alpha = (imagecolorat($mask, $x, $y) & 0x7F000000) >> 24;
            if ($alpha === 127) {
                imagesetpixel($image, $x, $y, $transparent);
            }
        }
    }
    imagedestroy($mask);

    $shadow = icon_color($image, '#0f172a', 112);
    imagefilledellipse($image, (int) round($size * 0.5), (int) round($size * 0.5), (int) round($size * 0.7), (int) round($size * 0.7), $shadow);

    $white = icon_color($image, '#ffffff');
    $capTop = [
        (int) round($size * 0.5), (int) round($size * 0.26),
        (int) round($size * 0.18), (int) round($size * 0.42),
        (int) round($size * 0.5), (int) round($size * 0.58),
        (int) round($size * 0.82), (int) round($size * 0.42),
    ];
    imagefilledpolygon($image, $capTop, 4, $white);

    $capBand = [
        (int) round($size * 0.32), (int) round($size * 0.52),
        (int) round($size * 0.5), (int) round($size * 0.61),
        (int) round($size * 0.68), (int) round($size * 0.52),
        (int) round($size * 0.68), (int) round($size * 0.65),
        (int) round($size * 0.5), (int) round($size * 0.76),
        (int) round($size * 0.32), (int) round($size * 0.65),
    ];
    imagefilledpolygon($image, $capBand, 6, $white);

    imagesetthickness($image, max(2, (int) round($size * 0.04)));
    imageline(
        $image,
        (int) round($size * 0.74),
        (int) round($size * 0.46),
        (int) round($size * 0.74),
        (int) round($size * 0.62),
        $white
    );
    imagefilledellipse(
        $image,
        (int) round($size * 0.74),
        (int) round($size * 0.68),
        (int) round($size * 0.08),
        (int) round($size * 0.08),
        $white
    );

    return $image;
}

function save_png_as_ico($image, string $path, int $size): void
{
    ob_start();
    imagepng($image);
    $pngData = (string) ob_get_clean();
    $pngSize = strlen($pngData);
    $dimension = $size >= 256 ? 0 : $size;

    $icoHeader = pack('vvv', 0, 1, 1);
    $icoDirectory = pack('CCCCvvVV', $dimension, $dimension, 0, 0, 1, 32, $pngSize, 22);

    file_put_contents($path, $icoHeader . $icoDirectory . $pngData);
}

if (!extension_loaded('gd')) {
    $errors[] = 'Ekstensi GD belum aktif. Aktifkan GD untuk generate icon.';
} else {
    if (!is_dir($iconDir) && !mkdir($iconDir, 0755, true)) {
        $errors[] = 'Folder assets/icons tidak dapat dibuat.';
    }

    if ($errors === []) {
        $source = create_brand_icon(512);
        imagepng($source, $sourcePath);
        imagedestroy($source);
        $generated[] = 'source.png';
    }

    if ($errors === []) {
        $source = imagecreatefrompng($sourcePath);

        if (!$source) {
            $errors[] = 'assets/icons/source.png tidak dapat dibaca.';
        } else {
            foreach ($sizes as $size) {
                $target = imagecreatetruecolor($size, $size);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

                $filename = 'icon-' . $size . 'x' . $size . '.png';
                imagepng($target, $iconDir . DIRECTORY_SEPARATOR . $filename);
                imagedestroy($target);
                $generated[] = $filename;
            }

            imagedestroy($source);

            $favicon = create_brand_icon(64);
            save_png_as_ico($favicon, $faviconPath, 64);
            imagedestroy($favicon);
            $generated[] = 'favicon.ico';
        }
    }
}

if (PHP_SAPI === 'cli') {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }

    foreach ($generated as $filename) {
        echo $filename . PHP_EOL;
    }

    exit($errors === [] ? 0 : 1);
}

$page_title = 'Generate PWA Icons';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_admin.php';
?>

<div class="page-header">
    <h1 class="h3 fw-bold mb-1">Generate PWA Icons</h1>
    <p class="text-muted mb-0">Membuat semua ukuran icon PWA dari <code>assets/icons/source.png</code>.</p>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= sanitize($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success">Icon berhasil digenerate.</div>
            <ul class="mb-0">
                <?php foreach ($generated as $filename): ?>
                    <li><code><?= sanitize($filename); ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
