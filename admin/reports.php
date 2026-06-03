<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

function admin_reports_export_csv(string $filename, array $headers, callable $writer): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    if ($output === false) {
        exit;
    }

    fputcsv($output, $headers);
    $writer($output);
    fclose($output);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$export = trim((string) ($_GET['export'] ?? ''));
$exportType = trim((string) ($_GET['type'] ?? ''));

if ($export === 'csv' && $exportType === 'applications') {
    $stmt = $pdo->query(
        'SELECT applications.id,
                applications.status,
                applications.created_at,
                applications.reviewed_at,
                applications.notes,
                student_profiles.full_name AS student_name,
                student_profiles.student_id AS nim,
                study_programs.name AS program_name,
                job_listings.title AS job_title,
                company_profiles.company_name
         FROM applications
         INNER JOIN student_profiles ON student_profiles.id = applications.student_id
         INNER JOIN study_programs ON study_programs.id = student_profiles.program_id
         INNER JOIN job_listings ON job_listings.id = applications.job_id
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         ORDER BY applications.created_at DESC'
    );

    admin_reports_export_csv(
        'laporan_lamaran_' . date('Ymd') . '.csv',
        ['ID', 'Mahasiswa', 'NIM', 'Program Studi', 'Perusahaan', 'Lowongan', 'Status', 'Tanggal Lamaran', 'Tanggal Review', 'Catatan'],
        static function ($output) use ($stmt): void {
            while ($row = $stmt->fetch()) {
                fputcsv($output, [
                    $row['id'],
                    $row['student_name'],
                    $row['nim'],
                    $row['program_name'],
                    $row['company_name'],
                    $row['job_title'],
                    $row['status'],
                    $row['created_at'],
                    $row['reviewed_at'],
                    $row['notes'],
                ]);
            }
        }
    );
}

if ($export === 'csv' && $exportType === 'students') {
    $stmt = $pdo->query(
        'SELECT student_profiles.id,
                student_profiles.full_name,
                student_profiles.student_id,
                users.email,
                student_profiles.phone,
                study_programs.name AS program_name,
                student_profiles.semester,
                student_profiles.gpa,
                student_profiles.profile_completed,
                student_profiles.created_at
         FROM student_profiles
         INNER JOIN users ON users.id = student_profiles.user_id
         INNER JOIN study_programs ON study_programs.id = student_profiles.program_id
         ORDER BY student_profiles.created_at DESC'
    );

    admin_reports_export_csv(
        'laporan_mahasiswa_' . date('Ymd') . '.csv',
        ['ID', 'Nama', 'NIM', 'Email', 'No. HP', 'Program Studi', 'Semester', 'IPK', 'Profil Lengkap', 'Tanggal Daftar'],
        static function ($output) use ($stmt): void {
            while ($row = $stmt->fetch()) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['student_id'],
                    $row['email'],
                    $row['phone'],
                    $row['program_name'],
                    $row['semester'],
                    $row['gpa'],
                    ((int) $row['profile_completed'] === 1 ? 'Ya' : 'Tidak'),
                    $row['created_at'],
                ]);
            }
        }
    );
}

$page_title = 'Laporan & Analitik';
$monthlyApplications = [];
$topJobs = [];
$programDistribution = [];
$companyStats = [];
$companyTopJobs = [];

for ($i = 11; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-{$i} months"));
    $monthlyApplications[$monthKey] = [
        'label' => date('M Y', strtotime($monthKey . '-01')),
        'total' => 0,
    ];
}

