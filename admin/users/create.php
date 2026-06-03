<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Tambah User';
$allowedRoles = ['admin', 'company', 'student'];
$old = [
    'name' => '',
    'email' => '',
    'role' => 'student',
    'is_active' => '1',
];

if (!function_exists('admin_create_default_program_id')) {
    function admin_create_default_program_id(PDO $pdo): ?int
    {
        $stmt = $pdo->query('SELECT id FROM study_programs WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $program = $stmt->fetch();

        return $program ? (int) $program['id'] : null;
    }
}

if (!function_exists('admin_create_profile')) {
    function admin_create_profile(PDO $pdo, int $userId, string $role, string $name): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if ($role === 'company') {
            $stmt = $pdo->prepare(
                'INSERT INTO company_profiles (user_id, company_name, industry, description, address, phone)
                 VALUES (:user_id, :company_name, :industry, :description, :address, :phone)'
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

        $programId = admin_create_default_program_id($pdo);

        if ($programId === null) {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO student_profiles (user_id, student_id, full_name, phone, address, program_id, semester, gpa)
             VALUES (:user_id, :student_id, :full_name, :phone, :address, :program_id, :semester, :gpa)'
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
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/users/create.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $role = trim((string) ($_POST['role'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $errors = [];

    $old = [
        'name' => sanitize($name),
        'email' => sanitize($email),
        'role' => in_array($role, $allowedRoles, true) ? $role : 'student',
        'is_active' => (string) $isActive,
    ];

    if ($name === '') {
        $errors[] = 'Nama lengkap wajib diisi';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Konfirmasi password tidak sama';
    }

    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Role tidak valid';
    }

    if ($errors === []) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Email sudah digunakan';
            } else {
                $pdo->beginTransaction();

                $insertStmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, is_active)
                     VALUES (:name, :email, :password, :role, :is_active)'
                );
                $insertStmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_BCRYPT),
                    ':role' => $role,
                    ':is_active' => $isActive,
                ]);

                $userId = (int) $pdo->lastInsertId();

                if (!admin_create_profile($pdo, $userId, $role, $name)) {
                    throw new RuntimeException('Initial profile could not be created');
                }

                $pdo->commit();
                log_activity((int) ($_SESSION['user_id'] ?? 0), 'create_user', 'Admin menambahkan user ID ' . $userId);
                set_flash('success', 'User berhasil ditambahkan');
                redirect(BASE_URL . '/admin/users/index.php');
            }
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Create user failed: ' . $exception->getMessage());
            $errors[] = 'User gagal ditambahkan. Silakan coba lagi nanti';
        }
    }

    foreach ($errors as $error) {
        set_flash('error', $error);
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Tambah User</h1>
        <p class="text-muted mb-0">Buat akun baru untuk admin, perusahaan, atau mahasiswa.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/users/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form id="userForm" class="needs-validation" method="POST" action="create.php" novalidate>
            <?= csrf_field(); ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= $old['name']; ?>" required>
                    <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= $old['email']; ?>" required>
                    <div class="invalid-feedback">Masukkan email yang valid.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                    <div class="invalid-feedback">Password minimal 8 karakter.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="passwordConfirmation" class="form-label">Konfirmasi Password</label>
                    <input type="password" class="form-control" id="passwordConfirmation" name="password_confirmation" minlength="8" required>
                    <div class="invalid-feedback">Konfirmasi password harus sama.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="company" <?= $old['role'] === 'company' ? 'selected' : ''; ?>>Perusahaan</option>
                        <option value="student" <?= $old['role'] === 'student' ? 'selected' : ''; ?>>Mahasiswa</option>
                    </select>
                    <div class="invalid-feedback">Pilih role user.</div>
                </div>

                <div class="col-12 col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1" <?= $old['is_active'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">Status Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/users/index.php" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>
                    Simpan User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const form = document.getElementById('userForm');
    const password = document.getElementById('password');
    const passwordConfirmation = document.getElementById('passwordConfirmation');

    function validatePasswordMatch() {
        const matches = password.value === passwordConfirmation.value;
        passwordConfirmation.setCustomValidity(matches ? '' : 'Password tidak sama');
        passwordConfirmation.classList.toggle('is-invalid', !matches && passwordConfirmation.value !== '');
    }

    password.addEventListener('input', validatePasswordMatch);
    passwordConfirmation.addEventListener('input', validatePasswordMatch);

    form.addEventListener('submit', event => {
        validatePasswordMatch();

        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
