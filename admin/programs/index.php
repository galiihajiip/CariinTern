<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Program Studi';
$programs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $programId = (int) ($_POST['program_id'] ?? 0);

    if ($programId <= 0) {
        set_flash('error', 'Program studi tidak valid');
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('UPDATE study_programs SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $stmt->execute([':id' => $programId]);

            if ($stmt->rowCount() > 0) {
                log_activity((int) ($_SESSION['user_id'] ?? 0), 'toggle_program_status', 'Admin mengubah status program studi ID ' . $programId);
                set_flash('success', 'Status program studi berhasil diperbarui');
            } else {
                set_flash('error', 'Program studi tidak ditemukan');
            }
        } catch (PDOException $exception) {
            error_log('Toggle program status failed: ' . $exception->getMessage());
            set_flash('error', 'Status program studi gagal diperbarui');
        }
    }

    redirect(BASE_URL . '/admin/programs/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query(
        'SELECT study_programs.id, study_programs.name, study_programs.code,
                study_programs.faculty, study_programs.is_active,
                (SELECT COUNT(*) FROM student_profiles WHERE student_profiles.program_id = study_programs.id) AS students_count
         FROM study_programs
         ORDER BY study_programs.faculty ASC, study_programs.name ASC'
    );
    $programs = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load study programs failed: ' . $exception->getMessage());
    set_flash('error', 'Data program studi gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Program Studi</h1>
        <p class="text-muted mb-0">Kelola program studi dan fakultas untuk profil mahasiswa.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/programs/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Tambah Program
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
                        <th>Kode</th>
                        <th>Fakultas</th>
                        <th>Status</th>
                        <th>Jumlah Mahasiswa</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($programs === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada program studi.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($programs as $index => $program): ?>
                        <?php
                        $programId = (int) $program['id'];
                        $studentsCount = (int) $program['students_count'];
                        $deleteModalId = 'deleteProgramModal' . $programId;
                        ?>
                        <tr>
                            <td><?= number_format($index + 1); ?></td>
                            <td class="fw-semibold"><?= sanitize((string) $program['name']); ?></td>
                            <td><code><?= sanitize((string) $program['code']); ?></code></td>
                            <td><?= sanitize((string) $program['faculty']); ?></td>
                            <td>
                                <?php if ((int) $program['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($studentsCount); ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/programs/edit.php?id=<?= $programId; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>

                                    <form method="POST" action="index.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="program_id" value="<?= $programId; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-toggle-on"></i>
                                            Toggle Status
                                        </button>
                                    </form>

                                    <?php if ($studentsCount === 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#<?= $deleteModalId; ?>">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Program masih memiliki mahasiswa terkait">
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

<?php foreach ($programs as $program): ?>
    <?php
    $programId = (int) $program['id'];

    if ((int) $program['students_count'] > 0) {
        continue;
    }

    $deleteModalId = 'deleteProgramModal' . $programId;
    ?>
    <div class="modal fade" id="<?= $deleteModalId; ?>" tabindex="-1" aria-labelledby="<?= $deleteModalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $deleteModalId; ?>Label">Konfirmasi Hapus Program Studi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Apakah Anda yakin ingin menghapus program studi ini?</p>
                    <div class="fw-semibold"><?= sanitize((string) $program['name']); ?></div>
                    <div class="text-muted small"><?= sanitize((string) $program['code']); ?> - <?= sanitize((string) $program['faculty']); ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="<?= rtrim(BASE_URL, '/'); ?>/admin/programs/delete.php">
                        <input type="hidden" name="program_id" value="<?= $programId; ?>">
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
