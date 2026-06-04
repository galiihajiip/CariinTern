<?php

require_once __DIR__ . '/../includes/functions.php';

$currentPath = $_SERVER['PHP_SELF'] ?? '';
$userName = sanitize((string) ($_SESSION['user_name'] ?? 'Perusahaan'));
$isVerified = filter_var($_SESSION['company_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!function_exists('company_sidebar_is_active')) {
    function company_sidebar_is_active(string $href, string $currentPath): bool
    {
        return str_contains($currentPath, $href);
    }
}

$menuItems = [
    [
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'href' => 'company/dashboard.php',
    ],
    [
        'label' => 'Profil Perusahaan',
        'icon' => 'bi-building',
        'href' => 'company/profile.php',
    ],
    [
        'label' => 'Lowongan Saya',
        'icon' => 'bi-briefcase',
        'href' => 'company/jobs/index.php',
    ],
    [
        'label' => 'Buat Lowongan Baru',
        'icon' => 'bi-plus-circle',
        'href' => 'company/jobs/create.php',
    ],
    [
        'label' => 'Daftar Pelamar',
        'icon' => 'bi-person-lines-fill',
        'href' => 'company/applicants/index.php',
    ],
];
?>

<aside
    id="companySidebar"
    class="sidebar sidebar-company bg-dark text-white"
>
    <div class="d-flex flex-column h-100 p-3">
        <div class="mb-4">
            <a href="<?= rtrim(BASE_URL, '/'); ?>/company/dashboard.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
                <i class="bi bi-building-fill fs-3 me-2"></i>
                <span class="fs-5 fw-bold"><?= sanitize(APP_NAME); ?></span>
            </a>
            <div class="small text-white-50">Masuk sebagai</div>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <span class="fw-semibold text-truncate"><?= $userName; ?></span>
                <span class="badge bg-info ms-2">Perusahaan</span>
            </div>
            <div class="mt-3">
                <?php if ($isVerified): ?>
                    <span class="badge bg-success">Terverifikasi</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Belum Terverifikasi</span>
                <?php endif; ?>
            </div>
        </div>

        <nav class="nav nav-pills flex-column gap-1">
            <?php foreach ($menuItems as $item): ?>
                <?php
                $isActive = company_sidebar_is_active($item['href'], $currentPath);
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

<div class="content company-content flex-grow-1 p-4">