try {
    $monthlyStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
         FROM applications
         WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
         GROUP BY month_key
         ORDER BY month_key ASC"
    );
    $monthlyStmt->execute();

    foreach ($monthlyStmt->fetchAll() as $row) {
        $monthKey = (string) $row['month_key'];

        if (isset($monthlyApplications[$monthKey])) {
            $monthlyApplications[$monthKey]['total'] = (int) $row['total'];
        }
    }

    $topJobsStmt = $pdo->query(
        'SELECT job_listings.title,
                company_profiles.company_name,
                COUNT(applications.id) AS total_applicants
         FROM applications
         INNER JOIN job_listings ON job_listings.id = applications.job_id
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         GROUP BY job_listings.id, job_listings.title, company_profiles.company_name
         ORDER BY total_applicants DESC
         LIMIT 5'
    );
    $topJobs = $topJobsStmt->fetchAll();

    $programStmt = $pdo->query(
        'SELECT study_programs.name, COUNT(applications.id) AS total_applicants
         FROM applications
         INNER JOIN student_profiles ON student_profiles.id = applications.student_id
         INNER JOIN study_programs ON study_programs.id = student_profiles.program_id
         GROUP BY study_programs.id, study_programs.name
         ORDER BY total_applicants DESC'
    );
    $programDistribution = $programStmt->fetchAll();

    $companyStatsStmt = $pdo->query(
        'SELECT company_profiles.company_name,
                users.email,
                COUNT(DISTINCT job_listings.id) AS total_jobs,
                COUNT(applications.id) AS total_applications,
                SUM(CASE WHEN applications.status = \'accepted\' THEN 1 ELSE 0 END) AS accepted_applications,
                ROUND(
                    IF(COUNT(applications.id) = 0, 0, SUM(CASE WHEN applications.status = \'accepted\' THEN 1 ELSE 0 END) / COUNT(applications.id) * 100),
                    2
                ) AS acceptance_rate
         FROM company_profiles
         INNER JOIN users ON users.id = company_profiles.user_id
         LEFT JOIN job_listings ON job_listings.company_id = company_profiles.id
         LEFT JOIN applications ON applications.job_id = job_listings.id
         GROUP BY company_profiles.id, company_profiles.company_name, users.email
         ORDER BY total_jobs DESC, total_applications DESC'
    );
    $companyStats = $companyStatsStmt->fetchAll();
    $companyTopJobs = array_slice($companyStats, 0, 5);
} catch (PDOException $exception) {
    error_log('Admin reports query failed: ' . $exception->getMessage());
    set_flash('error', 'Data laporan gagal dimuat. Silakan coba lagi nanti');
}

$monthlyLabels = array_column($monthlyApplications, 'label');
$monthlyTotals = array_column($monthlyApplications, 'total');
$topJobLabels = array_map(static fn ($row) => (string) $row['title'], $topJobs);
$topJobTotals = array_map(static fn ($row) => (int) $row['total_applicants'], $topJobs);
$programLabels = array_map(static fn ($row) => (string) $row['name'], $programDistribution);
$programTotals = array_map(static fn ($row) => (int) $row['total_applicants'], $programDistribution);
$companyJobLabels = array_map(static fn ($row) => (string) $row['company_name'], $companyTopJobs);
$companyJobTotals = array_map(static fn ($row) => (int) $row['total_jobs'], $companyTopJobs);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Laporan & Analitik</h1>
        <p class="text-muted mb-0">Pantau tren lamaran, performa lowongan, dan statistik perusahaan.</p>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2">
        <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/reports.php?export=csv&type=applications" class="btn btn-primary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<?= display_flash(); ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 fw-bold mb-0">Lamaran per Bulan</h2>
            </div>
            <div class="card-body">
                <canvas id="monthlyApplicationsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 fw-bold mb-0">Distribusi Program Studi</h2>
            </div>
            <div class="card-body">
                <canvas id="programDistributionChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 fw-bold mb-0">Top 5 Lowongan</h2>
            </div>
            <div class="card-body">
                <canvas id="topJobsChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 fw-bold mb-0">Perusahaan dengan Lowongan Terbanyak</h2>
            </div>
            <div class="card-body">
                <canvas id="companyJobsChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <h2 class="h5 fw-bold mb-1">Ringkasan Statistik Perusahaan</h2>
            <p class="text-muted small mb-0">Termasuk jumlah lowongan, total lamaran, dan acceptance rate.</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/reports.php?export=csv&type=applications" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Semua Lamaran (CSV)
            </a>
            <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/reports.php?export=csv&type=students" class="btn btn-outline-success btn-sm">
                <i class="bi bi-mortarboard me-1"></i>Export Data Mahasiswa (CSV)
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Perusahaan</th>
                        <th>Email</th>
                        <th class="text-center">Lowongan</th>
                        <th class="text-center">Lamaran</th>
                        <th class="text-center">Diterima</th>
                        <th class="text-center">Acceptance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($companyStats === []): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada data perusahaan.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($companyStats as $company): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize((string) $company['company_name']); ?></td>
                            <td><?= sanitize((string) $company['email']); ?></td>
                            <td class="text-center"><?= number_format((int) $company['total_jobs']); ?></td>
                            <td class="text-center"><?= number_format((int) $company['total_applications']); ?></td>
                            <td class="text-center"><?= number_format((int) $company['accepted_applications']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary">
                                    <?= number_format((float) $company['acceptance_rate'], 2); ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const monthlyLabels = <?= json_encode($monthlyLabels); ?>;
    const monthlyTotals = <?= json_encode($monthlyTotals); ?>;
    const topJobLabels = <?= json_encode($topJobLabels); ?>;
    const topJobTotals = <?= json_encode($topJobTotals); ?>;
    const programLabels = <?= json_encode($programLabels); ?>;
    const programTotals = <?= json_encode($programTotals); ?>;
    const companyJobLabels = <?= json_encode($companyJobLabels); ?>;
    const companyJobTotals = <?= json_encode($companyJobTotals); ?>;

    new Chart(document.getElementById('monthlyApplicationsChart'), {
        type: 'line',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Lamaran',
                data: monthlyTotals,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    new Chart(document.getElementById('topJobsChart'), {
        type: 'bar',
        data: {
            labels: topJobLabels,
            datasets: [{
                label: 'Pelamar',
                data: topJobTotals,
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    new Chart(document.getElementById('programDistributionChart'), {
        type: 'pie',
        data: {
            labels: programLabels,
            datasets: [{
                data: programTotals,
                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#fd7e14']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('companyJobsChart'), {
        type: 'bar',
        data: {
            labels: companyJobLabels,
            datasets: [{
                label: 'Lowongan',
                data: companyJobTotals,
                backgroundColor: '#198754'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
