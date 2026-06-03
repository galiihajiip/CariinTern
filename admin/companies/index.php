<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Verifikasi Perusahaan';
$allowedTabs = ['all', 'pending', 'verified'];
$activeTab = trim((string) ($_GET['status'] ?? 'all'));
$companies = [];
$pendingCount = 0;
$verifiedCount = 0;
$allCount = 0;

if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/companies/index.php?status=' . urlencode($activeTab));
    }

    $validator = (new Validator($_POST))
        ->required('action', 'Aksi')
        ->in_array('action', ['verify', 'reject'], 'Aksi')
        ->required('company_id', 'Perusahaan')
        ->numeric('company_id', 'Perusahaan')
        ->max_length('reason', 500, 'Catatan penolakan');
    $validationErrors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];
    $action = (string) ($_POST['action'] ?? '');
    $companyId = (int) ($_POST['company_id'] ?? 0);
    $adminId = (int) ($_SESSION['user_id'] ?? 0);

    if ($validationErrors !== [] || $companyId <= 0 || !in_array($action, ['verify', 'reject'], true)) {
        set_flash('error', $validationErrors[0] ?? 'Aksi perusahaan tidak valid');
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $companyStmt = $pdo->prepare('SELECT company_name FROM company_profiles WHERE id = :id LIMIT 1');
            $companyStmt->execute([':id' => $companyId]);
            $company = $companyStmt->fetch();

            if (!$company) {
                set_flash('error', 'Perusahaan tidak ditemukan');
            } elseif ($action === 'verify') {
                $stmt = $pdo->prepare(
                    'UPDATE company_profiles
                     SET is_verified = 1, verified_at = NOW(), verified_by = :verified_by
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':verified_by' => $adminId,
                    ':id' => $companyId,
                ]);

                log_activity($adminId, 'verify_company', 'Admin memverifikasi perusahaan: ' . $company['company_name']);
                set_flash('success', 'Perusahaan berhasil diverifikasi');
            } else {
                $reason = trim((string) ($_POST['reason'] ?? ''));
                $description = 'Admin menolak verifikasi perusahaan: ' . $company['company_name'];

                if ($reason !== '') {
                    $description .= ' | Catatan: ' . $reason;
                }

                $stmt = $pdo->prepare(
                    'UPDATE company_profiles
                     SET is_verified = 0, verified_at = NULL, verified_by = NULL
                     WHERE id = :id'
                );
                $stmt->execute([':id' => $companyId]);

                log_activity($adminId, 'reject_company', $description);
                set_flash('success', 'Verifikasi perusahaan ditolak');
            }
        } catch (PDOException $exception) {
            error_log('Company verification action failed: ' . $exception->getMessage());
            set_flash('error', 'Aksi verifikasi gagal diproses. Silakan coba lagi nanti');
        }
    }

    redirect(BASE_URL . '/admin/companies/index.php?status=' . urlencode($activeTab));
}

try {
    $pdo = Database::getInstance()->getConnection();
    $allCount = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles')->fetchColumn();
    $pendingCount = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles WHERE is_verified = 0')->fetchColumn();
    $verifiedCount = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles WHERE is_verified = 1')->fetchColumn();

    $whereSql = '';
    if ($activeTab === 'pending') {
        $whereSql = ' WHERE company_profiles.is_verified = 0';
    } elseif ($activeTab === 'verified') {
        $whereSql = ' WHERE company_profiles.is_verified = 1';
    }

    $stmt = $pdo->query(
        'SELECT company_profiles.id, company_profiles.company_name, company_profiles.industry,
                company_profiles.description, company_profiles.website, company_profiles.is_verified,
                company_profiles.created_at, users.email
         FROM company_profiles
         INNER JOIN users ON users.id = company_profiles.user_id' . $whereSql . '
         ORDER BY company_profiles.created_at DESC, company_profiles.id DESC'
    );
    $companies = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load companies verification failed: ' . $exception->getMessage());
    set_flash('error', 'Data perusahaan gagal dimuat. Silakan coba lagi nanti');
}

if (!function_exists('admin_companies_status_badge')) {
    function admin_companies_status_badge(int $isVerified): string
    {
        if ($isVerified === 1) {
            return '<span class="badge bg-success">Terverifikasi</span>';
        }

        return '<span class="badge bg-warning text-dark">Menunggu</span>';
    }
}

