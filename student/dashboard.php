<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$page_title = 'Dashboard Mahasiswa';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$studentProfile = null;
$studentId = 0;
$profileCompletion = 0;
$my_applications = [];
$recent_jobs = [];
$my_recent_apps = [];
$accepted_count = 0;
$pending_count = 0;
$rejected_count = 0;
$review_count = 0;
$active_count = 0;
$statusChartLabels = ['Pending', 'Review', 'Diterima', 'Ditolak'];
$statusChartData = [0, 0, 0, 0];

function student_dashboard_profile_completion(?array $profile): int
{
    if (!$profile) {
        return 0;
    }

    $fields = [
        'full_name',
        'student_id',
        'phone',
        'address',
        'program_id',
        'cv_file',
        'transcript_file',
    ];
    $filled = 0;

    foreach ($fields as $field) {
        $value = $profile[$field] ?? null;

        if ($value !== null && trim((string) $value) !== '' && (string) $value !== '0') {
            $filled++;
        }
    }

    return (int) round(($filled / count($fields)) * 100);
}

try {
    $pdo = Database::getInstance()->getConnection();
    $profileStmt = $pdo->prepare(
        'SELECT student_profiles.*, study_programs.name AS program_name
         FROM student_profiles
         LEFT JOIN study_programs ON study_programs.id = student_profiles.program_id
         WHERE student_profiles.user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $studentProfile = $profileStmt->fetch();

    if ($studentProfile) {
        $studentId = (int) $studentProfile['id'];
        $profileCompletion = student_dashboard_profile_completion($studentProfile);
    }

    $_SESSION['profile_completed'] = $profileCompletion;

    if ($studentId > 0) {
        $applicationsStmt = $pdo->prepare(
            'SELECT applications.id, applications.status, applications.created_at,
                    job_listings.title AS job_title,
                    company_profiles.company_name
             FROM applications
             INNER JOIN job_listings ON job_listings.id = applications.job_id
             INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
             WHERE applications.student_id = :student_id
             ORDER BY applications.created_at DESC'
        );
        $applicationsStmt->execute([':student_id' => $studentId]);
        $my_applications = $applicationsStmt->fetchAll();

        foreach ($my_applications as $application) {
            $status = (string) $application['status'];

            if ($status === 'pending') {
                $pending_count++;
            } elseif ($status === 'review') {
                $review_count++;
            } elseif ($status === 'accepted') {
                $accepted_count++;
            } elseif ($status === 'rejected') {
                $rejected_count++;
            }
        }

        $active_count = $pending_count + $review_count;
        $statusChartData = [$pending_count, $review_count, $accepted_count, $rejected_count];

        $recentAppsStmt = $pdo->prepare(
            'SELECT applications.status, applications.created_at,
                    job_listings.title AS job_title,
                    company_profiles.company_name
             FROM applications
             INNER JOIN job_listings ON job_listings.id = applications.job_id
             INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
             WHERE applications.student_id = :student_id
             ORDER BY applications.created_at DESC
             LIMIT 5'
        );
        $recentAppsStmt->execute([':student_id' => $studentId]);
        $my_recent_apps = $recentAppsStmt->fetchAll();
    }

    $recentJobsStmt = $pdo->query(
        'SELECT job_listings.id, job_listings.title, job_listings.location, job_listings.deadline,
                company_profiles.company_name,
                internship_categories.name AS category_name
         FROM job_listings
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
         WHERE job_listings.status = \'open\'
           AND job_listings.deadline >= CURDATE()
         ORDER BY job_listings.created_at DESC, job_listings.id DESC
         LIMIT 5'
    );
    $recent_jobs = $recentJobsStmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Student dashboard query failed: ' . $exception->getMessage());
    set_flash('error', 'Data dashboard gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_student.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Dashboard Mahasiswa</h1>
        <p class="text-muted mb-0">
            <?= $studentProfile ? sanitize((string) $studentProfile['full_name']) : 'Lengkapi profil untuk mulai melamar magang.'; ?>
        </p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-outline-primary">
        <i class="bi bi-search me-1"></i>
        Cari Lowongan
    </a>
</div>

<?= display_flash(); ?>

<?php if ($profileCompletion < 100): ?>
    <div class="alert alert-warning d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <div class="fw-semibold">Lengkapi profil kamu untuk melamar magang!</div>
                <div>Profil yang lengkap membantu perusahaan menilai lamaran kamu dengan lebih cepat.</div>
            </div>
        </div>
        <a href="<?= rtrim(BASE_URL, '/'); ?>/student/profile.php" class="btn btn-sm btn-warning text-nowrap">
            Lengkapi Profil
        </a>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
            <div>
                <h2 class="h5 fw-bold mb-1">Kelengkapan Profil</h2>
                <p class="text-muted small mb-0">Nama, NIM, telepon, alamat, prodi, CV, dan transkrip.</p>
            </div>
            <span class="badge <?= $profileCompletion === 100 ? 'bg-success' : 'bg-warning text-dark'; ?> fs-6">
                <?= $profileCompletion; ?>%
            </span>
        </div>
        <div class="progress profile-progress" style="height: 12px;">
            <div
                class="progress-bar <?= $profileCompletion === 100 ? 'bg-success' : 'bg-warning'; ?>"
                role="progressbar"
                style="width: <?= $profileCompletion; ?>%;"
                aria-valuenow="<?= $profileCompletion; ?>"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-file-earmark-text fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Lamaran Aktif</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($active_count); ?></div>
                    <div class="small text-muted"><?= number_format(count($my_applications)); ?> total lamaran</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-check-circle fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Diterima</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($accepted_count); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="bi bi-x-circle fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Ditolak</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($rejected_count); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Status Lamaran Saya</h2>
                <canvas id="myApplicationStatusChart" height="220"></canvas>

                <div class="mt-4">
                    <h3 class="h6 fw-bold mb-3">Lamaran Terbaru</h3>
                    <?php if ($my_recent_apps === []): ?>
                        <div class="text-center text-muted py-3">Belum ada lamaran.</div>
                    <?php endif; ?>

                    <?php foreach ($my_recent_apps as $application): ?>
                        <div class="d-flex justify-content-between gap-3 border-bottom py-2">
                            <div>
                                <div class="fw-semibold"><?= sanitize((string) $application['job_title']); ?></div>
                                <div class="small text-muted">
                                    <?= sanitize((string) $application['company_name']); ?> ·
                                    <?= sanitize(time_ago((string) $application['created_at'])); ?>
                                </div>
                            </div>
                            <div><?= get_status_badge((string) $application['status']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 fw-bold mb-0">Lowongan Terbaru</h2>
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>

                <div class="row g-3">
                    <?php if ($recent_jobs === []): ?>
                        <div class="col-12">
                            <div class="text-center text-muted py-4">Belum ada lowongan terbuka.</div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($recent_jobs as $job): ?>
                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                    <div>
                                        <h3 class="h6 fw-bold mb-1"><?= sanitize((string) $job['title']); ?></h3>
                                        <div class="small text-muted">
                                            <?= sanitize((string) $job['company_name']); ?> ·
                                            <?= sanitize((string) $job['category_name']); ?> ·
                                            <?= sanitize((string) $job['location']); ?>
                                        </div>
                                    </div>
                                    <div class="text-md-end">
                                        <div class="small text-muted">Deadline</div>
                                        <div class="fw-semibold"><?= format_date((string) $job['deadline']); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="badge text-bg-success">Open</span>
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/applications/apply.php?job_id=<?= (int) $job['id']; ?>" class="btn btn-sm btn-primary">
                                        Lamar
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const myApplicationStatusCtx = document.getElementById('myApplicationStatusChart');

    new Chart(myApplicationStatusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusChartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            datasets: [{
                data: <?= json_encode($statusChartData, JSON_NUMERIC_CHECK); ?>,
                backgroundColor: ['#6c757d', '#ffc107', '#198754', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
