<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Tambah Kategori Magang';
$old = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'is_active' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/categories/create.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $slug = generate_slug($slugInput !== '' ? $slugInput : $name);
    $validationData = $_POST;
    $validationData['slug'] = $slug;
    $validator = (new Validator($validationData))
        ->required('name', 'Nama kategori')
        ->required('slug', 'Slug kategori')
        ->max_length('name', 100, 'Nama kategori')
        ->max_length('slug', 100, 'Slug kategori')
        ->unique('slug', 'internship_categories', 'slug');
    $errors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];

    $old = [
        'name' => sanitize($name),
        'slug' => sanitize($slugInput !== '' ? $slugInput : $slug),
        'description' => sanitize($description),
        'is_active' => (string) $isActive,
    ];

    if ($errors === []) {
        try {
            $pdo = Database::getInstance()->getConnection();
            $insertStmt = $pdo->prepare(
                'INSERT INTO internship_categories (name, slug, description, is_active)
                 VALUES (:name, :slug, :description, :is_active)'
            );
            $insertStmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description !== '' ? $description : null,
                ':is_active' => $isActive,
            ]);

            log_activity((int) ($_SESSION['user_id'] ?? 0), 'create_category', 'Admin menambahkan kategori: ' . $name);
            set_flash('success', 'Kategori berhasil ditambahkan');
            redirect(BASE_URL . '/admin/categories/index.php');
        } catch (PDOException $exception) {
            error_log('Create category failed: ' . $exception->getMessage());
            $errors[] = 'Kategori gagal ditambahkan. Silakan coba lagi nanti';
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
        <h1 class="h3 fw-bold mb-1">Tambah Kategori Magang</h1>
        <p class="text-muted mb-0">Buat kategori baru untuk mengelompokkan lowongan magang.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/categories/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form id="categoryForm" class="needs-validation" method="POST" action="create.php" novalidate>
            <?= csrf_field(); ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nama Kategori</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= $old['name']; ?>" required>
                    <div class="invalid-feedback">Nama kategori wajib diisi.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="<?= $old['slug']; ?>" required>
                    <div class="form-text">Slug otomatis dari nama, tetapi bisa diedit manual.</div>
                    <div class="invalid-feedback">Slug kategori wajib diisi.</div>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?= $old['description']; ?></textarea>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1" <?= $old['is_active'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">Status Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/categories/index.php" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>
                    Simpan Kategori
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const form = document.getElementById('categoryForm');
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    let slugEditedManually = slugInput.value.trim() !== '';

    function makeSlug(value) {
        return value
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    slugInput.addEventListener('input', () => {
        slugEditedManually = true;
        slugInput.value = makeSlug(slugInput.value);
    });

    nameInput.addEventListener('input', () => {
        if (!slugEditedManually) {
            slugInput.value = makeSlug(nameInput.value);
        }
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
