<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Request tidak valid');
    redirect(BASE_URL . '/student/applications/index.php');
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
    redirect(BASE_URL . '/student/applications/index.php');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$validator = (new Validator($_POST))
    ->required('application_id', 'Lamaran')
    ->numeric('application_id', 'Lamaran');
$validationErrors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];
$applicationId = (int) ($_POST['application_id'] ?? 0);

if ($validationErrors !== [] || $applicationId <= 0) {
    set_flash('error', $validationErrors[0] ?? 'Lamaran tidak valid');
    redirect(BASE_URL . '/student/applications/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();

    $profileStmt = $pdo->prepare(
        'SELECT id
         FROM student_profiles
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $studentProfile = $profileStmt->fetch();

    if (!$studentProfile) {
        set_flash('warning', 'Profil mahasiswa tidak ditemukan');
        redirect(BASE_URL . '/student/profile.php');
    }

    $studentId = (int) $studentProfile['id'];

    $applicationStmt = $pdo->prepare(
        'SELECT applications.id, applications.status, job_listings.title
         FROM applications
         INNER JOIN job_listings ON job_listings.id = applications.job_id
         WHERE applications.id = :application_id
           AND applications.student_id = :student_id
         LIMIT 1'
    );
    $applicationStmt->execute([
        ':application_id' => $applicationId,
        ':student_id' => $studentId,
    ]);
    $application = $applicationStmt->fetch();

    if (!$application) {
        set_flash('error', 'Lamaran tidak ditemukan atau bukan milik kamu');
        redirect(BASE_URL . '/student/applications/index.php');
    }

    if ((string) $application['status'] !== 'pending') {
        set_flash('warning', 'Lamaran tidak bisa dibatalkan karena sudah diproses');
        redirect(BASE_URL . '/student/applications/index.php');
    }

    $deleteStmt = $pdo->prepare(
        'DELETE FROM applications
         WHERE id = :application_id
           AND student_id = :student_id
           AND status = \'pending\''
    );
    $deleteStmt->execute([
        ':application_id' => $applicationId,
        ':student_id' => $studentId,
    ]);

    if ($deleteStmt->rowCount() < 1) {
        set_flash('error', 'Lamaran gagal dibatalkan');
        redirect(BASE_URL . '/student/applications/index.php');
    }

    log_activity(
        $userId,
        'application_cancelled',
        'Mahasiswa membatalkan lamaran untuk ' . (string) $application['title']
    );

    set_flash('success', 'Lamaran berhasil dibatalkan');
    redirect(BASE_URL . '/student/applications/index.php');
} catch (PDOException $exception) {
    error_log('Cancel student application failed: ' . $exception->getMessage());
    set_flash('error', 'Lamaran gagal dibatalkan. Silakan coba lagi nanti');
    redirect(BASE_URL . '/student/applications/index.php');
}
