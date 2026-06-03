<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_upload.php';

require_role('student');

$page_title = 'Cari Lowongan';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$studentProfile = null;
$studentProfileId = 0;
$categories = [];
$locations = [];
$jobs = [];
$selectedCategoryId = (int) ($_GET['category_id'] ?? 0);
$search = trim((string) ($_GET['search'] ?? ''));
$selectedLocation = trim((string) ($_GET['location'] ?? ''));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($currentPage - 1) * $perPage;
$totalJobs = 0;
$totalPages = 1;

function student_jobs_page_url(int $page, int $categoryId, string $search, string $location): string
{
    $params = ['page' => $page];

    if ($categoryId > 0) {
        $params['category_id'] = $categoryId;
    }

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($location !== '') {
        $params['location'] = $location;
    }

    return BASE_URL . '/student/jobs/index.php?' . http_build_query($params);
}

function student_jobs_status_badge(string $status): string
{
    $classes = [
        'pending' => 'secondary',
        'review' => 'warning text-dark',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];
    $labels = [
        'pending' => 'Pending',
        'review' => 'Review',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . '">' . sanitize($labels[$status] ?? ucfirst($status)) . '</span>';
}

try {
    $pdo = Database::getInstance()->getConnection();

    $profileStmt = $pdo->prepare(
        'SELECT id, profile_completed
         FROM student_profiles
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $studentProfile = $profileStmt->fetch();

    if ($studentProfile) {
        $studentProfileId = (int) $studentProfile['id'];
        $_SESSION['profile_completed'] = (int) $studentProfile['profile_completed'] === 1 ? 100 : (int) ($_SESSION['profile_completed'] ?? 0);
    }

    $categoryStmt = $pdo->query(
        'SELECT id, name
         FROM internship_categories
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $categories = $categoryStmt->fetchAll();

    $locationStmt = $pdo->query(
        'SELECT DISTINCT location
         FROM job_listings
         WHERE status = \'open\' AND deadline >= CURDATE()
         ORDER BY location ASC'
    );
    $locations = $locationStmt->fetchAll();

    $conditions = [
        'job_listings.status = \'open\'',
        'job_listings.deadline >= CURDATE()',
        'company_profiles.is_verified = 1',
    ];
    $params = [];

    if ($selectedCategoryId > 0) {
        $conditions[] = 'job_listings.category_id = :category_id';
        $params[':category_id'] = $selectedCategoryId;
    }

    if ($search !== '') {
        $conditions[] = '(job_listings.title LIKE :search OR job_listings.description LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    if ($selectedLocation !== '') {
        $conditions[] = 'job_listings.location = :location';
        $params[':location'] = $selectedLocation;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $conditions);

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM job_listings
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
         ' . $whereSql
    );

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
        'SELECT job_listings.id, job_listings.title, job_listings.description, job_listings.location,
                job_listings.quota, job_listings.deadline, job_listings.created_at,
                company_profiles.company_name, company_profiles.logo, company_profiles.is_verified,
                internship_categories.name AS category_name,
                COALESCE(accepted_counts.accepted_count, 0) AS accepted_count,
                my_applications.status AS application_status
         FROM job_listings
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
         LEFT JOIN (
             SELECT job_id, COUNT(*) AS accepted_count
             FROM applications
             WHERE status = \'accepted\'
             GROUP BY job_id
         ) accepted_counts ON accepted_counts.job_id = job_listings.id
         LEFT JOIN applications my_applications
             ON my_applications.job_id = job_listings.id
             AND my_applications.student_id = :student_profile_id
         ' . $whereSql . '
         ORDER BY job_listings.created_at DESC, job_listings.id DESC
         LIMIT :limit OFFSET :offset'
    );

    $stmt->bindValue(':student_profile_id', $studentProfileId, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load student jobs failed: ' . $exception->getMessage());
    set_flash('error', 'Data lowongan gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_student.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Cari Lowongan</h1>
        <p class="text-muted mb-0">Temukan lowongan magang yang sesuai dengan minat dan profil kamu.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/applications/index.php" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-text me-1"></i>
        Lamaran Saya
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET" action="index.php">
            <div class="col-12 col-lg-4">
                <label for="search" class="form-label">Cari Lowongan</label>
                <input
                    type="search"
                    class="form-control"
                    id="search"
                    name="search"
                    value="<?= sanitize($search); ?>"
                    placeholder="Cari judul atau deskripsi..."
                >
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label for="categoryId" class="form-label">Kategori</label>
                <select class="form-select" id="categoryId" name="category_id">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryId = (int) $category['id']; ?>
                        <option value="<?= $categoryId; ?>" <?= $selectedCategoryId === $categoryId ? 'selected' : ''; ?>>
                            <?= sanitize((string) $category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label for="location" class="form-label">Lokasi</label>
                <select class="form-select" id="location" name="location">
                    <option value="">Semua Lokasi</option>
                    <?php foreach ($locations as $location): ?>
                        <?php $locationName = (string) $location['location']; ?>
                        <option value="<?= sanitize($locationName); ?>" <?= $selectedLocation === $locationName ? 'selected' : ''; ?>>
                            <?= sanitize($locationName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-2 d-grid d-md-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>
                    Cari
                </button>
                <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">Lowongan Tersedia</h2>
        <p class="text-muted mb-0">Menampilkan <?= number_format(count($jobs)); ?> dari <?= number_format($totalJobs); ?> lowongan.</p>
    </div>
</div>

<?php if ($jobs === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-search-heart display-3 text-primary d-block mb-3"></i>
            <h2 class="h5 fw-bold">Tidak ada lowongan ditemukan</h2>
            <p class="text-muted mb-3">Coba ubah kata kunci, kategori, atau lokasi pencarian kamu.</p>
            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-outline-primary">Reset Filter</a>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($jobs as $job): ?>
        <?php
        $jobId = (int) $job['id'];
        $quota = (int) $job['quota'];
        $acceptedCount = (int) $job['accepted_count'];
        $remainingQuota = max(0, $quota - $acceptedCount);
        $hasApplied = !empty($job['application_status']);
        $logoUrl = !empty($job['logo']) ? get_file_url((string) $job['logo'], 'company_logos') : 'https://placehold.co/96x96?text=Logo';
        ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <img
                            src="<?= sanitize($logoUrl); ?>"
                            alt="Logo <?= sanitize((string) $job['company_name']); ?>"
                            class="rounded-3 border object-fit-cover"
                            style="width: 64px; height: 64px;"
                        >
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate"><?= sanitize((string) $job['company_name']); ?></div>
                            <?php if ((int) $job['is_verified'] === 1): ?>
                                <span class="badge bg-success">Terverifikasi</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum Verifikasi</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 class="h5 fw-bold mb-2"><?= sanitize((string) $job['title']); ?></h3>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary-subtle text-primary"><?= sanitize((string) $job['category_name']); ?></span>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-geo-alt me-1"></i>
                            <?= sanitize((string) $job['location']); ?>
                        </span>
                    </div>

                    <p class="text-muted small flex-grow-1"><?= sanitize(truncate_text((string) $job['description'], 120)); ?></p>

                    <div class="row g-2 small mb-3">
                        <div class="col-6">
                            <div class="text-muted">Kuota Tersisa</div>
                            <div class="fw-semibold <?= $remainingQuota <= 0 ? 'text-danger' : 'text-success'; ?>">
                                <?= number_format($remainingQuota); ?>/<?= number_format($quota); ?>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-muted">Deadline</div>
                            <div class="fw-semibold"><?= format_date((string) $job['deadline']); ?></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-2 mt-auto">
                        <?php if ($hasApplied): ?>
                            <div><?= student_jobs_status_badge((string) $job['application_status']); ?></div>
                            <button type="button" class="btn btn-sm btn-secondary" disabled>Sudah Dilamar</button>
                        <?php elseif ($remainingQuota <= 0): ?>
                            <span class="badge bg-danger">Kuota Penuh</span>
                            <button type="button" class="btn btn-sm btn-secondary" disabled>Kuota Penuh</button>
                        <?php else: ?>
                            <span class="badge bg-success">Open</span>
                            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/applications/apply.php?job_id=<?= $jobId; ?>" class="btn btn-sm btn-primary">
                                Lihat Detail
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Pagination lowongan mahasiswa">
        <ul class="pagination justify-content-end mb-0">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?= student_jobs_page_url(max(1, $currentPage - 1), $selectedCategoryId, $search, $selectedLocation); ?>">Sebelumnya</a>
            </li>
            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                <li class="page-item <?= $page === $currentPage ? 'active' : ''; ?>">
                    <a class="page-link" href="<?= student_jobs_page_url($page, $selectedCategoryId, $search, $selectedLocation); ?>"><?= $page; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?= student_jobs_page_url(min($totalPages, $currentPage + 1), $selectedCategoryId, $search, $selectedLocation); ?>">Berikutnya</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
