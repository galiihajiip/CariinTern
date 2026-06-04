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
    <title>403 - Akses Ditolak | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #fff5f5 0%, #fff8f0 45%, #ffffff 100%);
        }

        .error-card {
            max-width: 760px;
            border: 0;
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(220, 53, 69, 0.12);
        }

        .error-code {
            font-size: clamp(4rem, 12vw, 8rem);
            font-weight: 800;
            letter-spacing: -0.08em;
            color: #dc3545;
            line-height: 1;
        }

        .shield {
            animation: pulse 2.6s ease-in-out infinite;
            transform-origin: center;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.04); }
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <main class="container">
        <div class="card error-card mx-auto">
            <div class="card-body p-4 p-md-5 text-center">
                <svg class="mb-4" width="210" height="150" viewBox="0 0 210 150" role="img" aria-label="Ilustrasi akses ditolak">
                    <path class="shield" d="M105 18l58 20v40c0 36-23 58-58 72-35-14-58-36-58-72V38l58-20z" fill="#f8d7da"/>
                    <path d="M105 36l38 13v30c0 23-14 39-38 50-24-11-38-27-38-50V49l38-13z" fill="#dc3545"/>
                    <rect x="82" y="72" width="46" height="34" rx="8" fill="#fff"/>
                    <path d="M91 72v-9a14 14 0 1128 0v9" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round"/>
                    <circle cx="105" cy="88" r="5" fill="#dc3545"/>
                    <path d="M105 93v7" stroke="#dc3545" stroke-width="5" stroke-linecap="round"/>
                </svg>
                <div class="error-code mb-3">403</div>
                <h1 class="h3 fw-bold mb-3">Akses Ditolak</h1>
                <p class="text-muted mb-4">
                    Kamu tidak memiliki izin untuk membuka halaman ini. Pastikan kamu login dengan akun dan role yang sesuai.
                </p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-danger px-4">
                        <i class="bi bi-shield-lock me-1"></i><?= htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8'); ?>
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