if (!function_exists('admin_companies_tab_url')) {
    function admin_companies_tab_url(string $status): string
    {
        return BASE_URL . '/admin/companies/index.php?status=' . urlencode($status);
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Verifikasi Perusahaan</h1>
        <p class="text-muted mb-0">Tinjau dan kelola status verifikasi akun perusahaan.</p>
    </div>
    <div class="text-md-end">
        <div class="small text-muted">Total Perusahaan</div>
        <div class="h4 fw-bold mb-0"><?= number_format($allCount); ?></div>
    </div>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'all' ? 'active' : ''; ?>" href="<?= admin_companies_tab_url('all'); ?>">
                    Semua
                    <span class="badge text-bg-secondary ms-1"><?= number_format($allCount); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'pending' ? 'active' : ''; ?>" href="<?= admin_companies_tab_url('pending'); ?>">
                    Menunggu Verifikasi
                    <span class="badge text-bg-warning ms-1"><?= number_format($pendingCount); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'verified' ? 'active' : ''; ?>" href="<?= admin_companies_tab_url('verified'); ?>">
                    Terverifikasi
                    <span class="badge text-bg-success ms-1"><?= number_format($verifiedCount); ?></span>
                </a>
            </li>
        </ul>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 72px;">No</th>
                        <th>Nama Perusahaan</th>
                        <th>Email</th>
                        <th>Industri</th>
                        <th>Tanggal Daftar</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($companies === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Tidak ada data perusahaan.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($companies as $index => $company): ?>
                        <?php
                        $companyId = (int) $company['id'];
                        $isVerified = (int) $company['is_verified'];
                        $verifyModalId = 'verifyCompanyModal' . $companyId;
                        $rejectModalId = 'rejectCompanyModal' . $companyId;
                        ?>
                        <tr>
                            <td><?= number_format($index + 1); ?></td>
                            <td>
                                <div class="fw-semibold"><?= sanitize((string) $company['company_name']); ?></div>
                                <?php if (!empty($company['website'])): ?>
                                    <a class="small text-decoration-none" href="<?= sanitize((string) $company['website']); ?>" target="_blank" rel="noopener">
                                        <?= sanitize((string) $company['website']); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize((string) $company['email']); ?></td>
                            <td><?= sanitize((string) $company['industry']); ?></td>
                            <td><?= sanitize(format_date((string) $company['created_at'], 'd M Y')); ?></td>
                            <td><?= admin_companies_status_badge($isVerified); ?></td>
                            <td class="text-end">
                                <?php if ($isVerified === 0): ?>
                                    <div class="d-inline-flex gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#<?= $verifyModalId; ?>"
                                        >
                                            <i class="bi bi-check-circle me-1"></i>
                                            Verifikasi
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#<?= $rejectModalId; ?>"
                                        >
                                            <i class="bi bi-x-circle me-1"></i>
                                            Tolak
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Tidak ada aksi</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($companies as $company): ?>
    <?php
    if ((int) $company['is_verified'] === 1) {
        continue;
    }

    $companyId = (int) $company['id'];
    $verifyModalId = 'verifyCompanyModal' . $companyId;
    $rejectModalId = 'rejectCompanyModal' . $companyId;
    ?>

    <div class="modal fade" id="<?= $verifyModalId; ?>" tabindex="-1" aria-labelledby="<?= $verifyModalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $verifyModalId; ?>Label">Konfirmasi Verifikasi Perusahaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Nama</dt>
                        <dd class="col-sm-9"><?= sanitize((string) $company['company_name']); ?></dd>

                        <dt class="col-sm-3">Industri</dt>
                        <dd class="col-sm-9"><?= sanitize((string) $company['industry']); ?></dd>

                        <dt class="col-sm-3">Deskripsi</dt>
                        <dd class="col-sm-9"><?= nl2br(sanitize((string) $company['description'])); ?></dd>

                        <dt class="col-sm-3">Website</dt>
                        <dd class="col-sm-9">
                            <?= $company['website'] ? sanitize((string) $company['website']) : '<span class="text-muted">-</span>'; ?>
                        </dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="index.php?status=<?= urlencode($activeTab); ?>">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="verify">
                        <input type="hidden" name="company_id" value="<?= $companyId; ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Verifikasi
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="<?= $rejectModalId; ?>" tabindex="-1" aria-labelledby="<?= $rejectModalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $rejectModalId; ?>Label">Konfirmasi Tolak Verifikasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="index.php?status=<?= urlencode($activeTab); ?>">
                    <?= csrf_field(); ?>
                    <div class="modal-body">
                        <p class="mb-2">Tolak verifikasi untuk perusahaan:</p>
                        <div class="fw-semibold"><?= sanitize((string) $company['company_name']); ?></div>
                        <div class="text-muted small mb-3"><?= sanitize((string) $company['email']); ?></div>

                        <label for="rejectReason<?= $companyId; ?>" class="form-label">Catatan Penolakan</label>
                        <textarea class="form-control" id="rejectReason<?= $companyId; ?>" name="reason" rows="3" placeholder="Contoh: dokumen belum lengkap"></textarea>
                        <div class="form-text">Catatan akan disimpan di activity log.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="company_id" value="<?= $companyId; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-1"></i>
                            Tolak
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
