<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_upload.php';
require_once __DIR__ . '/../../includes/push_notification.php';

require_role('student');

$page_title = 'Detail Lowongan';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$jobId = (int) ($_GET['job_id'] ?? 0);
$studentProfile = null;
$job = null;
$hasApplied = false;
$acceptedCount = 0;
$remainingQuota = 0;
$deadlineDays = 0;
$deadlineClass = 'text-success';
$coverLetter = '';
$errors = [];

function student_apply_file_label(?string $filename): string
{
    if ($filename === null || trim($filename) === '') {
        return 'Belum diupload';
    }

    return basename($filename);
}

function student_apply_render_text(string $text): string
{
    return nl2br(sanitize($text));
}

if ($jobId <= 0) {
    set_flash('error', 'Lowongan tidak valid');
    redirect(BASE_URL . '/student/jobs/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();

    $profileStmt = $pdo->prepare(
        'SELECT student_profiles.*, users.email
         FROM student_profiles
         INNER JOIN users ON users.id = student_profiles.user_id
         WHERE student_profiles.user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $studentProfile = $profileStmt->fetch();

    if (!$studentProfile) {
        set_flash('warning', 'Lengkapi profil kamu terlebih dahulu sebelum melamar magang');
        redirect(BASE_URL . '/student/profile.php');
    }

    $studentId = (int) $studentProfile['id'];
    $hasRequiredDocuments = trim((string) ($studentProfile['cv_file'] ?? '')) !== ''
        && trim((string) ($studentProfile['transcript_file'] ?? '')) !== ''
        && (int) ($studentProfile['profile_completed'] ?? 0) === 1;

    if (!$hasRequiredDocuments) {
        set_flash('warning', 'Upload CV dan transkrip terlebih dahulu sebelum melamar magang');
        redirect(BASE_URL . '/student/profile.php?tab=documents');
    }

    $jobStmt = $pdo->prepare(
        'SELECT job_listings.*, company_profiles.user_id AS company_user_id, company_profiles.company_name, company_profiles.industry,
                company_profiles.description AS company_description, company_profiles.address AS company_address,
                company_profiles.phone AS company_phone, company_profiles.website, company_profiles.logo,
                company_profiles.is_verified,
                internship_categories.name AS category_name
         FROM job_listings
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
         WHERE job_listings.id = :job_id
         LIMIT 1'
    );
    $jobStmt->execute([':job_id' => $jobId]);
    $job = $jobStmt->fetch();

    if (!$job) {
        set_flash('error', 'Lowongan tidak ditemukan');
        redirect(BASE_URL . '/student/jobs/index.php');
    }

    if ((string) $job['status'] !== 'open' || (string) $job['deadline'] < date('Y-m-d')) {
        set_flash('warning', 'Lowongan ini sudah tidak menerima lamaran');
        redirect(BASE_URL . '/student/jobs/index.php');
    }

    $applicationStmt = $pdo->prepare(
        'SELECT id, status
         FROM applications
         WHERE student_id = :student_id AND job_id = :job_id
         LIMIT 1'
    );
    $applicationStmt->execute([
        ':student_id' => $studentId,
        ':job_id' => $jobId,
    ]);
    $existingApplication = $applicationStmt->fetch();
    $hasApplied = (bool) $existingApplication;

    if ($hasApplied) {
        set_flash('info', 'Kamu sudah pernah melamar lowongan ini');
        redirect(BASE_URL . '/student/applications/index.php');
    }

    $acceptedStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM applications
         WHERE job_id = :job_id AND status = \'accepted\''
    );
    $acceptedStmt->execute([':job_id' => $jobId]);
    $acceptedCount = (int) $acceptedStmt->fetchColumn();
    $remainingQuota = max(0, (int) $job['quota'] - $acceptedCount);

    $today = new DateTimeImmutable('today');
    $deadlineDate = new DateTimeImmutable((string) $job['deadline']);
    $deadlineDays = (int) $today->diff($deadlineDate)->format('%r%a');
    $deadlineClass = $deadlineDays < 3 ? 'text-danger' : ($deadlineDays <= 7 ? 'text-warning' : 'text-success');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
            set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
            redirect(BASE_URL . '/student/applications/apply.php?job_id=' . $jobId);
        }

        $coverLetter = trim((string) ($_POST['cover_letter'] ?? ''));
        $validator = (new Validator($_POST))
            ->min_length('cover_letter', 50, 'Cover letter')
            ->max_length('cover_letter', 2000, 'Cover letter');
        if ($validator->fails()) {
            $errors = array_merge($errors, ...array_values($validator->errors()));
        }

        if ($remainingQuota <= 0) {
            $errors[] = 'Kuota lowongan sudah penuh';
        }

        if ($errors === []) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO applications (student_id, job_id, cover_letter, status)
                 VALUES (:student_id, :job_id, :cover_letter, \'pending\')'
            );
            $insertStmt->execute([
                ':student_id' => $studentId,
                ':job_id' => $jobId,
                ':cover_letter' => $coverLetter !== '' ? $coverLetter : null,
            ]);

            log_activity(
                $userId,
                'application_created',
                'Mahasiswa mengirim lamaran untuk lowongan: ' . (string) $job['title']
            );

            try {
                notify_user(
                    (int) $job['company_user_id'],
                    'Lamaran Baru Masuk!',
                    (string) $studentProfile['full_name'] . ' melamar ' . (string) $job['title'],
                    BASE_URL . '/company/applicants/index.php'
                );
            } catch (Throwable $exception) {
                error_log('Push notification for new application failed: ' . $exception->getMessage());
            }

            set_flash('success', 'Lamaran berhasil dikirim!');
            redirect(BASE_URL . '/student/applications/index.php');
        }

        foreach ($errors as $error) {
            set_flash('error', $error);
        }
    }
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        set_flash('info', 'Kamu sudah pernah melamar lowongan ini');
        redirect(BASE_URL . '/student/applications/index.php');
    }

    error_log('Student apply failed: ' . $exception->getMessage());
    set_flash('error', 'Halaman lamaran gagal dimuat. Silakan coba lagi nanti');
    redirect(BASE_URL . '/student/jobs/index.php');
}

