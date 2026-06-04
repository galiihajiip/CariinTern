<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Log Scraper';
$sourceFilter = (int) ($_GET['source_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($currentPage - 1) * $perPage;
$sources = [];
$logs = [];
$totalLogs = 0;
$summary = ['runs_today' => 0, 'success_rate' => 0, 'avg_items' => 0];

function scraper_log_status_badge(string $status): string
{
    $classes = [
        'running' => 'info',
        'success' => 'success',
        'failed' => 'danger',
        'partial' => 'warning text-dark',
    ];
    $pulse = $status === 'running' ? ' scraper-running-pulse' : '';

    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . $pulse . '">' . sanitize($status) . '</span>';
}

function scraper_log_duration(?string $start, ?string $finish): string
{
    if (!$start) {
        return '-';
    }

    $end = $finish ? strtotime($finish) : time();
    $duration = max(0, $end - strtotime($start));

    return $duration . ' detik';
}

try {
    $pdo = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old') {
        if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
            set_flash('error', 'Permintaan tidak valid');
        } else {
            $pdo->exec('DELETE FROM scraper_logs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
            set_flash('success', 'Log lama berhasil dihapus');
            redirect(BASE_URL . '/admin/scraper/logs.php');
        }
    }

    $sources = $pdo->query('SELECT id, name FROM scraper_sources ORDER BY name ASC')->fetchAll();
    $summary['runs_today'] = (int) $pdo->query('SELECT COUNT(*) FROM scraper_logs WHERE DATE(started_at) = CURDATE()')->fetchColumn();
    $successRuns = (int) $pdo->query('SELECT COUNT(*) FROM scraper_logs WHERE DATE(started_at) = CURDATE() AND status = \'success\'')->fetchColumn();
    $summary['success_rate'] = $summary['runs_today'] > 0 ? round(($successRuns / $summary['runs_today']) * 100) : 0;
    $summary['avg_items'] = (float) $pdo->query('SELECT COALESCE(AVG(items_found), 0) FROM scraper_logs WHERE DATE(started_at) = CURDATE()')->fetchColumn();

    $conditions = [];
    $params = [];

    if ($sourceFilter > 0) {
        $conditions[] = 'scraper_logs.source_id = :source_id';
        $params[':source_id'] = $sourceFilter;
    }

    if ($statusFilter !== '') {
        $conditions[] = 'scraper_logs.status = :status';
        $params[':status'] = $statusFilter;
    }

    if ($dateFrom !== '') {
        $conditions[] = 'DATE(scraper_logs.started_at) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = 'DATE(scraper_logs.started_at) <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $whereSql = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM scraper_logs' . $whereSql);
    $countStmt->execute($params);
    $totalLogs = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT scraper_logs.*, scraper_sources.name AS source_name
         FROM scraper_logs
         INNER JOIN scraper_sources ON scraper_sources.id = scraper_logs.source_id
         ' . $whereSql . '
         ORDER BY scraper_logs.started_at DESC
         LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load scraper logs failed: ' . $exception->getMessage());
    set_flash('error', 'Log scraper gagal dimuat');
}

$totalPages = max(1, (int) ceil($totalLogs / $perPage));

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<style>
    .scraper-running-pulse {
        animation: scraperPulse 1s infinite;
    }
    @keyframes scraperPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 fw-bold mb-1">Log Scraper</h1>
        <p class="text-muted mb-0">Monitoring eksekusi scraper dan error.</p>
    </div>
    <form method="POST">
        <?= csrf_field(); ?>
        <input type="hidden" name="action" value="clear_old">
        <button type="submit" class="btn btn-outline-danger">Clear Old Logs</button>
    </form>
</div>

<?= display_flash(); ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card primary p-3"><div class="text-muted small">Run Hari Ini</div><div class="h3 fw-bold mb-0"><?= number_format($summary['runs_today']); ?></div></div></div>
    <div class="col-md-4"><div class="stat-card success p-3"><div class="text-muted small">Success Rate</div><div class="h3 fw-bold mb-0"><?= number_format($summary['success_rate']); ?>%</div></div></div>
    <div class="col-md-4"><div class="stat-card secondary p-3"><div class="text-muted small">Rata-rata Items</div><div class="h3 fw-bold mb-0"><?= number_format($summary['avg_items'], 1); ?></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label">Source</label>
                <select class="form-select" name="source_id">
                    <option value="">Semua</option>
                    <?php foreach ($sources as $source): ?>
                        <option value="<?= (int) $source['id']; ?>" <?= $sourceFilter === (int) $source['id'] ? 'selected' : ''; ?>><?= sanitize((string) $source['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua</option>
                    <?php foreach (['running', 'success', 'failed', 'partial'] as $status): ?>
                        <option value="<?= $status; ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Dari</label><input type="date" class="form-control" name="date_from" value="<?= sanitize($dateFrom); ?>"></div>
            <div class="col-md-2"><label class="form-label">Sampai</label><input type="date" class="form-control" name="date_to" value="<?= sanitize($dateTo); ?>"></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="logs.php" class="btn btn-outline-secondary">Reset</a></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>No</th><th>Sumber</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Status</th><th>Ditemukan</th><th>Baru</th><th>Duplikat</th><th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $index => $log): ?>
                    <tr>
                        <td><?= number_format($offset + $index + 1); ?></td>
                        <td><?= sanitize((string) $log['source_name']); ?></td>
                        <td><?= sanitize(format_date((string) $log['started_at'], 'd M Y H:i:s')); ?></td>
                        <td><?= !empty($log['finished_at']) ? sanitize(format_date((string) $log['finished_at'], 'd M Y H:i:s')) : '-'; ?></td>
                        <td><?= scraper_log_duration((string) $log['started_at'], $log['finished_at'] ?? null); ?></td>
                        <td><?= scraper_log_status_badge((string) $log['status']); ?></td>
                        <td><?= number_format((int) $log['items_found']); ?></td>
                        <td><?= number_format((int) $log['items_new']); ?></td>
                        <td><?= number_format((int) $log['items_duplicate']); ?></td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#error<?= (int) $log['id']; ?>">Lihat Error</button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($log['error_message'])): ?>
                        <tr class="collapse" id="error<?= (int) $log['id']; ?>"><td colspan="10"><pre class="mb-0 small text-danger"><?= sanitize((string) $log['error_message']); ?></pre></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav><ul class="pagination justify-content-end">
                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <li class="page-item <?= $page === $currentPage ? 'active' : ''; ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page])); ?>"><?= $page; ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
