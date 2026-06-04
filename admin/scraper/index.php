<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Scraper';
$stats = ['active_sources' => 0, 'scraped_today' => 0, 'pending' => 0, 'imported' => 0];
$sources = [];

function scraper_type_badge(string $type): string
{
    $classes = [
        'website' => 'primary',
        'rss' => 'success',
        'telegram' => 'info',
        'google_cse' => 'warning text-dark',
    ];

    return '<span class="badge bg-' . ($classes[$type] ?? 'secondary') . '">' . sanitize(strtoupper($type)) . '</span>';
}

function scraper_status_badge(?string $status): string
{
    $classes = [
        'running' => 'info',
        'success' => 'success',
        'failed' => 'danger',
        'partial' => 'warning text-dark',
    ];

    if ($status === null || $status === '') {
        return '<span class="badge bg-secondary">Belum jalan</span>';
    }

    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . '">' . sanitize($status) . '</span>';
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stats['active_sources'] = (int) $pdo->query('SELECT COUNT(*) FROM scraper_sources WHERE is_active = 1')->fetchColumn();
    $stats['scraped_today'] = (int) $pdo->query('SELECT COUNT(*) FROM scraped_jobs WHERE DATE(scraped_at) = CURDATE()')->fetchColumn();
    $stats['pending'] = (int) $pdo->query('SELECT COUNT(*) FROM scraped_jobs WHERE status = \'pending\'')->fetchColumn();
    $stats['imported'] = (int) $pdo->query('SELECT COUNT(*) FROM scraped_jobs WHERE job_listing_id IS NOT NULL')->fetchColumn();
    $sources = $pdo->query(
        'SELECT scraper_sources.*,
                latest_logs.status AS last_status,
                latest_logs.items_found AS last_items_found
         FROM scraper_sources
         LEFT JOIN (
             SELECT l1.*
             FROM scraper_logs l1
             INNER JOIN (
                 SELECT source_id, MAX(id) AS max_id
                 FROM scraper_logs
                 GROUP BY source_id
             ) last_logs ON last_logs.max_id = l1.id
         ) latest_logs ON latest_logs.source_id = scraper_sources.id
         ORDER BY scraper_sources.created_at DESC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('Load scraper dashboard failed: ' . $exception->getMessage());
    set_flash('error', 'Dashboard scraper gagal dimuat');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Auto Scraper</h1>
        <p class="text-muted mb-0">Kelola sumber scraping legal dan review lowongan eksternal.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" id="runAllScrapers">
            <i class="bi bi-play-fill me-1"></i>
            Jalankan Semua Sekarang
        </button>
        <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/scraper/create_source.php" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Tambah Sumber
        </a>
    </div>
</div>

<?= display_flash(); ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="stat-card primary p-3"><div class="text-muted small">Sumber Aktif</div><div class="h3 fw-bold mb-0"><?= number_format($stats['active_sources']); ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card secondary p-3"><div class="text-muted small">Scrape Hari Ini</div><div class="h3 fw-bold mb-0"><?= number_format($stats['scraped_today']); ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card accent p-3"><div class="text-muted small">Menunggu Review</div><div class="h3 fw-bold mb-0"><?= number_format($stats['pending']); ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card success p-3"><div class="text-muted small">Sudah Diimport</div><div class="h3 fw-bold mb-0"><?= number_format($stats['imported']); ?></div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/scraper/results.php" class="btn btn-outline-secondary btn-sm">Hasil Scraping</a>
            <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/scraper/logs.php" class="btn btn-outline-secondary btn-sm">Log Scraper</a>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Interval</th>
                        <th>Last Run</th>
                        <th>Status Last Run</th>
                        <th>Items</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sources === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada sumber scraper.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= sanitize((string) $source['name']); ?></div>
                                <div class="small text-muted text-truncate" style="max-width: 360px;"><?= sanitize((string) $source['url']); ?></div>
                            </td>
                            <td><?= scraper_type_badge((string) $source['type']); ?></td>
                            <td><?= number_format((int) $source['scrape_interval_hours']); ?> jam</td>
                            <td><?= !empty($source['last_scraped_at']) ? sanitize(format_date((string) $source['last_scraped_at'], 'd M Y H:i')) : '-'; ?></td>
                            <td><?= scraper_status_badge($source['last_status'] ?? null); ?></td>
                            <td><?= number_format((int) ($source['last_items_found'] ?? 0)); ?> ditemukan</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary run-source" data-source-id="<?= (int) $source['id']; ?>">
                                    Jalankan
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    async function triggerScraper(sourceId) {
        const formData = new FormData();
        formData.append('source_id', sourceId);
        const response = await fetch('<?= rtrim(BASE_URL, '/'); ?>/api/trigger_scraper.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        alert(result.message || (result.success ? 'Scraper dijalankan' : 'Scraper gagal dijalankan'));
    }

    document.getElementById('runAllScrapers')?.addEventListener('click', () => triggerScraper('all'));
    document.querySelectorAll('.run-source').forEach((button) => {
        button.addEventListener('click', () => triggerScraper(button.dataset.sourceId));
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
