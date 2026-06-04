<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_notification.php';

header('Content-Type: application/json; charset=utf-8');

function push_unsubscribe_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    push_unsubscribe_json(['success' => false, 'message' => 'Method tidak diizinkan'], 405);
}

if (!is_logged_in()) {
    push_unsubscribe_json(['success' => false, 'message' => 'Silakan login terlebih dahulu'], 401);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
$endpoint = is_array($input) ? trim((string) ($input['endpoint'] ?? '')) : '';

if ($endpoint === '') {
    push_unsubscribe_json(['success' => false, 'message' => 'Endpoint tidak valid'], 422);
}

try {
    ensure_push_subscriptions_table();

    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare(
        'DELETE FROM push_subscriptions
         WHERE endpoint_hash = :endpoint_hash AND user_id = :user_id'
    );
    $stmt->execute([
        ':endpoint_hash' => hash('sha256', $endpoint),
        ':user_id' => (int) $_SESSION['user_id'],
    ]);

    push_unsubscribe_json(['success' => true, 'message' => 'Notifikasi berhasil dinonaktifkan']);
} catch (PDOException $exception) {
    error_log('Push unsubscribe failed: ' . $exception->getMessage());
    push_unsubscribe_json(['success' => false, 'message' => 'Subscription gagal dihapus'], 500);
}
