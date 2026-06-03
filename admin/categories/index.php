<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Kategori Magang';
$categories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/categories/index.php');
    }

    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($categoryId <= 0) {
        set_flash('error', 'Kategori tidak valid');
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('UPDATE internship_categories SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $stmt->execute([':id' => $categoryId]);

            if ($stmt->rowCount() > 0) {
                log_activity((int) ($_SESSION['user_id'] ?? 0), 'toggle_category_status', 'Admin mengubah status kategori ID ' . $categoryId);
                set_flash('success', 'Status kategori berhasil diperbarui');
            } else {
                set_flash('error', 'Kategori tidak ditemukan');
            }
        } catch (PDOException $exception) {
            error_log('Toggle category status failed: ' . $exception->getMessage());
            set_flash('error', 'Status kategori gagal diperbarui');
        }
    }

    redirect(BASE_URL . '/admin/categories/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query(
        'SELECT internship_categories.id, internship_categories.name, internship_categories.slug,
                internship_categories.is_active, internship_categories.created_at,
                (SELECT COUNT(*) FROM job_listings WHERE job_listings.category_id = internship_categories.id) AS jobs_count
         FROM internship_categories
         ORDER BY internship_categories.created_at DESC, internship_categories.id DESC'
    );
    $categories = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load categories failed: ' . $exception->getMessage());
    set_flash('error', 'Data kategori gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Kategori Magang</h1>
        <p class="text-muted mb-0">Kelola kategori lowongan magang yang tersedia di sistem.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/categories/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Tambah Kategori
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 72px;">No</th>
                        <th>Nama</th>
                        <th>Slug</th>
                        <th>Status Aktif</th>
                        <th>Jumlah Lowongan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories === []): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada kategori.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($categories as $index => $category): ?>
                        <?php
                        $categoryId = (int) $category['id'];
                        $jobsCount = (int) $category['jobs_count'];
                        $deleteModalId = 'deleteCategoryModal' . $categoryId;
                        ?>
                        <tr>
                            <td><?= number_format($index + 1); ?></td>
                            <td class="fw-semibold"><?= sanitize((string) $category['name']); ?></td>
                            <td><code><?= sanitize((string) $category['slug']); ?></code></td>
                            <td>
                                <?php if ((int) $category['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($jobsCount); ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/categories/edit.php?id=<?= $categoryId; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>

                                    <form method="POST" action="index.php" class="d-inline">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?= $categoryId; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-toggle-on"></i>
                                            Toggle Status
                                        </button>
                                    </form>

                                    <?php if ($jobsCount === 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#<?= $deleteModalId; ?>">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Kategori masih memiliki lowongan terkait">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($categories as $category): ?>
    <?php
    $categoryId = (int) $category['id'];

    if ((int) $category['jobs_count'] > 0) {
        continue;
    }

    $deleteModalId = 'deleteCategoryModal' . $categoryId;
    ?>
    <div class="modal fade" id="<?= $deleteModalId; ?>" tabindex="-1" aria-labelledby="<?= $deleteModalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $deleteModalId; ?>Label">Konfirmasi Hapus Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Apakah Anda yakin ingin menghapus kategori ini?</p>
                    <div class="fw-semibold"><?= sanitize((string) $category['name']); ?></div>
                    <div class="text-muted small"><?= sanitize((string) $category['slug']); ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="<?= rtrim(BASE_URL, '/'); ?>/admin/categories/delete.php">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="category_id" value="<?= $categoryId; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
