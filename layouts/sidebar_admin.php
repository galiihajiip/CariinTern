<?php

require_once __DIR__ . '/../includes/functions.php';

$currentPath = $_SERVER['PHP_SELF'] ?? '';
$userName = sanitize((string) ($_SESSION['user_name'] ?? 'Admin'));

if (!function_exists('admin_sidebar_is_active')) {
    function admin_sidebar_is_active(string $href, string $currentPath): bool
    {
        return str_contains($currentPath, $href);
    }
}

$menuItems = [
    [
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'href' => 'admin/dashboard.php',
    ],
    [
        'label' => 'Manajemen User',
        'icon' => 'bi-people',
        'href' => 'admin/users/index.php',
    ],
    [
        'label' => 'Verifikasi Perusahaan',
        'icon' => 'bi-building-check',
        'href' => 'admin/companies/index.php',
    ],
    [
        'label' => 'Kategori Magang',
        'icon' => 'bi-tags',
        'href' => 'admin/categories/index.php',
    ],
    [
        'label' => 'Program Studi',
        'icon' => 'bi-mortarboard',
        'href' => 'admin/programs/index.php',
    ],
    [
        'label' => 'Semua Lowongan',
        'icon' => 'bi-briefcase',
        'href' => 'admin/jobs/index.php',
    ],
    [
        'label' => 'Auto Scraper',
        'icon' => 'bi-robot',
        'href' => 'admin/scraper/index.php',
    ],
    [
        'label' => 'Laporan',
        'icon' => 'bi-bar-chart',
        'href' => 'admin/reports.php',
    ],
];
?>

<aside
    id="adminSidebar"
    class="sidebar sidebar-admin bg-dark text-white"
>
    <div class="d-flex flex-column h-100 p-3">
        <div class="mb-4">
            <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/dashboard.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
                <i class="bi bi-mortarboard-fill fs-3 me-2"></i>
                <span class="fs-5 fw-bold"><?= sanitize(APP_NAME); ?></span>
            </a>
            <div class="small text-white-50">Masuk sebagai</div>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <span class="fw-semibold text-truncate"><?= $userName; ?></span>
                <span class="badge bg-primary ms-2">Admin</span>
            </div>
        </div>

        <nav class="nav nav-pills flex-column gap-1">
            <?php foreach ($menuItems as $item): ?>
                <?php
                $isActive = admin_sidebar_is_active($item['href'], $currentPath);
                $linkClass = $isActive ? 'nav-link active' : 'nav-link text-white-50';
                ?>
                <a class="<?= $linkClass; ?>" href="<?= rtrim(BASE_URL, '/'); ?>/<?= $item['href']; ?>">
                    <i class="bi <?= $item['icon']; ?> me-2"></i>
                    <?= sanitize($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <hr class="border-secondary my-3">

        <nav class="nav nav-pills flex-column mt-auto">
            <a class="nav-link text-white-50" href="<?= rtrim(BASE_URL, '/'); ?>/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>
                Logout
            </a>
        </nav>
    </div>
</aside>

<div class="content admin-content flex-grow-1 p-4">
