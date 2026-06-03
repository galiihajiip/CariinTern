<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Edit Kategori Magang';
$categoryId = (int) ($_GET['id'] ?? 0);
$old = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'is_active' => '1',
];

if ($categoryId <= 0) {
    set_flash('error', 'Kategori tidak valid');
    redirect(BASE_URL . '/admin/categories/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT id, name, slug, description, is_active FROM internship_categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $categoryId]);
    $category = $stmt->fetch();

    if (!$category) {
        set_flash('error', 'Kategori tidak ditemukan');
        redirect(BASE_URL . '/admin/categories/index.php');
    }

    $old = [
        'name' => sanitize((string) $category['name']),
        'slug' => sanitize((string) $category['slug']),
        'description' => sanitize((string) ($category['description'] ?? '')),
        'is_active' => (string) (int) $category['is_active'],
    ];
} catch (PDOException $exception) {
    error_log('Load category edit failed: ' . $exception->getMessage());
    set_flash('error', 'Data kategori gagal dimuat');
    redirect(BASE_URL . '/admin/categories/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $slug = generate_slug($slugInput !== '' ? $slugInput : $name);
    $errors = [];

    $old = [
        'name' => sanitize($name),
        'slug' => sanitize($slugInput !== '' ? $slugInput : $slug),
        'description' => sanitize($description),
        'is_active' => (string) $isActive,
    ];

    if ($name === '') {
        $errors[] = 'Nama kategori wajib diisi';
    }

    if ($slug === '') {
        $errors[] = 'Slug kategori wajib diisi';
    }

    if ($errors === []) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM internship_categories WHERE slug = :slug AND id <> :id');
            $stmt->execute([
                ':slug' => $slug,
                ':id' => $categoryId,
            ]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Slug sudah digunakan';
            } else {
                $updateStmt = $pdo->prepare(
                    'UPDATE internship_categories
                     SET name = :name, slug = :slug, description = :description, is_active = :is_active
                     WHERE id = :id'
                );
                $updateStmt->execute([
                    ':name' => $name,
                    ':slug' => $slug,
                    ':description' => $description !== '' ? $description : null,
                    ':is_active' => $isActive,
                    ':id' => $categoryId,
                ]);

                log_activity((int) ($_SESSION['user_id'] ?? 0), 'update_category', 'Admin mengubah kategori ID ' . $categoryId);
                set_flash('success', 'Kategori berhasil diperbarui');
                redirect(BASE_URL . '/admin/categories/index.php');
            }
        } catch (PDOException $exception) {
            error_log('Update category failed: ' . $exception->getMessage());
            $errors[] = 'Kategori gagal diperbarui. Silakan coba lagi nanti';
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
        <h1 class="h3 fw-bold mb-1">Edit Kategori Magang</h1>
        <p class="text-muted mb-0">Perbarui kategori dan slug lowongan magang.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/categories/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form id="categoryForm" class="needs-validation" method="POST" action="edit.php?id=<?= $categoryId; ?>" novalidate>
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
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const form = document.getElementById('categoryForm');
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const initialSlug = slugInput.value;
    let slugEditedManually = false;

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
        slugEditedManually = slugInput.value !== initialSlug;
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
