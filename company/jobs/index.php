<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('company');

$page_title = 'Lowongan Saya';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$allowedStatuses = ['open', 'draft', 'closed'];
$selectedStatus = trim((string) ($_GET['status'] ?? ''));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($currentPage - 1) * $perPage;
$companyProfile = null;
$companyId = 0;
$jobs = [];
$totalJobs = 0;
$totalPages = 1;
$statusCounts = [
    'all' => 0,
    'open' => 0,
    'draft' => 0,
    'closed' => 0,
];

if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

function company_jobs_redirect_url(string $status, int $page): string
{
    $params = [];

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($page > 1) {
        $params['page'] = $page;
    }

    $url = BASE_URL . '/company/jobs/index.php';

    return $params !== [] ? $url . '?' . http_build_query($params) : $url;
}

function company_jobs_status_badge(string $status): string
{
    $classes = [
        'open' => 'success',
        'draft' => 'secondary',
        'closed' => 'dark',
    ];
    $labels = [
        'open' => 'Buka',
        'draft' => 'Draft',
        'closed' => 'Ditutup',
    ];

    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . '">' . sanitize($labels[$status] ?? ucfirst($status)) . '</span>';
}

function company_jobs_deadline_badge(string $deadline): string
{
    $today = new DateTimeImmutable('today');
    $deadlineDate = DateTimeImmutable::createFromFormat('Y-m-d', $deadline) ?: new DateTimeImmutable($deadline);
    $daysLeft = (int) $today->diff($deadlineDate)->format('%r%a');

    if ($daysLeft < 0) {
        return '<span class="badge bg-danger">Lewat ' . abs($daysLeft) . ' hari</span>';
    }

    if ($daysLeft < 7) {
        return '<span class="badge bg-warning text-dark">' . $daysLeft . ' hari lagi</span>';
    }

    return '<span class="badge bg-success">' . $daysLeft . ' hari lagi</span>';
}

try {
    $pdo = Database::getInstance()->getConnection();
    $profileStmt = $pdo->prepare(
        'SELECT id, company_name, is_verified
         FROM company_profiles
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $companyProfile = $profileStmt->fetch();

    if ($companyProfile) {
        $companyId = (int) $companyProfile['id'];
        $_SESSION['company_verified'] = (int) $companyProfile['is_verified'] === 1;
    }
} catch (PDOException $exception) {
    error_log('Load company for jobs failed: ' . $exception->getMessage());
    set_flash('error', 'Profil perusahaan gagal dimuat');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(company_jobs_redirect_url($selectedStatus, $currentPage));
    }

    $action = (string) ($_POST['action'] ?? '');
    $jobId = (int) ($_POST['job_id'] ?? 0);

    if ($companyId <= 0) {
        set_flash('error', 'Lengkapi profil perusahaan terlebih dahulu');
    } elseif ($jobId <= 0) {
        set_flash('error', 'Lowongan tidak valid');
    } elseif ($action === 'toggle_status') {
        try {
            $stmt = $pdo->prepare(
                'UPDATE job_listings
                 SET status = CASE WHEN status = \'open\' THEN \'closed\' ELSE \'open\' END
                 WHERE id = :job_id AND company_id = :company_id'
            );
            $stmt->execute([
                ':job_id' => $jobId,
                ':company_id' => $companyId,
            ]);

            if ($stmt->rowCount() > 0) {
                log_activity($userId, 'toggle_job_status', 'Perusahaan mengubah status lowongan ID ' . $jobId);
                set_flash('success', 'Status lowongan berhasil diperbarui');
            } else {
                set_flash('error', 'Lowongan tidak ditemukan atau bukan milik perusahaan Anda');
            }
        } catch (PDOException $exception) {
            error_log('Toggle company job status failed: ' . $exception->getMessage());
            set_flash('error', 'Status lowongan gagal diperbarui');
        }
    }

    redirect(company_jobs_redirect_url($selectedStatus, $currentPage));
}

