<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_upload.php';

require_role('company');

$page_title = 'Kelola Pelamar';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$companyProfile = null;
$companyId = 0;
$jobs = [];
$applications = [];
$allowedStatuses = ['pending', 'review', 'accepted', 'rejected'];
$allowedSorts = ['newest', 'oldest'];
$selectedJobId = (int) ($_GET['job_id'] ?? 0);
$selectedStatus = trim((string) ($_GET['status'] ?? ''));
$selectedSort = trim((string) ($_GET['sort'] ?? 'newest'));

if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

if (!in_array($selectedSort, $allowedSorts, true)) {
    $selectedSort = 'newest';
}

function company_applicants_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'review' => 'Review',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function company_applicants_badge(string $status): string
{
    $classes = [
        'pending' => 'secondary',
        'review' => 'warning text-dark',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];

    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . '">' . sanitize(company_applicants_status_label($status)) . '</span>';
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
    error_log('Load company for applicants failed: ' . $exception->getMessage());
    set_flash('error', 'Profil perusahaan gagal dimuat');
}

try {
    if ($companyId > 0) {
        $jobsStmt = $pdo->prepare(
            'SELECT id, title
             FROM job_listings
             WHERE company_id = :company_id
             ORDER BY created_at DESC, id DESC'
        );
        $jobsStmt->execute([':company_id' => $companyId]);
        $jobs = $jobsStmt->fetchAll();

        $conditions = ['job_listings.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        if ($selectedJobId > 0) {
            $conditions[] = 'job_listings.id = :job_id';
            $params[':job_id'] = $selectedJobId;
        }

        if ($selectedStatus !== '') {
            $conditions[] = 'applications.status = :status';
            $params[':status'] = $selectedStatus;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $conditions);
        $orderSql = $selectedSort === 'oldest' ? 'ASC' : 'DESC';
        $stmt = $pdo->prepare(
            'SELECT applications.id AS application_id, applications.cover_letter, applications.status,
                    applications.notes, applications.created_at AS applied_at,
                    student_profiles.student_id, student_profiles.full_name, student_profiles.phone,
                    student_profiles.gpa, student_profiles.cv_file, student_profiles.transcript_file,
                    users.email AS student_email,
                    study_programs.name AS program_name,
                    job_listings.id AS job_id, job_listings.title AS job_title
             FROM applications
             INNER JOIN student_profiles ON student_profiles.id = applications.student_id
             INNER JOIN users ON users.id = student_profiles.user_id
             LEFT JOIN study_programs ON study_programs.id = student_profiles.program_id
             INNER JOIN job_listings ON job_listings.id = applications.job_id
             ' . $whereSql . '
             ORDER BY applications.created_at ' . $orderSql . ', applications.id ' . $orderSql
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $applications = $stmt->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('Load company applicants failed: ' . $exception->getMessage());
    set_flash('error', 'Data pelamar gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_company.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Kelola Pelamar</h1>
        <p class="text-muted mb-0">
            <?= $companyProfile ? sanitize((string) $companyProfile['company_name']) : 'Kelola status pelamar untuk lowongan perusahaan Anda.'; ?>
        </p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/company/jobs/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-briefcase me-1"></i>
        Lowongan Saya
    </a>
</div>

<?= display_flash(); ?>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="applicantToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="applicantToastBody">Status berhasil diperbarui.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET" action="index.php">
            <div class="col-12 col-lg-4">
                <label for="jobId" class="form-label">Lowongan</label>
                <select class="form-select" id="jobId" name="job_id">
                    <option value="">Semua Lowongan</option>
                    <?php foreach ($jobs as $job): ?>
                        <?php $jobId = (int) $job['id']; ?>
                        <option value="<?= $jobId; ?>" <?= $selectedJobId === $jobId ? 'selected' : ''; ?>>
                            <?= sanitize((string) $job['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua Status</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= sanitize($status); ?>" <?= $selectedStatus === $status ? 'selected' : ''; ?>>
                            <?= sanitize(company_applicants_status_label($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label for="sort" class="form-label">Urutkan</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="newest" <?= $selectedSort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="oldest" <?= $selectedSort === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-3 d-grid d-md-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter me-1"></i>
                    Filter
                </button>
                <a href="<?= rtrim(BASE_URL, '/'); ?>/company/applicants/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">Daftar Pelamar</h2>
        <p class="text-muted mb-0">Menampilkan <?= number_format(count($applications)); ?> pelamar.</p>
    </div>
</div>

<?php if ($applications === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-person-lines-fill fs-1 d-block mb-2"></i>
            Belum ada pelamar yang cocok dengan filter.
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($applications as $application): ?>
        <?php
        $applicationId = (int) $application['application_id'];
        $cvUrl = !empty($application['cv_file']) ? get_file_url((string) $application['cv_file'], 'cv') : '';
        $transcriptUrl = !empty($application['transcript_file']) ? get_file_url((string) $application['transcript_file'], 'transcripts') : '';
        $currentStatus = (string) $application['status'];
        ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm applicant-card" data-application-id="<?= $applicationId; ?>">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-12 col-xl-7">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                                <div>
                                    <h3 class="h5 fw-bold mb-1"><?= sanitize((string) $application['full_name']); ?></h3>
                                    <div class="text-muted small">
                                        <?= sanitize((string) $application['student_id']); ?> ·
                                        <?= sanitize((string) ($application['program_name'] ?? 'Program tidak tersedia')); ?> ·
                                        GPA <?= number_format((float) $application['gpa'], 2); ?>
                                    </div>
                                </div>
                                <div class="status-badge" id="statusBadge<?= $applicationId; ?>">
                                    <?= company_applicants_badge($currentStatus); ?>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Lowongan Dilamar</div>
                                    <div class="fw-semibold"><?= sanitize((string) $application['job_title']); ?></div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Tanggal Melamar</div>
                                    <div><?= format_date((string) $application['applied_at'], 'd M Y H:i'); ?></div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Email</div>
                                    <div><?= sanitize((string) $application['student_email']); ?></div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Telepon</div>
                                    <div><?= sanitize((string) $application['phone']); ?></div>
                                </div>
                            </div>

                            <?php if (!empty($application['cover_letter'])): ?>
                                <div class="border rounded-3 bg-light p-3">
                                    <div class="small text-muted mb-1">Cover Letter</div>
                                    <div><?= nl2br(sanitize((string) $application['cover_letter'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-xl-5">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php if ($cvUrl !== ''): ?>
                                    <a href="<?= sanitize($cvUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-person me-1"></i>
                                        Lihat Dokumen CV
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>CV belum tersedia</button>
                                <?php endif; ?>

                                <?php if ($transcriptUrl !== ''): ?>
                                    <a href="<?= sanitize($transcriptUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-text me-1"></i>
                                        Lihat Transkrip
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Transkrip belum tersedia</button>
                                <?php endif; ?>
                            </div>

                            <form class="applicant-status-form border rounded-3 p-3" data-application-id="<?= $applicationId; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="application_id" value="<?= $applicationId; ?>">

                                <div class="mb-3">
                                    <label for="statusSelect<?= $applicationId; ?>" class="form-label">Aksi Status</label>
                                    <select class="form-select" id="statusSelect<?= $applicationId; ?>" name="new_status" required>
                                        <option value="review" <?= $currentStatus === 'review' ? 'selected' : ''; ?>>Tandai Review</option>
                                        <option value="accepted" <?= $currentStatus === 'accepted' ? 'selected' : ''; ?>>Terima</option>
                                        <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : ''; ?>>Tolak</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="notes<?= $applicationId; ?>" class="form-label">Notes Opsional</label>
                                    <textarea class="form-control" id="notes<?= $applicationId; ?>" name="notes" rows="3" placeholder="Catatan untuk pelamar atau internal perusahaan..."><?= sanitize((string) ($application['notes'] ?? '')); ?></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">
                                        <span class="button-text">
                                            <i class="bi bi-check2-circle me-1"></i>
                                            Update Status
                                        </span>
                                        <span class="button-loading d-none">Menyimpan...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    const applicantToast = document.getElementById('applicantToast');
    const applicantToastBody = document.getElementById('applicantToastBody');

    function showApplicantToast(type, message) {
        applicantToast.classList.remove('text-bg-success', 'text-bg-danger');
        applicantToast.classList.add(type === 'success' ? 'text-bg-success' : 'text-bg-danger');
        applicantToastBody.textContent = message;
        bootstrap.Toast.getOrCreateInstance(applicantToast).show();
    }

    async function updateApplicantStatus(applicationId, newStatus, notes) {
        const formData = new FormData();
        formData.append('application_id', applicationId);
        formData.append('new_status', newStatus);
        formData.append('notes', notes);

        const response = await fetch('update_status.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'fetch'
            }
        });

        return response.json();
    }

    document.querySelectorAll('.applicant-status-form').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const buttonText = form.querySelector('.button-text');
            const buttonLoading = form.querySelector('.button-loading');
            const applicationId = form.dataset.applicationId;
            const newStatus = form.querySelector('[name="new_status"]').value;
            const notes = form.querySelector('[name="notes"]').value;

            button.disabled = true;
            buttonText.classList.add('d-none');
            buttonLoading.classList.remove('d-none');

            try {
                const result = await updateApplicantStatus(applicationId, newStatus, notes);

                if (!result.success) {
                    showApplicantToast('danger', result.message || 'Status gagal diperbarui.');
                    return;
                }

                document.getElementById('statusBadge' + applicationId).innerHTML = result.badge_html;
                showApplicantToast('success', result.message || 'Status berhasil diperbarui.');
            } catch (error) {
                showApplicantToast('danger', 'Terjadi kesalahan koneksi. Silakan coba lagi.');
            } finally {
                button.disabled = false;
                buttonText.classList.remove('d-none');
                buttonLoading.classList.add('d-none');
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
