<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Tambah Program Studi';
$faculties = ['Teknik', 'Ekonomi', 'Hukum', 'FISIP', 'Kedokteran', 'Pertanian', 'Pendidikan', 'Seni dan Desain'];
$old = [
    'name' => '',
    'code' => '',
    'faculty' => 'Teknik',
    'is_active' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/programs/create.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $faculty = trim((string) ($_POST['faculty'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $errors = [];

    $old = [
        'name' => sanitize($name),
        'code' => sanitize($code),
        'faculty' => in_array($faculty, $faculties, true) ? $faculty : 'Teknik',
        'is_active' => (string) $isActive,
    ];

    if ($name === '') {
        $errors[] = 'Nama program studi wajib diisi';
    }

    if ($code === '') {
        $errors[] = 'Kode program studi wajib diisi';
    }

    if (!preg_match('/^[A-Z0-9_-]{2,20}$/', $code)) {
        $errors[] = 'Kode hanya boleh huruf besar, angka, underscore, atau dash dengan panjang 2-20 karakter';
    }

    if (!in_array($faculty, $faculties, true)) {
        $errors[] = 'Fakultas tidak valid';
    }

    if ($errors === []) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM study_programs WHERE code = :code');
            $stmt->execute([':code' => $code]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Kode program studi sudah digunakan';
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO study_programs (name, code, faculty, is_active)
                     VALUES (:name, :code, :faculty, :is_active)'
                );
                $insertStmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':faculty' => $faculty,
                    ':is_active' => $isActive,
                ]);

                log_activity((int) ($_SESSION['user_id'] ?? 0), 'create_program', 'Admin menambahkan program studi: ' . $name);
                set_flash('success', 'Program studi berhasil ditambahkan');
                redirect(BASE_URL . '/admin/programs/index.php');
            }
        } catch (PDOException $exception) {
            error_log('Create study program failed: ' . $exception->getMessage());
            $errors[] = 'Program studi gagal ditambahkan. Silakan coba lagi nanti';
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
        <h1 class="h3 fw-bold mb-1">Tambah Program Studi</h1>
        <p class="text-muted mb-0">Buat program studi baru untuk profil mahasiswa.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/programs/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form id="programForm" class="needs-validation" method="POST" action="create.php" novalidate>
            <?= csrf_field(); ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nama Program Studi</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= $old['name']; ?>" required>
                    <div class="invalid-feedback">Nama program studi wajib diisi.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="code" class="form-label">Kode</label>
                    <input type="text" class="form-control text-uppercase" id="code" name="code" value="<?= $old['code']; ?>" maxlength="20" required>
                    <div class="form-text">Kode otomatis menjadi uppercase.</div>
                    <div class="invalid-feedback">Kode wajib diisi dan maksimal 20 karakter.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="faculty" class="form-label">Fakultas</label>
                    <select class="form-select" id="faculty" name="faculty" required>
                        <?php foreach ($faculties as $faculty): ?>
                            <option value="<?= sanitize($faculty); ?>" <?= $old['faculty'] === $faculty ? 'selected' : ''; ?>>
                                <?= sanitize($faculty); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Pilih fakultas.</div>
                </div>

                <div class="col-12 col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1" <?= $old['is_active'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">Status Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/programs/index.php" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>
                    Simpan Program
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const form = document.getElementById('programForm');
    const codeInput = document.getElementById('code');

    codeInput.addEventListener('input', () => {
        codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
    });

    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
