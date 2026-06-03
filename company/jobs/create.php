<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('company');

$page_title = 'Buat Lowongan';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$companyProfile = null;
$companyId = 0;
$categories = [];
$today = date('Y-m-d');
$old = [
    'title' => '',
    'description' => '',
    'requirements' => '',
    'category_id' => '',
    'location' => '',
    'quota' => '1',
    'start_date' => $today,
    'end_date' => '',
    'deadline' => '',
    'status' => 'draft',
];

function company_job_create_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : null;
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

    if (!$companyProfile) {
        set_flash('error', 'Lengkapi profil perusahaan terlebih dahulu');
        redirect(BASE_URL . '/company/profile.php');
    }

    $companyId = (int) $companyProfile['id'];
    $_SESSION['company_verified'] = (int) $companyProfile['is_verified'] === 1;

    if ((int) $companyProfile['is_verified'] !== 1) {
        set_flash('error', 'Akun perusahaan harus terverifikasi admin sebelum membuat lowongan');
        redirect(BASE_URL . '/company/jobs/index.php');
    }

    $categoryStmt = $pdo->query(
        'SELECT id, name
         FROM internship_categories
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $categories = $categoryStmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Prepare company job create failed: ' . $exception->getMessage());
    set_flash('error', 'Halaman buat lowongan gagal dimuat');
    redirect(BASE_URL . '/company/jobs/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/company/jobs/create.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $requirements = trim((string) ($_POST['requirements'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $location = trim((string) ($_POST['location'] ?? ''));
    $quota = (int) ($_POST['quota'] ?? 0);
    $startDateInput = trim((string) ($_POST['start_date'] ?? ''));
    $endDateInput = trim((string) ($_POST['end_date'] ?? ''));
    $deadlineInput = trim((string) ($_POST['deadline'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'draft'));
    $submitAction = trim((string) ($_POST['submit_action'] ?? ''));
    $errors = [];

    if ($submitAction === 'publish') {
        $status = 'open';
    } elseif ($submitAction === 'draft') {
        $status = 'draft';
    }

    $old = [
        'title' => sanitize($title),
        'description' => sanitize($description),
        'requirements' => sanitize($requirements),
        'category_id' => (string) $categoryId,
        'location' => sanitize($location),
        'quota' => (string) max(0, $quota),
        'start_date' => sanitize($startDateInput),
        'end_date' => sanitize($endDateInput),
        'deadline' => sanitize($deadlineInput),
        'status' => sanitize($status),
    ];

    if ($title === '') {
        $errors[] = 'Judul lowongan wajib diisi';
    } elseif (strlen($title) < 10) {
        $errors[] = 'Judul lowongan minimal 10 karakter';
    }

    if ($description === '') {
        $errors[] = 'Deskripsi lowongan wajib diisi';
    } elseif (strlen($description) < 50) {
        $errors[] = 'Deskripsi lowongan minimal 50 karakter';
    }

    if ($requirements === '') {
        $errors[] = 'Persyaratan wajib diisi';
    }

    if ($location === '') {
        $errors[] = 'Lokasi wajib diisi';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Kategori wajib dipilih';
    } else {
        $categoryCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM internship_categories WHERE id = :id AND is_active = 1');
        $categoryCheckStmt->execute([':id' => $categoryId]);

        if ((int) $categoryCheckStmt->fetchColumn() === 0) {
            $errors[] = 'Kategori tidak valid atau tidak aktif';
        }
    }

    if ($quota <= 0) {
        $errors[] = 'Kuota wajib berupa angka positif';
    } elseif ($quota > 100) {
        $errors[] = 'Kuota maksimal 100';
    }

    $startDate = company_job_create_date($startDateInput);
    $endDate = company_job_create_date($endDateInput);
    $deadline = company_job_create_date($deadlineInput);
    $todayDate = new DateTimeImmutable('today');

    if (!$startDate) {
        $errors[] = 'Tanggal mulai wajib diisi dengan format valid';
    } elseif ($startDate < $todayDate) {
        $errors[] = 'Tanggal mulai tidak boleh di masa lalu';
    }

    if (!$endDate) {
        $errors[] = 'Tanggal selesai wajib diisi dengan format valid';
    } elseif ($startDate && $endDate <= $startDate) {
        $errors[] = 'Tanggal selesai harus setelah tanggal mulai';
    }

    if (!$deadline) {
        $errors[] = 'Deadline wajib diisi dengan format valid';
    } elseif ($endDate && $deadline >= $endDate) {
        $errors[] = 'Deadline harus sebelum tanggal selesai';
    }

    if (!in_array($status, ['open', 'draft'], true)) {
        $errors[] = 'Status lowongan tidak valid';
    }

    if ($errors === []) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO job_listings
                    (company_id, category_id, title, description, requirements, location, quota, start_date, end_date, deadline, status)
                 VALUES
                    (:company_id, :category_id, :title, :description, :requirements, :location, :quota, :start_date, :end_date, :deadline, :status)'
            );
            $stmt->execute([
                ':company_id' => $companyId,
                ':category_id' => $categoryId,
                ':title' => $title,
                ':description' => $description,
                ':requirements' => $requirements,
                ':location' => $location,
                ':quota' => $quota,
                ':start_date' => $startDateInput,
                ':end_date' => $endDateInput,
                ':deadline' => $deadlineInput,
                ':status' => $status,
            ]);

            log_activity($userId, 'create_job_listing', 'Perusahaan membuat lowongan: ' . $title);
            set_flash('success', $status === 'open' ? 'Lowongan berhasil dipublish' : 'Lowongan berhasil disimpan sebagai draft');
            redirect(BASE_URL . '/company/jobs/index.php?status=' . $status);
        } catch (PDOException $exception) {
            error_log('Create company job failed: ' . $exception->getMessage());
            $errors[] = 'Lowongan gagal dibuat. Silakan coba lagi nanti';
        }
    }

    foreach ($errors as $error) {
        set_flash('error', $error);
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_company.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Buat Lowongan Baru</h1>
        <p class="text-muted mb-0">Isi detail lowongan magang untuk <?= sanitize((string) $companyProfile['company_name']); ?>.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/company/jobs/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>

<?= display_flash(); ?>

<?php if ($categories === []): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Belum ada kategori aktif. Hubungi admin untuk mengaktifkan kategori magang.
    </div>
<?php endif; ?>

<form id="jobForm" class="needs-validation" method="POST" action="create.php" novalidate>
    <?= csrf_field(); ?>
    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">Informasi Lowongan</h2>

                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Lowongan</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= $old['title']; ?>" minlength="10" required>
                        <div class="form-text">Minimal 10 karakter.</div>
                        <div class="invalid-feedback">Judul lowongan wajib diisi minimal 10 karakter.</div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <label for="description" class="form-label mb-0">Deskripsi</label>
                            <span class="small text-muted"><span id="descriptionCount">0</span>/50 karakter minimum</span>
                        </div>
                        <textarea class="form-control mt-2" id="description" name="description" rows="8" minlength="50" required><?= $old['description']; ?></textarea>
                        <div class="form-text">Jelaskan tanggung jawab, tujuan magang, dan gambaran pekerjaan.</div>
                        <div class="invalid-feedback">Deskripsi wajib diisi minimal 50 karakter.</div>
                    </div>

                    <div>
                        <label for="requirements" class="form-label">Persyaratan</label>
                        <textarea class="form-control" id="requirements" name="requirements" rows="7" required><?= $old['requirements']; ?></textarea>
                        <div class="form-text">Tuliskan skill, jurusan, dokumen, atau kriteria lain yang dibutuhkan.</div>
                        <div class="invalid-feedback">Persyaratan wajib diisi.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">Pengaturan</h2>

                    <div class="mb-3">
                        <label for="categoryId" class="form-label">Kategori</label>
                        <select class="form-select" id="categoryId" name="category_id" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <?php $categoryId = (int) $category['id']; ?>
                                <option value="<?= $categoryId; ?>" <?= $old['category_id'] === (string) $categoryId ? 'selected' : ''; ?>>
                                    <?= sanitize((string) $category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Kategori wajib dipilih.</div>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Lokasi</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?= $old['location']; ?>" placeholder="Surabaya / Remote / Hybrid" required>
                        <div class="invalid-feedback">Lokasi wajib diisi.</div>
                    </div>

                    <div class="mb-3">
                        <label for="quota" class="form-label">Kuota</label>
                        <input type="number" class="form-control" id="quota" name="quota" value="<?= $old['quota']; ?>" min="1" max="100" required>
                        <div class="invalid-feedback">Kuota wajib 1 sampai 100.</div>
                    </div>

                    <div class="mb-3">
                        <label for="startDate" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="startDate" name="start_date" value="<?= $old['start_date']; ?>" min="<?= $today; ?>" required>
                        <div class="invalid-feedback">Tanggal mulai tidak boleh di masa lalu.</div>
                    </div>

                    <div class="mb-3">
                        <label for="endDate" class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="endDate" name="end_date" value="<?= $old['end_date']; ?>" required>
                        <div class="invalid-feedback">Tanggal selesai harus setelah tanggal mulai.</div>
                    </div>

                    <div class="mb-3">
                        <label for="deadline" class="form-label">Deadline Lamaran</label>
                        <input type="date" class="form-control" id="deadline" name="deadline" value="<?= $old['deadline']; ?>" required>
                        <div class="invalid-feedback">Deadline harus sebelum tanggal selesai.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label d-block">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="statusOpen" value="open" <?= $old['status'] === 'open' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="statusOpen">Publish</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="statusDraft" value="draft" <?= $old['status'] !== 'open' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="statusDraft">Simpan Draft</label>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_action" value="publish" class="btn btn-primary" <?= $categories === [] ? 'disabled' : ''; ?>>
                            <i class="bi bi-megaphone me-1"></i>
                            Publish Sekarang
                        </button>
                        <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary" <?= $categories === [] ? 'disabled' : ''; ?>>
                            <i class="bi bi-save me-1"></i>
                            Simpan sebagai Draft
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    const form = document.getElementById('jobForm');
    const description = document.getElementById('description');
    const descriptionCount = document.getElementById('descriptionCount');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const deadline = document.getElementById('deadline');
    const statusOpen = document.getElementById('statusOpen');
    const statusDraft = document.getElementById('statusDraft');

    function updateDescriptionCount() {
        const length = description.value.trim().length;
        descriptionCount.textContent = length;
        descriptionCount.classList.toggle('text-danger', length < 50);
        descriptionCount.classList.toggle('text-success', length >= 50);
    }

    function validateDates() {
        const today = new Date('<?= $today; ?>T00:00:00');
        const start = startDate.value ? new Date(startDate.value + 'T00:00:00') : null;
        const end = endDate.value ? new Date(endDate.value + 'T00:00:00') : null;
        const closeDate = deadline.value ? new Date(deadline.value + 'T00:00:00') : null;
        let valid = true;

        startDate.setCustomValidity('');
        endDate.setCustomValidity('');
        deadline.setCustomValidity('');

        if (start && start < today) {
            startDate.setCustomValidity('Tanggal mulai tidak boleh di masa lalu.');
            valid = false;
        }

        if (start && end && end <= start) {
            endDate.setCustomValidity('Tanggal selesai harus setelah tanggal mulai.');
            valid = false;
        }

        if (end && closeDate && closeDate >= end) {
            deadline.setCustomValidity('Deadline harus sebelum tanggal selesai.');
            valid = false;
        }

        return valid;
    }

    description.addEventListener('input', updateDescriptionCount);
    [startDate, endDate, deadline].forEach(input => input.addEventListener('change', validateDates));

    form.addEventListener('submit', event => {
        const submitter = event.submitter;

        if (submitter && submitter.value === 'publish') {
            statusOpen.checked = true;
        }

        if (submitter && submitter.value === 'draft') {
            statusDraft.checked = true;
        }

        const datesValid = validateDates();

        if (!form.checkValidity() || !datesValid) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');
    });

    updateDescriptionCount();
    validateDates();
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
