<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_upload.php';

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
        'SELECT job_listings.*, company_profiles.company_name, company_profiles.industry,
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

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row gap-3 mb-4">
                    <img
                        src="<?= sanitize($companyLogoUrl); ?>"
                        alt="Logo <?= sanitize((string) $job['company_name']); ?>"
                        class="rounded-3 border object-fit-cover"
                        style="width: 88px; height: 88px;"
                    >
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-primary-subtle text-primary"><?= sanitize((string) $job['category_name']); ?></span>
                            <?php if ((int) $job['is_verified'] === 1): ?>
                                <span class="badge bg-success">Perusahaan Terverifikasi</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum Terverifikasi</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h3 fw-bold mb-2"><?= sanitize((string) $job['title']); ?></h2>
                        <div class="text-muted">
                            <i class="bi bi-building me-1"></i>
                            <?= sanitize((string) $job['company_name']); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-geo-alt me-1"></i>
                            <?= sanitize((string) $job['location']); ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-muted small">Periode Magang</div>
                            <div class="fw-semibold"><?= format_date((string) $job['start_date']); ?> - <?= format_date((string) $job['end_date']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-muted small">Kuota</div>
                            <div class="fw-semibold"><?= number_format($remainingQuota); ?> tersisa dari <?= number_format((int) $job['quota']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-muted small">Deadline</div>
                            <div class="fw-semibold"><?= format_date((string) $job['deadline']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="h5 fw-bold mb-3">Deskripsi Lowongan</h3>
                    <div class="text-muted lh-lg"><?= student_apply_render_text((string) $job['description']); ?></div>
                </div>

                <div class="mb-4">
                    <h3 class="h5 fw-bold mb-3">Persyaratan</h3>
                    <div class="text-muted lh-lg"><?= student_apply_render_text((string) $job['requirements']); ?></div>
                </div>

                <div>
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
                                <div><?= student_apply_render_text((string) $job['company_description']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 1rem;">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Lamar Sekarang</h2>

                <div class="alert <?= $deadlineDays < 3 ? 'alert-danger' : 'alert-info'; ?> mb-3">
                    <div class="fw-semibold">
                        <i class="bi bi-clock me-1"></i>
                        Deadline <?= format_date((string) $job['deadline']); ?>
                    </div>
                    <div class="<?= $deadlineClass; ?>">
                        <?= $deadlineDays === 0 ? 'Berakhir hari ini' : 'Sisa ' . number_format(max(0, $deadlineDays)) . ' hari'; ?>
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
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmApplyModal"
                            <?= $remainingQuota <= 0 ? 'disabled' : ''; ?>
                        >
                            <i class="bi bi-send me-1"></i>
                            Kirim Lamaran
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

    function updateCoverLetterCount() {
        coverLetterCount.textContent = coverLetter.value.trim().length;
    }

    coverLetter.addEventListener('input', () => {
        updateCoverLetterCount();

        if (coverLetter.value.trim() !== '' && coverLetter.value.trim().length < 50) {
            coverLetter.setCustomValidity('Cover letter minimal 50 karakter jika diisi.');
        } else {
            coverLetter.setCustomValidity('');
        }
    });

    applicationForm.addEventListener('submit', (event) => {
        if (coverLetter.value.trim() !== '' && coverLetter.value.trim().length < 50) {
            coverLetter.setCustomValidity('Cover letter minimal 50 karakter jika diisi.');
        } else {
            coverLetter.setCustomValidity('');
        }

        if (!applicationForm.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        applicationForm.classList.add('was-validated');
    });

    updateCoverLetterCount();
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
