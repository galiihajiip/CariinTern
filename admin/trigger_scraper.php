<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$page_title = 'Trigger Scraper';
$sources = [];

try {
    $pdo = Database::getInstance()->getConnection();
    $sources = $pdo->query(
        'SELECT id, name, type, is_active, last_scraped_at
         FROM scraper_sources
         ORDER BY name ASC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('Load trigger scraper sources failed: ' . $exception->getMessage());
    set_flash('error', 'Sumber scraper gagal dimuat');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid');
        redirect(BASE_URL . '/admin/trigger_scraper.php');
    }

    $sourceId = trim((string) ($_POST['source_id'] ?? ''));
    $target = $sourceId === 'all' ? '--all --force' : '--source=' . (int) $sourceId;

    if ($sourceId !== 'all' && (int) $sourceId <= 0) {
        set_flash('error', 'Source tidak valid');
        redirect(BASE_URL . '/admin/trigger_scraper.php');
    }

    $command = '"' . (PHP_BINARY ?: 'php') . '" "' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scraper' . DIRECTORY_SEPARATOR . 'run_scraper.php" ' . $target;

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        pclose(popen('start /B "" ' . $command, 'r'));
    } else {
        shell_exec($command . ' > /dev/null 2>&1 &');
    }

    set_flash('success', 'Scraper dijalankan di background');
    redirect(BASE_URL . '/admin/trigger_scraper.php');
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 fw-bold mb-1">Trigger Scraper</h1>
        <p class="text-muted mb-0">Jalankan scraper manual di environment lokal.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/scraper/index.php" class="btn btn-outline-primary">Panel Scraper</a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" class="mb-4">
            <?= csrf_field(); ?>
            <input type="hidden" name="source_id" value="all">
            <button type="submit" class="btn btn-primary">Jalankan Semua Sekarang</button>
        </form>

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Last Run</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td class="fw-semibold"><?= sanitize((string) $source['name']); ?></td>
                            <td><span class="badge text-bg-secondary"><?= sanitize((string) $source['type']); ?></span></td>
                            <td><?= (int) $source['is_active'] === 1 ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>'; ?></td>
                            <td><?= !empty($source['last_scraped_at']) ? sanitize(format_date((string) $source['last_scraped_at'], 'd M Y H:i')) : '-'; ?></td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="source_id" value="<?= (int) $source['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Jalankan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
