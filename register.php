<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(get_dashboard_url((string) ($_SESSION['user_role'] ?? '')));
}

$old = [
    'name' => '',
    'email' => '',
    'role' => 'student',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $role = trim((string) ($_POST['role'] ?? ''));
    $errors = [];

    $old = [
        'name' => sanitize($name),
        'email' => sanitize($email),
        'role' => in_array($role, ['company', 'student'], true) ? $role : 'student',
    ];

    if (strlen($name) < 3) {
        $errors[] = 'Nama minimal 3 karakter';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Za-z]/', $password) ||
        !preg_match('/\d/', $password)
    ) {
        $errors[] = 'Password minimal 8 karakter dan harus mengandung huruf serta angka';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Konfirmasi password tidak sama';
    }

    if (!in_array($role, ['company', 'student'], true)) {
        $errors[] = 'Role tidak valid';
    }

    if ($errors === []) {
        $result = register_user([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ]);

        if ($result['success']) {
            $profileCreated = create_initial_profile((int) $result['user_id'], $role, $name);

            if ($profileCreated) {
                set_flash('success', 'Registrasi berhasil. Silakan login menggunakan akun Anda');
                redirect(BASE_URL . '/login.php');
            }

            set_flash('error', 'Registrasi akun berhasil, tetapi profil awal gagal dibuat. Silakan hubungi administrator');
        } else {
            set_flash('error', $result['message']);
        }
    } else {
        foreach ($errors as $error) {
            set_flash('error', $error);
        }
    }
}

function create_initial_profile(int $userId, string $role, string $name): bool
{
    try {
        $pdo = Database::getInstance()->getConnection();

        if ($role === 'company') {
            $stmt = $pdo->prepare(
                'INSERT INTO company_profiles (user_id, company_name, industry, description, address, phone) VALUES (:user_id, :company_name, :industry, :description, :address, :phone)'
            );

            return $stmt->execute([
                ':user_id' => $userId,
                ':company_name' => $name,
                ':industry' => '',
                ':description' => '',
                ':address' => '',
                ':phone' => '',
            ]);
        }

        $programId = get_default_study_program_id();

        if ($programId === null) {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO student_profiles (user_id, student_id, full_name, phone, address, program_id, semester, gpa) VALUES (:user_id, :student_id, :full_name, :phone, :address, :program_id, :semester, :gpa)'
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':student_id' => 'TMP' . $userId,
            ':full_name' => $name,
            ':phone' => '',
            ':address' => '',
            ':program_id' => $programId,
            ':semester' => 1,
            ':gpa' => 0.00,
        ]);
    } catch (PDOException $exception) {
        error_log('Create initial profile failed: ' . $exception->getMessage());

        return false;
    }
}

function get_default_study_program_id(): ?int
{
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query('SELECT id FROM study_programs WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $program = $stmt->fetch();

        return $program ? (int) $program['id'] : null;
    } catch (PDOException $exception) {
        error_log('Get default study program failed: ' . $exception->getMessage());

        return null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= sanitize(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9Oer+R4sF86dIHNDz4JxQmK2h5ZC7pERxKED7ZtGh6y9" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/custom.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(13, 110, 253, 0.92), rgba(33, 37, 41, 0.88)),
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.28), transparent 30%);
        }

        .register-card {
            max-width: 480px;
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

        .strength-bar {
            height: 8px;
            border-radius: 999px;
            transition: width 0.2s ease, background-color 0.2s ease;
        }
    </style>
</head>
<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center px-3 py-5">
        <div class="w-100" style="max-width: 480px;">
            <div class="text-center text-white mb-4">
                <div class="brand-icon mb-3">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <h1 class="h3 fw-bold mb-1"><?= sanitize(APP_NAME); ?></h1>
                <p class="mb-0 text-white-50">Buat akun untuk mulai menggunakan CariinTern</p>
            </div>

            <?= display_flash(); ?>

            <div class="card register-card">
                <div class="card-body p-4">
                    <h2 class="h4 fw-bold mb-1">Daftar Akun</h2>
                    <p class="text-muted mb-4">Pilih tipe akun sesuai kebutuhan Anda.</p>

                    <form id="registerForm" method="POST" action="register.php" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Lengkap</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="name" name="name" value="<?= $old['name']; ?>" autocomplete="name" placeholder="Nama lengkap">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?= $old['email']; ?>" autocomplete="email" placeholder="nama@email.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Daftar Sebagai</label>
                            <select class="form-select" id="role" name="role">
                                <option value="student" <?= $old['role'] === 'student' ? 'selected' : ''; ?>>Saya Mahasiswa</option>
                                <option value="company" <?= $old['role'] === 'company' ? 'selected' : ''; ?>>Saya Perusahaan</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" placeholder="Minimal 8 karakter">
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div id="passwordStrengthBar" class="strength-bar bg-secondary" style="width: 0%;"></div>
                            </div>
                            <div id="passwordStrengthText" class="small text-muted mt-1">Kekuatan password</div>
                        </div>

                        <div class="mb-4">
                            <label for="passwordConfirmation" class="form-label">Konfirmasi Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" class="form-control" id="passwordConfirmation" name="password_confirmation" autocomplete="new-password" placeholder="Ulangi password">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-person-plus me-1"></i>
                            Daftar
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="text-muted">Sudah punya akun?</span>
                        <a href="login.php" class="fw-semibold text-decoration-none">Kembali ke login</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');

        passwordInput.addEventListener('input', () => {
            const value = passwordInput.value;
            let score = 0;

            if (value.length >= 8) score += 1;
            if (/[A-Za-z]/.test(value) && /\d/.test(value)) score += 1;
            if (/[^A-Za-z0-9]/.test(value)) score += 1;

            const states = [
                { width: '0%', className: 'strength-bar bg-secondary', text: 'Kekuatan password' },
                { width: '33%', className: 'strength-bar bg-danger', text: 'Weak' },
                { width: '66%', className: 'strength-bar bg-warning', text: 'Medium' },
                { width: '100%', className: 'strength-bar bg-success', text: 'Strong' },
            ];
            const state = states[score];

            strengthBar.style.width = state.width;
            strengthBar.className = state.className;
            strengthText.textContent = state.text;
        });
    </script>
</body>
</html>
