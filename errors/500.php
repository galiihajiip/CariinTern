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
    <title>500 - Server Error | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f4f7fb 0%, #eef5ff 45%, #ffffff 100%);
        }

        .error-card {
            max-width: 760px;
            border: 0;
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(33, 37, 41, 0.12);
        }

        .error-code {
            font-size: clamp(4rem, 12vw, 8rem);
            font-weight: 800;
            letter-spacing: -0.08em;
            color: #212529;
            line-height: 1;
        }

        .gear {
            animation: spin 7s linear infinite;
            transform-origin: center;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <main class="container">
        <div class="card error-card mx-auto">
            <div class="card-body p-4 p-md-5 text-center">
                <svg class="mb-4" width="220" height="150" viewBox="0 0 220 150" role="img" aria-label="Ilustrasi server error">
                    <rect x="42" y="32" width="136" height="86" rx="18" fill="#e9ecef"/>
                    <rect x="62" y="52" width="96" height="12" rx="6" fill="#adb5bd"/>
                    <rect x="62" y="76" width="62" height="10" rx="5" fill="#ced4da"/>
                    <circle cx="153" cy="82" r="8" fill="#0d6efd"/>
                    <g class="gear">
                        <circle cx="72" cy="112" r="18" fill="#212529"/>
                        <circle cx="72" cy="112" r="7" fill="#fff"/>
                        <path d="M72 84v13M72 127v13M44 112h13M87 112h13M52 92l9 9M83 123l9 9M92 92l-9 9M61 123l-9 9" stroke="#212529" stroke-width="7" stroke-linecap="round"/>
                    </g>
                </svg>
                <div class="error-code mb-3">500</div>
                <h1 class="h3 fw-bold mb-3">Terjadi kesalahan server</h1>
                <p class="text-muted mb-4">
                    Maaf, sistem sedang mengalami kendala. Silakan coba lagi beberapa saat lagi atau kembali ke halaman utama.
                </p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-dark px-4">
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