$companyLogoUrl = !empty($job['logo']) ? get_file_url((string) $job['logo'], 'company_logos') : 'https://placehold.co/112x112?text=Logo';
$cvUrl = get_file_url((string) $studentProfile['cv_file'], 'cv');
$transcriptUrl = get_file_url((string) $studentProfile['transcript_file'], 'transcripts');
$categoryImages = [
    'teknologi' => 'photo-1518770660439-4636190af475',
    'bisnis' => 'photo-1507003211169-0a1dd7228f2d',
    'desain' => 'photo-1561070791-2526d30994b5',
    'engineering' => 'photo-1581091226825-a6a2a5aee158',
];
$categoryKey = strtolower((string) ($job['category_name'] ?? ''));
$bgPhoto = $categoryImages[$categoryKey] ?? 'photo-1521737852567-6949f3f9f2b5';
$bgUrl = 'https://images.unsplash.com/' . $bgPhoto . '?w=1200&q=80';
$quota = (int) $job['quota'];
$filledQuota = max(0, $quota - $remainingQuota);
$quotaPercent = $quota > 0 ? min(100, (int) round(($filledQuota / $quota) * 100)) : 0;
$workType = str_contains(strtolower((string) $job['location']), 'remote') || str_contains(strtolower((string) $job['location']), 'wfh') ? 'WFH' : 'WFO';

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_student.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Detail Lowongan</h1>
        <p class="text-muted mb-0">Baca detail lowongan sebelum mengirim lamaran.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali ke Lowongan
    </a>
</div>

<?= display_flash(); ?>

