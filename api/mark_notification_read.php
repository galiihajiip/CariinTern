<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

function notification_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notification_json([
        'success' => false,
        'message' => 'Method tidak diizinkan',
    ], 405);
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    notification_json([
        'success' => false,
        'message' => 'Permintaan tidak valid',
    ], 403);
}

if (!isset($_SESSION['user_id'])) {
    notification_json([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu',
    ], 401);
}

$validator = (new Validator($_POST))
    ->required('notification_id', 'Notifikasi')
    ->numeric('notification_id', 'Notifikasi');
$validationErrors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];
$notificationId = (int) ($_POST['notification_id'] ?? 0);

if ($validationErrors !== [] || $notificationId <= 0) {
    notification_json([
        'success' => false,
        'message' => $validationErrors[0] ?? 'Notifikasi tidak valid',
    ], 422);
}

try {
    ensure_activity_logs_is_read_column();

    $allowedNotifications = array_map(
        static fn ($notification) => (int) $notification['id'],
        get_notifications((int) $_SESSION['user_id'], 20)
    );

    if (!in_array($notificationId, $allowedNotifications, true)) {
        notification_json([
            'success' => false,
            'message' => 'Notifikasi tidak ditemukan',
        ], 404);
    }

    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('UPDATE activity_logs SET is_read = 1 WHERE id = :id');
    $stmt->execute([':id' => $notificationId]);

    notification_json([
        'success' => true,
        'message' => 'Notifikasi ditandai sudah dibaca',
    ]);
} catch (PDOException $exception) {
    error_log('Mark notification read failed: ' . $exception->getMessage());
    notification_json([
        'success' => false,
        'message' => 'Notifikasi gagal diperbarui',
    ], 500);
}
