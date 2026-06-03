<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$page_title = 'Dashboard Admin';
$total_users = 0;
$total_companies = 0;
$total_students = 0;
$total_jobs = 0;
$total_applications = 0;
$pending_companies = 0;
$applications_by_status = [
    'pending' => 0,
    'review' => 0,
    'accepted' => 0,
    'rejected' => 0,
];
$monthly_applications = [];
$recent_activities = [];

for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-{$i} months"));
    $monthly_applications[$monthKey] = [
        'label' => date('M Y', strtotime($monthKey . '-01')),
        'total' => 0,
    ];
}

try {
    $pdo = Database::getInstance()->getConnection();

    $total_users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $total_companies = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles')->fetchColumn();
    $total_students = (int) $pdo->query('SELECT COUNT(*) FROM student_profiles')->fetchColumn();
    $total_jobs = (int) $pdo->query('SELECT COUNT(*) FROM job_listings')->fetchColumn();
    $total_applications = (int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    $pending_companies = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles WHERE is_verified = 0')->fetchColumn();

    $statusStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM applications GROUP BY status');
    foreach ($statusStmt->fetchAll() as $row) {
        $status = (string) $row['status'];

        if (array_key_exists($status, $applications_by_status)) {
            $applications_by_status[$status] = (int) $row['total'];
        }
    }

    $monthlyStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
         FROM applications
         WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY month_key
         ORDER BY month_key ASC"
    );
    $monthlyStmt->execute();

    foreach ($monthlyStmt->fetchAll() as $row) {
        $monthKey = (string) $row['month_key'];

        if (isset($monthly_applications[$monthKey])) {
            $monthly_applications[$monthKey]['total'] = (int) $row['total'];
        }
    }

    $activityStmt = $pdo->query(
        'SELECT activity_logs.action, activity_logs.description, activity_logs.created_at, users.name AS user_name
         FROM activity_logs
         LEFT JOIN users ON users.id = activity_logs.user_id
         ORDER BY activity_logs.created_at DESC
         LIMIT 10'
    );
    $recent_activities = $activityStmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Admin dashboard query failed: ' . $exception->getMessage());
    set_flash('error', 'Data dashboard gagal dimuat. Silakan coba lagi nanti');
}

$monthlyLabels = array_column($monthly_applications, 'label');
$monthlyTotals = array_column($monthly_applications, 'total');
$statusLabels = ['Pending', 'Review', 'Accepted', 'Rejected'];
$statusTotals = array_values($applications_by_status);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Dashboard Admin</h1>
        <p class="text-muted mb-0">Ringkasan aktivitas dan statistik sistem CariinTern.</p>
    </div>
    <div class="text-md-end">
        <div class="small text-muted">Hari ini</div>
        <div class="fw-semibold"><?= sanitize(format_date(date('Y-m-d'), 'd M Y')); ?></div>
    </div>
</div>

<?= display_flash(); ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-mortarboard fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Mahasiswa</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($total_students); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-building fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Perusahaan</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($total_companies); ?></div>
                    <div class="small text-warning"><?= number_format($pending_companies); ?> belum terverifikasi</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-briefcase fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Lowongan</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($total_jobs); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="bi bi-file-earmark-text fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Lamaran</div>
                    <div class="h3 fw-bold mb-0"><?= number_format($total_applications); ?></div>
                    <div class="small text-muted"><?= number_format($total_users); ?> total user</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 fw-bold mb-0">Lamaran per Bulan</h2>
                    <span class="badge text-bg-light">6 bulan terakhir</span>
                </div>
                <canvas id="monthlyApplicationsChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Status Lamaran</h2>
                <canvas id="applicationStatusChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Recent Activities</h2>
                <p class="text-muted small mb-0">10 aktivitas terbaru di sistem.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_activities === []): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Belum ada aktivitas.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= sanitize((string) ($activity['user_name'] ?? 'System')); ?></div>
                                <div class="small text-muted"><?= sanitize((string) ($activity['description'] ?? '')); ?></div>
                            </td>
                            <td>
                                <span class="badge text-bg-secondary"><?= sanitize((string) $activity['action']); ?></span>
                            </td>
                            <td class="text-muted"><?= sanitize(time_ago((string) $activity['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const monthlyApplicationsCtx = document.getElementById('monthlyApplicationsChart');
    const applicationStatusCtx = document.getElementById('applicationStatusChart');

    new Chart(monthlyApplicationsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($monthlyLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            datasets: [{
                label: 'Lamaran',
                data: <?= json_encode($monthlyTotals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#0d6efd',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
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

    new Chart(applicationStatusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            datasets: [{
                data: <?= json_encode($statusTotals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                backgroundColor: ['#6c757d', '#ffc107', '#198754', '#dc3545'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '65%'
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
