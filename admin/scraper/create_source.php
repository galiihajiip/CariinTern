<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../scraper/HttpClient.php';

require_role('admin');

$page_title = 'Tambah Sumber Scraper';
$allowedTypes = ['website', 'rss', 'telegram', 'google_cse'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    header('Content-Type: application/json; charset=utf-8');
    $url = trim((string) ($_POST['url'] ?? ''));
    $type = trim((string) ($_POST['type'] ?? ''));

    if ($url === '' || !in_array($type, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'URL dan tipe wajib diisi']);
        exit;
    }

    $client = new HttpClient();
    $body = $type === 'google_cse' ? null : $client->get($url, [], 15);
    echo json_encode([
        'success' => $body !== null || $type === 'google_cse',
        'message' => $body !== null || $type === 'google_cse' ? 'Source dapat diakses. Simpan sumber untuk test scrape penuh.' : 'Source gagal diakses.',
        'preview' => $body !== null ? array_slice(preg_split('/\s+/', strip_tags($body)) ?: [], 0, 40) : [],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid');
        redirect(BASE_URL . '/admin/scraper/create_source.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $type = trim((string) ($_POST['type'] ?? ''));
    $url = trim((string) ($_POST['url'] ?? ''));
    $interval = (int) ($_POST['scrape_interval_hours'] ?? 6);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $config = [];
    $selectors = null;

    if ($type === 'website') {
        $selectors = [
            'job_container' => trim((string) ($_POST['job_container'] ?? '')),
            'title' => trim((string) ($_POST['selector_title'] ?? '')),
            'company' => trim((string) ($_POST['selector_company'] ?? '')),
            'url' => trim((string) ($_POST['selector_url'] ?? '')),
            'location' => trim((string) ($_POST['selector_location'] ?? '')),
            'deadline' => trim((string) ($_POST['selector_deadline'] ?? '')),
        ];
        $config = ['requires_js' => false, 'max_pages' => 1];
    } elseif ($type === 'telegram') {
        $config = [
            'method' => trim((string) ($_POST['telegram_method'] ?? 'web_scrape')),
            'bot_token' => trim((string) ($_POST['bot_token'] ?? '')),
            'channel_username' => trim((string) ($_POST['channel_username'] ?? '')),
            'keyword_filter' => ['magang', 'internship', 'deadline'],
        ];
    } elseif ($type === 'google_cse') {
        $config = [
            'api_key' => trim((string) ($_POST['api_key'] ?? '')),
            'cx' => trim((string) ($_POST['cx'] ?? '')),
            'query' => trim((string) ($_POST['query'] ?? 'lowongan magang mahasiswa Indonesia')),
            'date_restrict' => 'd7',
        ];
    } elseif ($type === 'rss') {
        $config = ['keyword_filter' => ['magang', 'internship', 'praktek kerja']];
    }

    if ($name === '' || $url === '' || !in_array($type, $allowedTypes, true)) {
        set_flash('error', 'Nama, tipe, dan URL wajib diisi');
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO scraper_sources (name, type, url, config, css_selectors, is_active, scrape_interval_hours, created_by)
                 VALUES (:name, :type, :url, :config, :css_selectors, :is_active, :interval, :created_by)'
            );
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':url' => $url,
                ':config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                ':css_selectors' => $selectors !== null ? json_encode($selectors, JSON_UNESCAPED_UNICODE) : null,
                ':is_active' => $isActive,
                ':interval' => $interval,
                ':created_by' => (int) $_SESSION['user_id'],
            ]);
            set_flash('success', 'Sumber scraper berhasil ditambahkan');
            redirect(BASE_URL . '/admin/scraper/index.php');
        } catch (PDOException $exception) {
            error_log('Create scraper source failed: ' . $exception->getMessage());
            set_flash('error', 'Sumber scraper gagal disimpan');
        }
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header">
    <h1 class="h3 fw-bold mb-1">Tambah Sumber Scraper</h1>
    <p class="text-muted mb-0">Tambahkan sumber publik legal untuk mengumpulkan info magang.</p>
