<?php

require_once __DIR__ . '/../includes/functions.php';

$currentPath = $_SERVER['PHP_SELF'] ?? '';
$userName = sanitize((string) ($_SESSION['user_name'] ?? 'Mahasiswa'));
$profileCompleted = $_SESSION['profile_completed'] ?? 0;

if (is_bool($profileCompleted)) {
    $profileProgress = $profileCompleted ? 100 : 0;
} else {
    $profileProgress = max(0, min(100, (int) $profileCompleted));
}

if (!function_exists('student_sidebar_is_active')) {
    function student_sidebar_is_active(string $href, string $currentPath): bool
    {
        return str_contains($currentPath, $href);
    }
}

$menuItems = [
    [
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'href' => 'student/dashboard.php',
    ],
    [
        'label' => 'Profil Saya',
        'icon' => 'bi-person-circle',
        'href' => 'student/profile.php',
    ],
    [
        'label' => 'Cari Lowongan',
        'icon' => 'bi-search',
        'href' => 'student/jobs/index.php',
    ],
    [
        'label' => 'Lamaran Saya',
        'icon' => 'bi-file-earmark-text',
        'href' => 'student/applications/index.php',
    ],
];
?>

<button
    class="btn btn-dark d-md-none position-fixed top-0 start-0 m-3"
    type="button"
    data-bs-toggle="collapse"
    data-bs-target="#studentSidebar"
    aria-controls="studentSidebar"
    aria-expanded="false"
    aria-label="Toggle sidebar"
    style="z-index: 1050;"
>
    <i class="bi bi-list fs-4"></i>
</button>

<aside
    id="studentSidebar"
    class="sidebar-student collapse d-md-block bg-dark text-white position-fixed top-0 start-0 vh-100 overflow-auto"
    style="width: 250px; z-index: 1040;"
>
    <div class="d-flex flex-column h-100 p-3">
        <div class="mb-4">
            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/dashboard.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
                <i class="bi bi-mortarboard-fill fs-3 me-2"></i>
                <span class="fs-5 fw-bold"><?= sanitize(APP_NAME); ?></span>
            </a>
            <div class="small text-white-50">Masuk sebagai</div>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <span class="fw-semibold text-truncate"><?= $userName; ?></span>
                <span class="badge bg-success ms-2">Mahasiswa</span>
            </div>

            <div class="mt-3">
                <div class="d-flex justify-content-between small text-white-50 mb-1">
                    <span>Kelengkapan Profil</span>
                    <span><?= $profileProgress; ?>%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div
                        class="progress-bar bg-success"
                        role="progressbar"
                        style="width: <?= $profileProgress; ?>%;"
                        aria-valuenow="<?= $profileProgress; ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>
            </div>
        </div>

        <nav class="nav nav-pills flex-column gap-1">
            <?php foreach ($menuItems as $item): ?>
                <?php
                $isActive = student_sidebar_is_active($item['href'], $currentPath);
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

<style>
    @media (max-width: 767.98px) {
        .student-content {
            margin-left: 0 !important;
            padding-top: 4.5rem !important;
        }
    }
</style>

<div class="content student-content flex-grow-1 p-4" style="margin-left: 250px;">
