<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function applicant_status_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function applicant_status_label(string $status): string
{
    $labels = [
        'review' => 'Review',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function applicant_status_badge(string $status): string
{
    $classes = [
        'review' => 'warning text-dark',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];

    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . '">' . sanitize(applicant_status_label($status)) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    applicant_status_json([
        'success' => false,
        'message' => 'Method tidak diizinkan',
    ], 405);
}

if (!isset($_SESSION['user_id'])) {
    applicant_status_json([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu',
    ], 401);
}

if (($_SESSION['user_role'] ?? '') !== 'company') {
    applicant_status_json([
        'success' => false,
        'message' => 'Akses ditolak',
    ], 403);
}

$allowedStatuses = ['review', 'accepted', 'rejected'];
$userId = (int) ($_SESSION['user_id'] ?? 0);
$applicationId = (int) ($_POST['application_id'] ?? 0);
$newStatus = trim((string) ($_POST['new_status'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($applicationId <= 0) {
    applicant_status_json([
        'success' => false,
        'message' => 'Lamaran tidak valid',
    ], 422);
}

if (!in_array($newStatus, $allowedStatuses, true)) {
    applicant_status_json([
        'success' => false,
        'message' => 'Status tidak valid',
    ], 422);
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
        applicant_status_json([
            'success' => false,
            'message' => 'Profil perusahaan tidak ditemukan',
        ], 404);
    }

    $companyId = (int) $companyProfile['id'];
    $_SESSION['company_verified'] = (int) $companyProfile['is_verified'] === 1;

    $applicationStmt = $pdo->prepare(
        'SELECT applications.id, applications.status, applications.job_id,
                student_profiles.full_name,
                job_listings.title AS job_title, job_listings.quota,
                job_listings.company_id
         FROM applications
         INNER JOIN student_profiles ON student_profiles.id = applications.student_id
         INNER JOIN job_listings ON job_listings.id = applications.job_id
         WHERE applications.id = :application_id AND job_listings.company_id = :company_id
         LIMIT 1'
    );
    $applicationStmt->execute([
        ':application_id' => $applicationId,
        ':company_id' => $companyId,
    ]);
    $application = $applicationStmt->fetch();

    if (!$application) {
        applicant_status_json([
            'success' => false,
            'message' => 'Lamaran tidak ditemukan atau bukan milik perusahaan Anda',
        ], 404);
    }

    if ($newStatus === 'accepted' && (string) $application['status'] !== 'accepted') {
        $acceptedStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM applications
             WHERE job_id = :job_id AND status = \'accepted\''
        );
        $acceptedStmt->execute([':job_id' => (int) $application['job_id']]);
        $acceptedCount = (int) $acceptedStmt->fetchColumn();

        if ($acceptedCount >= (int) $application['quota']) {
            applicant_status_json([
                'success' => false,
                'message' => 'Kuota lowongan sudah penuh. Tidak bisa menerima pelamar baru',
            ], 409);
        }
    }

    $updateStmt = $pdo->prepare(
        'UPDATE applications
         SET status = :status,
             notes = :notes,
             reviewed_at = NOW(),
             reviewed_by = :reviewed_by
         WHERE id = :application_id'
    );
    $updateStmt->execute([
        ':status' => $newStatus,
        ':notes' => $notes !== '' ? $notes : null,
        ':reviewed_by' => $userId,
        ':application_id' => $applicationId,
    ]);

    log_activity(
        $userId,
        'update_application_status',
        'Perusahaan mengubah status lamaran ' . (string) $application['full_name'] . ' untuk lowongan ' . (string) $application['job_title'] . ' menjadi ' . $newStatus
    );

    applicant_status_json([
        'success' => true,
        'message' => 'Status lamaran berhasil diperbarui',
        'new_status' => $newStatus,
        'badge_html' => applicant_status_badge($newStatus),
    ]);
} catch (PDOException $exception) {
    error_log('Applicant AJAX status update failed: ' . $exception->getMessage());
    applicant_status_json([
        'success' => false,
        'message' => 'Status lamaran gagal diperbarui',
    ], 500);
}