</div>

<?= display_flash(); ?>

<form class="card border-0 shadow-sm" method="POST" id="sourceForm">
    <div class="card-body">
        <?= csrf_field(); ?>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="name">Nama</label>
                <input class="form-control" id="name" name="name" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="type">Tipe</label>
                <select class="form-select" id="type" name="type" required>
                    <option value="website">Website</option>
                    <option value="rss">RSS</option>
                    <option value="telegram">Telegram</option>
                    <option value="google_cse">Google CSE</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="scrape_interval_hours">Interval</label>
                <select class="form-select" id="scrape_interval_hours" name="scrape_interval_hours">
                    <?php foreach ([2, 4, 6, 12, 24] as $hour): ?>
                        <option value="<?= $hour; ?>" <?= $hour === 6 ? 'selected' : ''; ?>><?= $hour; ?> jam</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="url">URL / Channel</label>
                <input class="form-control" id="url" name="url" required>
            </div>
        </div>

        <div class="mt-4 source-section" data-section="website">
            <h2 class="h5 fw-bold">CSS Selectors</h2>
            <div class="row g-3">
                <div class="col-md-4"><input class="form-control" name="job_container" placeholder="job_container"></div>
                <div class="col-md-4"><input class="form-control" name="selector_title" placeholder="title"></div>
                <div class="col-md-4"><input class="form-control" name="selector_company" placeholder="company"></div>
                <div class="col-md-4"><input class="form-control" name="selector_url" placeholder="url"></div>
                <div class="col-md-4"><input class="form-control" name="selector_location" placeholder="location"></div>
                <div class="col-md-4"><input class="form-control" name="selector_deadline" placeholder="deadline"></div>
            </div>
        </div>

        <div class="mt-4 source-section d-none" data-section="telegram">
            <h2 class="h5 fw-bold">Telegram Config</h2>
            <div class="row g-3">
                <div class="col-md-4"><input class="form-control" name="telegram_method" value="web_scrape" placeholder="method"></div>
                <div class="col-md-4"><input class="form-control" name="bot_token" placeholder="Bot Token"></div>
                <div class="col-md-4"><input class="form-control" name="channel_username" placeholder="@channel"></div>
            </div>
        </div>

        <div class="mt-4 source-section d-none" data-section="google_cse">
            <h2 class="h5 fw-bold">Google CSE Config</h2>
            <div class="row g-3">
                <div class="col-md-4"><input class="form-control" name="api_key" placeholder="API Key"></div>
                <div class="col-md-4"><input class="form-control" name="cx" placeholder="Search Engine ID"></div>
                <div class="col-md-4"><input class="form-control" name="query" placeholder="Search Query"></div>
            </div>
        </div>

        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" checked>
            <label class="form-check-label" for="isActive">Aktif</label>
        </div>

        <div id="testResult" class="alert alert-info mt-4 d-none"></div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-primary" id="testScrape">Test Scrape</button>
        <button type="submit" class="btn btn-primary">Simpan Sumber</button>
    </div>
</form>

<script>
    const typeInput = document.getElementById('type');
    const testResult = document.getElementById('testResult');

    function updateSections() {
        document.querySelectorAll('.source-section').forEach((section) => {
            section.classList.toggle('d-none', section.dataset.section !== typeInput.value);
        });
    }

    typeInput.addEventListener('change', updateSections);
    updateSections();

    document.getElementById('testScrape').addEventListener('click', async () => {
        const formData = new FormData(document.getElementById('sourceForm'));
        formData.append('action', 'test');
        const response = await fetch('create_source.php', { method: 'POST', body: formData });
        const result = await response.json();
        testResult.className = `alert mt-4 alert-${result.success ? 'success' : 'danger'}`;
        testResult.textContent = result.message;
        testResult.classList.remove('d-none');
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
