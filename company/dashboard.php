<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

require_role('company');

$page_title = 'Dashboard Perusahaan';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$companyProfile = null;
$companyId = 0;
$isVerified = false;
$total_jobs = 0;
$active_jobs = 0;
$total_applicants = 0;
$pending_applicants = 0;
$accepted_applicants = 0;
$applicants_per_job = [];
$recent_applicants = [];

try {
    $pdo = Database::getInstance()->getConnection();
    $profileStmt = $pdo->prepare(
        'SELECT id, company_name, industry, is_verified
         FROM company_profiles
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $companyProfile = $profileStmt->fetch();

    if ($companyProfile) {
        $companyId = (int) $companyProfile['id'];
        $isVerified = (int) $companyProfile['is_verified'] === 1;
    }

    $_SESSION['company_verified'] = $isVerified;

    if ($companyId > 0) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM job_listings WHERE company_id = :company_id');
        $countStmt->execute([':company_id' => $companyId]);
        $total_jobs = (int) $countStmt->fetchColumn();

        $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM job_listings WHERE company_id = :company_id AND status = 'open'");
        $activeStmt->execute([':company_id' => $companyId]);
        $active_jobs = (int) $activeStmt->fetchColumn();

        $applicantStmt = $pdo->prepare(
            'SELECT
                COUNT(applications.id) AS total_applicants,
                SUM(CASE WHEN applications.status = \'pending\' THEN 1 ELSE 0 END) AS pending_applicants,
                SUM(CASE WHEN applications.status = \'accepted\' THEN 1 ELSE 0 END) AS accepted_applicants
             FROM applications
             INNER JOIN job_listings ON job_listings.id = applications.job_id
             WHERE job_listings.company_id = :company_id'
        );
        $applicantStmt->execute([':company_id' => $companyId]);
        $applicantStats = $applicantStmt->fetch() ?: [];
        $total_applicants = (int) ($applicantStats['total_applicants'] ?? 0);
        $pending_applicants = (int) ($applicantStats['pending_applicants'] ?? 0);
        $accepted_applicants = (int) ($applicantStats['accepted_applicants'] ?? 0);

        $chartStmt = $pdo->prepare(
            'SELECT job_listings.id AS job_id, job_listings.title, COUNT(applications.id) AS total
             FROM job_listings
             LEFT JOIN applications ON applications.job_id = job_listings.id
             WHERE job_listings.company_id = :company_id
             GROUP BY job_listings.id, job_listings.title
             ORDER BY total DESC, job_listings.created_at DESC
             LIMIT 10'
        );
        $chartStmt->execute([':company_id' => $companyId]);
        $applicants_per_job = $chartStmt->fetchAll();

        $recentStmt = $pdo->prepare(
            'SELECT applications.created_at, applications.status,
                    student_profiles.full_name, study_programs.name AS program_name,
                    job_listings.title AS job_title
             FROM applications
             INNER JOIN job_listings ON job_listings.id = applications.job_id
             INNER JOIN student_profiles ON student_profiles.id = applications.student_id
             LEFT JOIN study_programs ON study_programs.id = student_profiles.program_id
             WHERE job_listings.company_id = :company_id
             ORDER BY applications.created_at DESC
             LIMIT 5'
        );
        $recentStmt->execute([':company_id' => $companyId]);
        $recent_applicants = $recentStmt->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('Company dashboard query failed: ' . $exception->getMessage());
    set_flash('error', 'Data dashboard gagal dimuat. Silakan coba lagi nanti');
}

$chartLabels = array_map(static fn (array $row): string => (string) $row['title'], $applicants_per_job);
$chartTotals = array_map(static fn (array $row): int => (int) $row['total'], $applicants_per_job);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_company.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Dashboard Perusahaan</h1>
        <p class="text-muted mb-0">
            <?= $companyProfile ? sanitize((string) $companyProfile['company_name']) : 'Lengkapi profil perusahaan untuk mulai mengelola lowongan.'; ?>
        </p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/company/profile.php" class="btn btn-outline-primary">
        <i class="bi bi-building me-1"></i>
        Profil Perusahaan
    </a>
</div>

<?= display_flash(); ?>

<?php if (!$companyProfile): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <div class="fw-semibold">Profil perusahaan belum lengkap.</div>
            <div>Silakan lengkapi profil perusahaan agar admin dapat melakukan verifikasi.</div>
        </div>
    </div>
<?php elseif (!$isVerified): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <div class="fw-semibold">Akun belum terverifikasi admin.</div>
            <div>Beberapa fitur dapat dibatasi sampai admin menyetujui profil perusahaan Anda.</div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-briefcase fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Lowongan Aktif</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($active_jobs); ?></div>
                    <div class="small text-muted"><?= number_format($total_jobs); ?> total lowongan</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-people fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Pelamar</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($total_applicants); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-hourglass-split fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Menunggu Review</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($pending_applicants); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-check-circle fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Diterima</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($accepted_applicants); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 fw-bold mb-0">Pelamar per Lowongan</h2>
                    <span class="badge text-bg-light">Top 10</span>
                </div>
                <canvas id="applicantsPerJobChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Pelamar Terbaru</h2>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Lowongan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_applicants === []): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Belum ada pelamar.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($recent_applicants as $applicant): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= sanitize((string) $applicant['full_name']); ?></div>
                                        <div class="small text-muted"><?= sanitize((string) ($applicant['program_name'] ?? '-')); ?></div>
                                    </td>
                                    <td>
                                        <div><?= sanitize((string) $applicant['job_title']); ?></div>
                                        <div class="small text-muted"><?= sanitize(time_ago((string) $applicant['created_at'])); ?></div>
                                    </td>
                                    <td><?= get_status_badge((string) $applicant['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const applicantsPerJobCtx = document.getElementById('applicantsPerJobChart');

    new Chart(applicantsPerJobCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            datasets: [{
                label: 'Pelamar',
                data: <?= json_encode($chartTotals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.72)',
                borderColor: '#0d6efd',
                borderWidth: 1,
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
