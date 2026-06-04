<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push_notification.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/scraper/results.php');
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    set_flash('error', 'Permintaan tidak valid');
    redirect(BASE_URL . '/admin/scraper/results.php');
}

$scrapedJobId = (int) ($_POST['scraped_job_id'] ?? 0);
$categoryId = (int) ($_POST['category_id'] ?? 0);
$companyId = (int) ($_POST['company_id'] ?? 0);
$deadline = trim((string) ($_POST['deadline'] ?? ''));

if ($scrapedJobId <= 0 || $categoryId <= 0 || $companyId <= 0 || $deadline === '') {
    set_flash('error', 'Data import tidak lengkap');
    redirect(BASE_URL . '/admin/scraper/results.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM scraped_jobs WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $scrapedJobId]);
    $scrapedJob = $stmt->fetch();

    if (!$scrapedJob) {
        throw new RuntimeException('Scraped job tidak ditemukan');
    }

    $insert = $pdo->prepare(
        'INSERT INTO job_listings
            (company_id, category_id, title, description, requirements, location, quota, start_date, end_date, deadline, status)
         VALUES
            (:company_id, :category_id, :title, :description, :requirements, :location, 1, CURDATE(), :end_date, :deadline, \'open\')'
    );
    $insert->execute([
        ':company_id' => $companyId,
        ':category_id' => $categoryId,
        ':title' => substr((string) $scrapedJob['title'], 0, 150),
        ':description' => (string) ($scrapedJob['description'] ?? 'Lihat detail lowongan pada sumber eksternal: ' . $scrapedJob['source_url']),
        ':requirements' => (string) ($scrapedJob['requirements'] ?? 'Silakan cek persyaratan lengkap pada sumber eksternal.'),
        ':location' => substr((string) ($scrapedJob['location'] ?? 'Indonesia'), 0, 100),
        ':end_date' => $deadline,
        ':deadline' => $deadline,
    ]);
    $jobListingId = (int) $pdo->lastInsertId();

    $update = $pdo->prepare(
        'UPDATE scraped_jobs
         SET status = \'approved\', approved_by = :approved_by, approved_at = NOW(), job_listing_id = :job_listing_id, category_id = :category_id
         WHERE id = :id'
    );
    $update->execute([
        ':approved_by' => (int) $_SESSION['user_id'],
        ':job_listing_id' => $jobListingId,
        ':category_id' => $categoryId,
        ':id' => $scrapedJobId,
    ]);

    $pdo->commit();

    try {
        notify_role('student', 'Lowongan Baru Tersedia', 'Ada lowongan baru: ' . (string) $scrapedJob['title'], BASE_URL . '/student/jobs/index.php');
    } catch (Throwable $exception) {
        error_log('Notify students for imported scraped job failed: ' . $exception->getMessage());
    }

    set_flash('success', 'Lowongan berhasil diimport');
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Import scraped job failed: ' . $exception->getMessage());
    set_flash('error', 'Lowongan gagal diimport');
}

redirect(BASE_URL . '/admin/scraper/results.php?status=pending');
