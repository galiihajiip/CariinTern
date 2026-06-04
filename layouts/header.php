<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_session_timeout();

$page_title = $page_title ?? 'Dashboard';
$full_title = sanitize($page_title . ' - ' . APP_NAME);
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserRole = (string) ($_SESSION['user_role'] ?? '');
$notifications = $currentUserId > 0 ? get_notifications($currentUserId, 5) : [];
$notificationCount = count($notifications);
$activityLinks = [
    'admin' => '/admin/dashboard.php',
    'company' => '/company/dashboard.php',
    'student' => '/student/applications/index.php',
];
$activityLink = rtrim(BASE_URL, '/') . ($activityLinks[$currentUserRole] ?? '/index.php');
$vapidPublicKey = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
?>
<!DOCTYPE html>
<html lang="id" data-base-url="<?= rtrim(BASE_URL, '/'); ?>" data-vapid-public-key="<?= sanitize($vapidPublicKey); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $full_title; ?></title>
    <link rel="manifest" href="<?= rtrim(BASE_URL, '/'); ?>/manifest.json">
    <link rel="icon" href="<?= rtrim(BASE_URL, '/'); ?>/favicon.ico" sizes="any">
    <link rel="icon" href="<?= rtrim(BASE_URL, '/'); ?>/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= sanitize(APP_NAME); ?>">
    <link rel="apple-touch-icon" href="<?= rtrim(BASE_URL, '/'); ?>/assets/icons/icon-152x152.png">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/custom.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <?php if ($currentUserId > 0): ?>
        <div class="mobile-header d-lg-none">
            <button class="btn btn-link text-white p-0 me-3" id="sidebarToggle" aria-label="Menu" type="button">
                <i class="bi bi-list fs-4"></i>
            </button>
            <span class="text-white fw-semibold"><?= sanitize(APP_NAME); ?></span>
            <div class="ms-auto d-flex gap-2">
                <a href="<?= sanitize($activityLink); ?>" class="text-white position-relative" aria-label="Notifikasi">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                            <?= $notificationCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php endif; ?>

    <?php if ($currentUserId > 0): ?>
        <nav class="notification-navbar position-fixed top-0 end-0 p-3" style="z-index: 1060;">
            <div class="dropdown">
                <button
                    class="btn btn-light border shadow-sm position-relative rounded-circle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Notifikasi"
                    style="width: 44px; height: 44px;"
                >
                    <i class="bi bi-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-count">
                            <?= $notificationCount; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end border-0 shadow notification-dropdown p-0" style="width: min(360px, 92vw);">
                    <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Notifikasi</span>
                        <span class="badge bg-primary-subtle text-primary"><?= $notificationCount; ?> baru</span>
                    </div>

                    <div class="notification-list">
                        <?php if ($notifications === []): ?>
                            <div class="px-3 py-4 text-center text-muted small">
                                <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                                Tidak ada notifikasi baru.
                            </div>
                        <?php endif; ?>

                        <?php foreach ($notifications as $notification): ?>
                            <div class="dropdown-item-text border-bottom px-3 py-2 notification-item" data-notification-id="<?= (int) $notification['id']; ?>">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="small fw-semibold text-truncate">
                                        <?= sanitize((string) ($notification['actor_name'] ?? 'Sistem')); ?>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-link btn-sm p-0 text-decoration-none mark-notification-read"
                                        data-notification-id="<?= (int) $notification['id']; ?>"
                                        data-csrf-token="<?= sanitize(generate_csrf_token()); ?>"
                                    >
                                        Tandai
                                    </button>
                                </div>
                                <div class="small text-muted mt-1"><?= sanitize((string) $notification['description']); ?></div>
                                <div class="small text-primary mt-1"><?= sanitize(time_ago((string) $notification['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?= sanitize($activityLink); ?>" class="dropdown-item text-center small py-2">
                        Lihat Semua Aktivitas
                    </a>
                    <button type="button" class="dropdown-item justify-content-center small py-2" onclick="window.subscribePush && window.subscribePush()">
                        <i class="bi bi-bell-fill me-1"></i>
                        Aktifkan Notifikasi
                    </button>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <div class="wrapper d-flex">
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.mark-notification-read').forEach((button) => {
                button.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const notificationId = button.dataset.notificationId;
                    const csrfToken = button.dataset.csrfToken;
                    const formData = new FormData();
                    formData.append('notification_id', notificationId);
                    formData.append('csrf_token', csrfToken);

                    try {
                        const response = await fetch('<?= rtrim(BASE_URL, '/'); ?>/api/mark_notification_read.php', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const result = await response.json();

                        if (result.success) {
                            const item = button.closest('.notification-item');
                            if (item) {
                                item.remove();
                            }

                            const countBadge = document.querySelector('.notification-count');
                            if (countBadge) {
                                const nextCount = Math.max(0, parseInt(countBadge.textContent, 10) - 1);
                                countBadge.textContent = nextCount;

                                if (nextCount === 0) {
                                    countBadge.remove();
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Mark notification read failed', error);
                    }
                });
            });
        });
    </script>
