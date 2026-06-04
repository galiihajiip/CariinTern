<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Hasil Scraping';
$allowedTabs = ['pending', 'approved', 'rejected', 'duplicate'];
$activeTab = in_array(($_GET['status'] ?? 'pending'), $allowedTabs, true) ? (string) $_GET['status'] : 'pending';
$jobs = [];
$categories = [];
$companies = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['bulk_action'] ?? '') !== '') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid');
        redirect(BASE_URL . '/admin/scraper/results.php?status=' . urlencode($activeTab));
    }

    $ids = array_map('intval', $_POST['job_ids'] ?? []);
    $action = (string) $_POST['bulk_action'];

    if ($ids !== [] && in_array($action, ['rejected', 'duplicate'], true)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('UPDATE scraped_jobs SET status = ? WHERE id IN (' . $placeholders . ')');
            $stmt->execute(array_merge([$action], $ids));
            set_flash('success', 'Bulk action berhasil diproses');
        } catch (PDOException $exception) {
            error_log('Bulk scraped jobs action failed: ' . $exception->getMessage());
            set_flash('error', 'Bulk action gagal');
        }
    }

    redirect(BASE_URL . '/admin/scraper/results.php?status=' . urlencode($activeTab));
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare(
        'SELECT scraped_jobs.*, scraper_sources.name AS source_name
         FROM scraped_jobs
         INNER JOIN scraper_sources ON scraper_sources.id = scraped_jobs.source_id
         WHERE scraped_jobs.status = :status
         ORDER BY scraped_jobs.scraped_at DESC, scraped_jobs.id DESC
         LIMIT 100'
    );
    $stmt->execute([':status' => $activeTab]);
    $jobs = $stmt->fetchAll();
    $categories = $pdo->query('SELECT id, name FROM internship_categories WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    $companies = $pdo->query('SELECT id, company_name FROM company_profiles ORDER BY company_name ASC')->fetchAll();
} catch (PDOException $exception) {
    error_log('Load scraped results failed: ' . $exception->getMessage());
    set_flash('error', 'Hasil scraping gagal dimuat');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 fw-bold mb-1">Review Hasil Scraping</h1>
        <p class="text-muted mb-0">Setujui, tolak, atau tandai duplikat lowongan eksternal.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/scraper/index.php" class="btn btn-outline-primary">Dashboard Scraper</a>
</div>

<?= display_flash(); ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($allowedTabs as $tab): ?>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === $tab ? 'active' : ''; ?>" href="?status=<?= $tab; ?>"><?= ucfirst($tab); ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="POST" class="card border-0 shadow-sm">
    <div class="card-body">
        <?= csrf_field(); ?>
        <div class="d-flex gap-2 mb-3">
            <button type="submit" name="bulk_action" value="rejected" class="btn btn-outline-danger btn-sm">Bulk Reject</button>
            <button type="submit" name="bulk_action" value="duplicate" class="btn btn-outline-secondary btn-sm">Bulk Duplikat</button>
        </div>

        <?php if ($jobs === []): ?>
            <div class="text-center text-muted py-5">Tidak ada hasil pada tab ini.</div>
        <?php endif; ?>

        <div class="row g-3">
            <?php foreach ($jobs as $job): ?>
                <div class="col-12">
                    <div class="border rounded-4 p-3">
                        <div class="d-flex gap-3 align-items-start">
                            <input class="form-check-input mt-1" type="checkbox" name="job_ids[]" value="<?= (int) $job['id']; ?>">
                            <div class="flex-grow-1">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                                    <div>
                                        <h2 class="h5 fw-bold mb-1"><?= sanitize((string) $job['title']); ?></h2>
                                        <div class="text-muted small">
                                            <?= sanitize((string) ($job['company_name'] ?? 'Perusahaan tidak diketahui')); ?> ·
                                            <?= sanitize((string) $job['source_name']); ?> ·
                                            <?= sanitize(format_date((string) $job['scraped_at'], 'd M Y H:i')); ?>
                                        </div>
                                    </div>
                                    <a href="<?= sanitize((string) $job['source_url']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Buka Sumber</a>
                                </div>
                                <p class="text-muted mt-3 mb-3"><?= sanitize(truncate_text((string) ($job['description'] ?? ''), 220)); ?></p>

                                <?php if ($activeTab === 'pending'): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importModal<?= (int) $job['id']; ?>">Setujui & Import</button>
                                        <button type="submit" name="bulk_action" value="rejected" class="btn btn-sm btn-outline-danger" onclick="this.form.querySelectorAll('input[name=&quot;job_ids[]&quot;]').forEach(cb => cb.checked = false); this.closest('.border').querySelector('input[type=checkbox]').checked = true;">Tolak</button>
                                        <button type="submit" name="bulk_action" value="duplicate" class="btn btn-sm btn-outline-secondary" onclick="this.form.querySelectorAll('input[name=&quot;job_ids[]&quot;]').forEach(cb => cb.checked = false); this.closest('.border').querySelector('input[type=checkbox]').checked = true;">Tandai Duplikat</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<?php foreach ($jobs as $job): ?>
    <div class="modal fade" id="importModal<?= (int) $job['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="import_job.php">
                <div class="modal-header">
                    <h5 class="modal-title">Import Lowongan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="scraped_job_id" value="<?= (int) $job['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id']; ?>" <?= (int) ($job['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>><?= sanitize((string) $category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Perusahaan</label>
                        <select class="form-select" name="company_id" required>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= (int) $company['id']; ?>"><?= sanitize((string) $company['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deadline</label>
                        <input type="date" class="form-control" name="deadline" value="<?= sanitize((string) ($job['deadline'] ?? date('Y-m-d', strtotime('+30 days')))); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
