<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

$role = (string) ($_SESSION['user_role'] ?? '');
$dashboardMap = [
    'admin' => '/admin/dashboard.php',
    'company' => '/company/dashboard.php',
    'student' => '/student/dashboard.php',
];
$targetUrl = rtrim(BASE_URL, '/') . ($dashboardMap[$role] ?? '/index.php');
$buttonLabel = isset($dashboardMap[$role]) ? 'Kembali ke Dashboard' : 'Kembali ke Beranda';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef5ff 0%, #f8fbff 45%, #ffffff 100%);
        }

        .error-card {
            max-width: 760px;
            border: 0;
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(13, 110, 253, 0.12);
        }

        .error-code {
            font-size: clamp(4rem, 12vw, 8rem);
            font-weight: 800;
            letter-spacing: -0.08em;
            color: #0d6efd;
            line-height: 1;
        }

        .floating-dot {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <main class="container">
        <div class="card error-card mx-auto">
            <div class="card-body p-4 p-md-5 text-center">
                <svg class="mb-4" width="220" height="150" viewBox="0 0 220 150" role="img" aria-label="Ilustrasi halaman tidak ditemukan">
                    <rect x="35" y="35" width="150" height="88" rx="18" fill="#e7f1ff"/>
                    <rect x="55" y="55" width="110" height="10" rx="5" fill="#9ec5fe"/>
                    <rect x="55" y="78" width="72" height="8" rx="4" fill="#cfe2ff"/>
                    <circle class="floating-dot" cx="167" cy="30" r="12" fill="#0d6efd"/>
                    <path d="M76 112c12-18 24-18 36 0 10-15 22-16 34-2" fill="none" stroke="#0d6efd" stroke-width="7" stroke-linecap="round"/>
                    <circle cx="80" cy="92" r="5" fill="#0d6efd"/>
                    <circle cx="140" cy="92" r="5" fill="#0d6efd"/>
                </svg>
                <div class="error-code mb-3">404</div>
                <h1 class="h3 fw-bold mb-3">Halaman tidak ditemukan</h1>
                <p class="text-muted mb-4">
                    Link yang kamu buka mungkin sudah berubah, dihapus, atau alamatnya salah ketik.
                </p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary px-4">
                        <i class="bi bi-house-door me-1"></i><?= htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="history.back()">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
