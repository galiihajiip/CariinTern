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
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$generated = [];
$errors = [];

if (!extension_loaded('gd')) {
    $errors[] = 'Ekstensi GD belum aktif. Aktifkan GD untuk generate icon.';
} else {
    if (!is_dir($iconDir) && !mkdir($iconDir, 0755, true)) {
        $errors[] = 'Folder assets/icons tidak dapat dibuat.';
    }

    if ($errors === [] && !is_file($sourcePath)) {
        $source = imagecreatetruecolor(512, 512);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $bgStart = imagecolorallocate($source, 99, 102, 241);
        $bgEnd = imagecolorallocate($source, 14, 165, 233);
        for ($y = 0; $y < 512; $y++) {
            $ratio = $y / 511;
            $r = (int) (99 + (14 - 99) * $ratio);
            $g = (int) (102 + (165 - 102) * $ratio);
            $b = (int) (241 + (233 - 241) * $ratio);
            $color = imagecolorallocate($source, $r, $g, $b);
            imageline($source, 0, $y, 512, $y, $color);
        }

        $white = imagecolorallocate($source, 255, 255, 255);
        $dark = imagecolorallocatealpha($source, 15, 23, 42, 40);
        imagefilledellipse($source, 256, 256, 360, 360, $dark);

        $font = 5;
        $text = 'CT';
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        imagestring($source, $font, (512 - $textWidth) / 2, (512 - $textHeight) / 2, $text, $white);

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