<style>
    .job-hero {
        min-height: 360px;
        border-radius: 28px;
        overflow: hidden;
        position: relative;
        background-size: cover;
        background-position: center;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.18);
    }

    .job-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.86), rgba(15, 23, 42, 0.48));
    }

    .job-hero-content {
        position: relative;
        z-index: 1;
        color: #fff;
    }

    .info-chip-row {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 6px;
        -webkit-overflow-scrolling: touch;
    }

    .info-chip {
        min-width: max-content;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        background: #fff;
        padding: 10px 14px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .apply-sticky-card {
        position: sticky;
        top: 80px;
    }

    .job-detail-tab .nav-link {
        color: #475569;
        font-weight: 700;
    }

    .job-detail-tab .nav-link.active {
        color: #6366f1;
    }

    @media (max-width: 1199.98px) {
        .apply-sticky-card {
            position: static;
        }
    }
</style>

<section class="job-hero mb-4" style="background-image: url('<?= sanitize($bgUrl); ?>');">
    <div class="job-hero-content h-100 p-4 p-lg-5 d-flex flex-column justify-content-end">
        <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end justify-content-between">
            <div>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <img
                        src="<?= sanitize($companyLogoUrl); ?>"
                        alt="Logo <?= sanitize((string) $job['company_name']); ?>"
                        class="rounded-4 border border-white border-opacity-25 object-fit-cover bg-white"
                        style="width: 86px; height: 86px;"
                    >
                    <div>
                        <div class="fw-semibold fs-5"><?= sanitize((string) $job['company_name']); ?></div>
                        <div class="text-white-50"><?= sanitize((string) $job['industry']); ?></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-primary px-3 py-2"><?= sanitize((string) $job['category_name']); ?></span>
                    <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 px-3 py-2">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= sanitize((string) $job['location']); ?>
                    </span>
                    <?php if ((int) $job['is_verified'] === 1): ?>
                        <span class="badge bg-success px-3 py-2">Perusahaan Terverifikasi</span>
                    <?php endif; ?>
                </div>
                <h2 class="display-6 fw-bold mb-0"><?= sanitize((string) $job['title']); ?></h2>
            </div>
            <button type="button" class="btn btn-light" id="shareJobButton">
                <i class="bi bi-share me-1"></i>
                Bagikan
            </button>
        </div>
    </div>
</section>

<div class="info-chip-row mb-4">
    <div class="info-chip">
        <span class="text-muted small d-block">Kuota</span>
        <span class="fw-bold"><?= number_format($remainingQuota); ?> tersisa dari <?= number_format($quota); ?></span>
    </div>
    <div class="info-chip">
        <span class="text-muted small d-block">Periode</span>
        <span class="fw-bold"><?= format_date((string) $job['start_date']); ?> - <?= format_date((string) $job['end_date']); ?></span>
    </div>
    <div class="info-chip">
        <span class="text-muted small d-block">Deadline</span>
        <span class="fw-bold"><?= format_date((string) $job['deadline']); ?></span>
    </div>
    <div class="info-chip">
        <span class="text-muted small d-block">Tipe</span>
        <span class="fw-bold"><?= sanitize($workType); ?></span>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <ul class="nav nav-pills job-detail-tab gap-2 mb-4" id="jobDetailTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-bs-toggle="pill" data-bs-target="#description-pane" type="button" role="tab">
                            Deskripsi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="requirements-tab" data-bs-toggle="pill" data-bs-target="#requirements-pane" type="button" role="tab">
                            Persyaratan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="company-tab" data-bs-toggle="pill" data-bs-target="#company-pane" type="button" role="tab">
                            Tentang Perusahaan
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="description-pane" role="tabpanel" aria-labelledby="description-tab" tabindex="0">
                        <h3 class="h5 fw-bold mb-3">Deskripsi Lowongan</h3>
                        <div class="text-muted lh-lg"><?= student_apply_render_text((string) $job['description']); ?></div>
                    </div>
                    <div class="tab-pane fade" id="requirements-pane" role="tabpanel" aria-labelledby="requirements-tab" tabindex="0">
                        <h3 class="h5 fw-bold mb-3">Persyaratan</h3>
                        <div class="text-muted lh-lg"><?= student_apply_render_text((string) $job['requirements']); ?></div>
                    </div>
                    <div class="tab-pane fade" id="company-pane" role="tabpanel" aria-labelledby="company-tab" tabindex="0">
                        <h3 class="h5 fw-bold mb-3">Tentang Perusahaan</h3>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Industri</div>
                                <div class="fw-semibold"><?= sanitize((string) $job['industry']); ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Website</div>
                                <?php if (!empty($job['website'])): ?>
                                    <a href="<?= sanitize((string) $job['website']); ?>" target="_blank" rel="noopener" class="fw-semibold">
                                        <?= sanitize((string) $job['website']); ?>
                                    </a>
                                <?php else: ?>
                                    <div class="fw-semibold">-</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Alamat</div>
                                <div class="fw-semibold"><?= sanitize((string) $job['company_address']); ?></div>
                            </div>
                            <?php if (!empty($job['company_description'])): ?>
                                <div class="col-12">
                                    <div class="text-muted small">Profil Singkat</div>
                                    <div class="text-muted lh-lg"><?= student_apply_render_text((string) $job['company_description']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm apply-sticky-card">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-3">Lamar Sekarang</h2>

                <div class="alert <?= $deadlineDays < 3 ? 'alert-danger' : 'alert-info'; ?> mb-3">
                    <div class="fw-semibold">
                        <i class="bi bi-clock me-1"></i>
                        Deadline <?= format_date((string) $job['deadline']); ?>
                    </div>
                    <div class="<?= $deadlineClass; ?>" id="deadlineCountdown" data-deadline-date="<?= sanitize((string) $job['deadline']); ?>">
                        <?= $deadlineDays === 0 ? 'Berakhir hari ini' : 'Sisa ' . number_format(max(0, $deadlineDays)) . ' hari'; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Status Kuota</span>
                        <span class="small text-muted"><?= number_format($filledQuota); ?>/<?= number_format($quota); ?> terisi</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar" role="progressbar" style="width: <?= $quotaPercent; ?>%;" aria-valuenow="<?= $quotaPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Dokumen Terlampir</span>
                        <a href="<?= rtrim(BASE_URL, '/'); ?>/student/profile.php?tab=documents" class="small">Ubah</a>
                    </div>
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                            <div class="min-w-0">
                                <div class="small text-muted">CV</div>
                                <a href="<?= sanitize($cvUrl); ?>" target="_blank" class="fw-semibold text-truncate d-block">
                                    <?= sanitize(student_apply_file_label((string) $studentProfile['cv_file'])); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded-3 p-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
                            <div class="min-w-0">
                                <div class="small text-muted">Transkrip</div>
                                <a href="<?= sanitize($transcriptUrl); ?>" target="_blank" class="fw-semibold text-truncate d-block">
                                    <?= sanitize(student_apply_file_label((string) $studentProfile['transcript_file'])); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="applicationForm" class="needs-validation" method="POST" action="apply.php?job_id=<?= $jobId; ?>" novalidate>
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <label for="coverLetter" class="form-label mb-0">Cover Letter <span class="text-muted">(opsional)</span></label>
                            <span class="small text-muted"><span id="coverLetterCount">0</span> karakter</span>
                        </div>
                        <textarea
                            class="form-control mt-2"
                            id="coverLetter"
                            name="cover_letter"
                            rows="7"
                            minlength="50"
                            placeholder="Ceritakan alasan kamu tertarik dengan lowongan ini..."
                        ><?= sanitize($coverLetter); ?></textarea>
                        <div class="form-text">Jika diisi, minimal 50 karakter.</div>
                        <div class="invalid-feedback">Cover letter minimal 50 karakter jika diisi.</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button
                            type="button"
                            class="btn btn-primary btn-lg"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmApplyModal"
                            <?= $remainingQuota <= 0 ? 'disabled' : ''; ?>
                        >
                            <i class="bi bi-send me-1"></i>
                            Lamar Posisi Ini
                        </button>
                        <?php if ($remainingQuota <= 0): ?>
                            <div class="text-danger small text-center">Kuota lowongan sudah penuh.</div>
                        <?php endif; ?>
                    </div>

                    <div class="modal fade" id="confirmApplyModal" tabindex="-1" aria-labelledby="confirmApplyModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="confirmApplyModalLabel">Konfirmasi Lamaran</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Pastikan data profil, CV, dan transkrip sudah benar. Kirim lamaran untuk posisi
                                    <strong><?= sanitize((string) $job['title']); ?></strong>?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Ya, Kirim Lamaran</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const applicationForm = document.getElementById('applicationForm');
    const coverLetter = document.getElementById('coverLetter');
    const coverLetterCount = document.getElementById('coverLetterCount');
    const shareJobButton = document.getElementById('shareJobButton');
    const deadlineCountdown = document.getElementById('deadlineCountdown');

    function updateCoverLetterCount() {
        coverLetterCount.textContent = coverLetter.value.trim().length;
    }

    function validateCoverLetter() {
        if (coverLetter.value.trim() !== '' && coverLetter.value.trim().length < 50) {
            coverLetter.setCustomValidity('Cover letter minimal 50 karakter jika diisi.');
        } else {
            coverLetter.setCustomValidity('');
        }
    }

    function updateDeadlineCountdown() {
        if (!deadlineCountdown) {
            return;
        }

        const deadlineDate = new Date(`${deadlineCountdown.dataset.deadlineDate}T23:59:59`);
        const now = new Date();
        const diff = deadlineDate.getTime() - now.getTime();

        if (Number.isNaN(deadlineDate.getTime())) {
            return;
        }

        if (diff <= 0) {
            deadlineCountdown.textContent = 'Deadline telah berakhir';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
        const minutes = Math.floor((diff / (1000 * 60)) % 60);
        const seconds = Math.floor((diff / 1000) % 60);

        if (days > 0) {
            deadlineCountdown.textContent = `Sisa ${days} hari`;
            return;
        }

        deadlineCountdown.textContent = `Berakhir dalam ${hours}j ${minutes}m ${seconds}d`;
    }

    coverLetter.addEventListener('input', () => {
        updateCoverLetterCount();
        validateCoverLetter();
    });

    applicationForm.addEventListener('submit', (event) => {
        validateCoverLetter();

        if (!applicationForm.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        applicationForm.classList.add('was-validated');
    });

    shareJobButton.addEventListener('click', async () => {
        const shareData = {
            title: <?= json_encode((string) $job['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            text: <?= json_encode('Lowongan magang di ' . (string) $job['company_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            url: window.location.href
        };

        try {
            if (navigator.share) {
                await navigator.share(shareData);
                return;
            }

            await navigator.clipboard.writeText(window.location.href);
            shareJobButton.innerHTML = '<i class="bi bi-check2 me-1"></i>Link Disalin';
            setTimeout(() => {
                shareJobButton.innerHTML = '<i class="bi bi-share me-1"></i>Bagikan';
            }, 2200);
        } catch (error) {
            console.error('Share failed', error);
        }
    });

    updateCoverLetterCount();
    updateDeadlineCountdown();
    setInterval(updateDeadlineCountdown, 1000);
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
