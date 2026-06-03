<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(get_dashboard_url((string) ($_SESSION['user_role'] ?? '')));
}

$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/login.php');
    }

    $email = sanitize((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));
    $emailValue = $email;

    $result = login_user($email, $password);

    if ($result['success']) {
        redirect(get_dashboard_url((string) $result['role']));
    }

    set_flash('error', $result['message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= sanitize(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9Oer+R4sF86dIHNDz4JxQmK2h5ZC7pERxKED7ZtGh6y9" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/custom.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(13, 110, 253, 0.92), rgba(33, 37, 41, 0.88)),
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.3), transparent 30%);
        }

        .login-card {
            max-width: 400px;
            width: 100%;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.18);
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            font-size: 2rem;
        }
    </style>
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center px-3 py-5">
        <div class="w-100" style="max-width: 400px;">
            <div class="text-center text-white mb-4">
                <div class="brand-icon mb-3">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h1 class="h3 fw-bold mb-1"><?= sanitize(APP_NAME); ?></h1>
                <p class="mb-0 text-white-50">Masuk untuk mengelola pendaftaran magang</p>
            </div>

            <?= display_flash(); ?>

            <div class="card login-card">
                <div class="card-body p-4">
                    <h2 class="h4 fw-bold mb-1">Login</h2>
                    <p class="text-muted mb-4">Gunakan email dan password akun Anda.</p>

                    <form id="loginForm" method="POST" action="login.php" novalidate>
                        <?= csrf_field(); ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    value="<?= $emailValue; ?>"
                                    required
                                    autocomplete="email"
                                    placeholder="nama@email.com"
                                >
                            </div>
                            <div class="invalid-feedback d-block" id="emailError"></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Masukkan password"
                                >
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback d-block" id="passwordError"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember_me">
                                <label class="form-check-label" for="rememberMe">Ingat Saya</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            Masuk
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="text-muted">Belum punya akun?</span>
                        <a href="register.php" class="fw-semibold text-decoration-none">Daftar sekarang</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const emailError = document.getElementById('emailError');
        const passwordError = document.getElementById('passwordError');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePassword.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            togglePassword.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
        });

        form.addEventListener('submit', event => {
            let isValid = true;
            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            emailError.textContent = '';
            passwordError.textContent = '';
            emailInput.classList.remove('is-invalid');
            passwordInput.classList.remove('is-invalid');

            if (!emailPattern.test(email)) {
                emailError.textContent = 'Masukkan format email yang valid.';
                emailInput.classList.add('is-invalid');
                isValid = false;
            }

            if (password === '') {
                passwordError.textContent = 'Password tidak boleh kosong.';
                passwordInput.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
