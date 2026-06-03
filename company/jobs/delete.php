<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('company');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Metode request tidak valid');
    redirect(BASE_URL . '/company/jobs/index.php');
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
    redirect(BASE_URL . '/company/jobs/index.php');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$validator = (new Validator($_POST))
    ->required('job_id', 'Lowongan')
    ->numeric('job_id', 'Lowongan');
$validationErrors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];
$jobId = (int) ($_POST['job_id'] ?? 0);

if ($validationErrors !== [] || $jobId <= 0) {
    set_flash('error', $validationErrors[0] ?? 'Lowongan tidak valid');
    redirect(BASE_URL . '/company/jobs/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $profileStmt = $pdo->prepare(
        'SELECT id, is_verified
         FROM company_profiles
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $companyProfile = $profileStmt->fetch();

    if (!$companyProfile) {
        set_flash('error', 'Profil perusahaan tidak ditemukan');
        redirect(BASE_URL . '/company/jobs/index.php');
    }

    $companyId = (int) $companyProfile['id'];
    $_SESSION['company_verified'] = (int) $companyProfile['is_verified'] === 1;

    $jobStmt = $pdo->prepare(
        'SELECT id, title
         FROM job_listings
         WHERE id = :job_id AND company_id = :company_id
         LIMIT 1'
    );
    $jobStmt->execute([
        ':job_id' => $jobId,
        ':company_id' => $companyId,
    ]);
    $job = $jobStmt->fetch();

    if (!$job) {
        set_flash('error', 'Lowongan tidak ditemukan atau bukan milik perusahaan Anda');
        redirect(BASE_URL . '/company/jobs/index.php');
    }

    $acceptedStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM applications
         WHERE job_id = :job_id AND status = \'accepted\''
    );
    $acceptedStmt->execute([':job_id' => $jobId]);

    if ((int) $acceptedStmt->fetchColumn() > 0) {
        set_flash('error', 'Lowongan tidak dapat dihapus karena sudah memiliki lamaran yang diterima');
        redirect(BASE_URL . '/company/jobs/index.php');
    }

    $pdo->beginTransaction();

    $deleteApplicationsStmt = $pdo->prepare('DELETE FROM applications WHERE job_id = :job_id');
    $deleteApplicationsStmt->execute([':job_id' => $jobId]);

    $deleteJobStmt = $pdo->prepare('DELETE FROM job_listings WHERE id = :job_id AND company_id = :company_id');
    $deleteJobStmt->execute([
        ':job_id' => $jobId,
        ':company_id' => $companyId,
    ]);

    if ($deleteJobStmt->rowCount() === 0) {
        $pdo->rollBack();
        set_flash('error', 'Lowongan gagal dihapus');
        redirect(BASE_URL . '/company/jobs/index.php');
    }

    $pdo->commit();

    log_activity($userId, 'delete_job_listing', 'Perusahaan menghapus lowongan: ' . (string) $job['title']);
    set_flash('success', 'Lowongan berhasil dihapus');
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete company job failed: ' . $exception->getMessage());
    set_flash('error', 'Lowongan gagal dihapus. Silakan coba lagi nanti');
}

redirect(BASE_URL . '/company/jobs/index.php');
