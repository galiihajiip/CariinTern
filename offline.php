<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - <?= sanitize(APP_NAME); ?></title>
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <meta name="theme-color" content="#6366f1">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(99, 102, 241, 0.18), transparent 30%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
        }

        .offline-card {
            max-width: 720px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(16px);
        }

        .signal-line {
            animation: pulse 1.6s ease-in-out infinite;
            transform-origin: center;
        }

        .signal-line:nth-child(2) {
            animation-delay: 0.2s;
        }

        .signal-line:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.25; transform: scale(0.96); }
            50% { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <main class="container">
        <section class="offline-card mx-auto p-4 p-md-5 text-center">
            <svg class="mb-4" width="220" height="170" viewBox="0 0 220 170" role="img" aria-label="Ilustrasi koneksi offline">
                <circle cx="110" cy="82" r="70" fill="#1d4ed8" opacity="0.2"/>
                <path class="signal-line" d="M60 78c28-26 72-26 100 0" fill="none" stroke="#93c5fd" stroke-width="10" stroke-linecap="round"/>
                <path class="signal-line" d="M82 104c16-14 40-14 56 0" fill="none" stroke="#bfdbfe" stroke-width="10" stroke-linecap="round"/>
                <circle class="signal-line" cx="110" cy="130" r="9" fill="#dbeafe"/>
                <path d="M70 38l80 92" stroke="#f97316" stroke-width="10" stroke-linecap="round"/>
            </svg>

            <p class="text-uppercase text-info fw-semibold small mb-2">CariinTern Offline Mode</p>
            <h1 class="display-6 fw-bold mb-3">Kamu Sedang Offline</h1>
            <p class="text-white-50 mb-4">
                Koneksi internet sedang tidak tersedia. Beberapa halaman yang sudah pernah dibuka masih bisa diakses dari cache.
            </p>

            <div class="text-start bg-white bg-opacity-10 rounded-4 p-3 p-md-4 mb-4">
                <div class="fw-semibold mb-2">Halaman yang biasanya tersedia offline:</div>
                <ul class="mb-0 text-white-50">
                    <li>Beranda CariinTern</li>
                    <li>Halaman login</li>
                    <li>Asset tampilan seperti CSS, JS, dan icon aplikasi</li>
                </ul>
            </div>

            <button type="button" class="btn btn-primary px-4" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Coba Lagi
            </button>
        </section>
    </main>

    <script src="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('online', () => {
            const previousUrl = sessionStorage.getItem('cariintern-last-online-url');
            window.location.href = previousUrl || '<?= rtrim(BASE_URL, '/'); ?>/index.php';
        });
    </script>
</body>
</html>