try {
    if ($companyId > 0) {
        $countByStatusStmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS total
             FROM job_listings
             WHERE company_id = :company_id
             GROUP BY status'
        );
        $countByStatusStmt->execute([':company_id' => $companyId]);

        foreach ($countByStatusStmt->fetchAll() as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];

            if (isset($statusCounts[$status])) {
                $statusCounts[$status] = $count;
                $statusCounts['all'] += $count;
            }
        }

        $conditions = ['job_listings.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        if ($selectedStatus !== '') {
            $conditions[] = 'job_listings.status = :status';
            $params[':status'] = $selectedStatus;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $conditions);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM job_listings' . $whereSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalJobs = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalJobs / $perPage));

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
            $offset = ($currentPage - 1) * $perPage;
        }

        $stmt = $pdo->prepare(
            'SELECT job_listings.id, job_listings.title, job_listings.quota, job_listings.deadline,
                    job_listings.status, job_listings.created_at,
                    internship_categories.name AS category_name,
                    COUNT(applications.id) AS applicants_count
             FROM job_listings
             INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
             LEFT JOIN applications ON applications.job_id = job_listings.id
             ' . $whereSql . '
             GROUP BY job_listings.id, job_listings.title, job_listings.quota, job_listings.deadline,
                      job_listings.status, job_listings.created_at, internship_categories.name
             ORDER BY job_listings.created_at DESC, job_listings.id DESC
             LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('Load company jobs failed: ' . $exception->getMessage());
    set_flash('error', 'Data lowongan gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_company.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Lowongan Saya</h1>
        <p class="text-muted mb-0">
            <?= $companyProfile ? sanitize((string) $companyProfile['company_name']) : 'Lengkapi profil perusahaan untuk membuat lowongan.'; ?>
        </p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/company/jobs/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Buat Lowongan Baru
    </a>
</div>

<?= display_flash(); ?>

<?php if ($companyId <= 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Profil perusahaan belum tersedia. Silakan lengkapi profil terlebih dahulu.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === '' ? 'active' : ''; ?>" href="<?= rtrim(BASE_URL, '/'); ?>/company/jobs/index.php">
                    Semua
                    <span class="badge text-bg-secondary ms-1"><?= number_format($statusCounts['all']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'open' ? 'active' : ''; ?>" href="<?= company_jobs_redirect_url('open', 1); ?>">
                    Buka
                    <span class="badge text-bg-success ms-1"><?= number_format($statusCounts['open']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'draft' ? 'active' : ''; ?>" href="<?= company_jobs_redirect_url('draft', 1); ?>">
                    Draft
                    <span class="badge text-bg-secondary ms-1"><?= number_format($statusCounts['draft']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'closed' ? 'active' : ''; ?>" href="<?= company_jobs_redirect_url('closed', 1); ?>">
                    Ditutup
                    <span class="badge text-bg-dark ms-1"><?= number_format($statusCounts['closed']); ?></span>
                </a>
            </li>
        </ul>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Daftar Lowongan</h2>
                <p class="text-muted small mb-0">Menampilkan <?= number_format(count($jobs)); ?> dari <?= number_format($totalJobs); ?> lowongan.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Kategori</th>
                        <th>Kuota</th>
                        <th>Pelamar/Kuota</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($jobs === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada lowongan yang cocok.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $jobId = (int) $job['id'];
                        $applicantsCount = (int) $job['applicants_count'];
                        $quota = (int) $job['quota'];
                        $status = (string) $job['status'];
                        $toggleModalId = 'toggleJobModal' . $jobId;
                        $deleteModalId = 'deleteJobModal' . $jobId;
                        $toggleLabel = $status === 'open' ? 'Tutup' : 'Buka';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= sanitize((string) $job['title']); ?></div>
                                <div class="small text-muted">Dibuat <?= format_date((string) $job['created_at']); ?></div>
                            </td>
                            <td><?= sanitize((string) $job['category_name']); ?></td>
                            <td><?= number_format($quota); ?></td>
                            <td>
                                <span class="badge <?= $applicantsCount >= $quota ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?= number_format($applicantsCount); ?>/<?= number_format($quota); ?>
                                </span>
                            </td>
                            <td>
                                <div><?= format_date((string) $job['deadline']); ?></div>
                                <?= company_jobs_deadline_badge((string) $job['deadline']); ?>
                            </td>
                            <td><?= company_jobs_status_badge($status); ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/company/jobs/edit.php?id=<?= $jobId; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/company/applicants/index.php?job_id=<?= $jobId; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-person-lines-fill"></i>
                                        Pelamar
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#<?= $toggleModalId; ?>">
                                        <i class="bi bi-toggle-on"></i>
                                        <?= $toggleLabel; ?>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#<?= $deleteModalId; ?>">
                                        <i class="bi bi-trash"></i>
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pagination lowongan">
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= company_jobs_redirect_url($selectedStatus, max(1, $currentPage - 1)); ?>">Sebelumnya</a>
                    </li>
                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                        <li class="page-item <?= $page === $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="<?= company_jobs_redirect_url($selectedStatus, $page); ?>"><?= $page; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= company_jobs_redirect_url($selectedStatus, min($totalPages, $currentPage + 1)); ?>">Berikutnya</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($jobs as $job): ?>
    <?php
    $jobId = (int) $job['id'];
    $status = (string) $job['status'];
    $toggleModalId = 'toggleJobModal' . $jobId;
    $deleteModalId = 'deleteJobModal' . $jobId;
    $toggleLabel = $status === 'open' ? 'Tutup' : 'Buka';
    $targetStatusLabel = $status === 'open' ? 'Ditutup' : 'Buka';
    ?>

    <div class="modal fade" id="<?= $toggleModalId; ?>" tabindex="-1" aria-labelledby="<?= $toggleModalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $toggleModalId; ?>Label">Konfirmasi Status Lowongan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Ubah status lowongan ini menjadi <strong><?= sanitize($targetStatusLabel); ?></strong>?</p>
                    <div class="fw-semibold"><?= sanitize((string) $job['title']); ?></div>
                    <div class="text-muted small">Status saat ini: <?= company_jobs_status_badge($status); ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="<?= company_jobs_redirect_url($selectedStatus, $currentPage); ?>">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="job_id" value="<?= $jobId; ?>">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-toggle-on me-1"></i>
                            <?= sanitize($toggleLabel); ?> Lowongan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="<?= $deleteModalId; ?>" tabindex="-1" aria-labelledby="<?= $deleteModalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $deleteModalId; ?>Label">Konfirmasi Hapus Lowongan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Apakah Anda yakin ingin menghapus lowongan ini?</p>
                    <div class="fw-semibold"><?= sanitize((string) $job['title']); ?></div>
                    <div class="text-muted small"><?= number_format((int) $job['applicants_count']); ?> pelamar terkait juga akan terdampak oleh penghapusan ini.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="<?= rtrim(BASE_URL, '/'); ?>/company/jobs/delete.php">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="job_id" value="<?= $jobId; ?>">
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
