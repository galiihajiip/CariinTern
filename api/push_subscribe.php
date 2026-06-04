<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_notification.php';

header('Content-Type: application/json; charset=utf-8');

function push_subscribe_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    push_subscribe_json(['success' => false, 'message' => 'Method tidak diizinkan'], 405);
}

if (!is_logged_in()) {
    push_subscribe_json(['success' => false, 'message' => 'Silakan login terlebih dahulu'], 401);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($input)) {
    push_subscribe_json(['success' => false, 'message' => 'Payload tidak valid'], 422);
}

$endpoint = trim((string) ($input['endpoint'] ?? ''));
$p256dh = trim((string) ($input['keys']['p256dh'] ?? ''));
$auth = trim((string) ($input['keys']['auth'] ?? ''));

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    push_subscribe_json(['success' => false, 'message' => 'Data subscription tidak lengkap'], 422);
}

try {
    ensure_push_subscriptions_table();

    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
         VALUES (:user_id, :endpoint, :endpoint_hash, :p256dh, :auth, :user_agent)
         ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             endpoint = VALUES(endpoint),
             p256dh = VALUES(p256dh),
             auth = VALUES(auth),
             user_agent = VALUES(user_agent),
             updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':user_id' => (int) $_SESSION['user_id'],
        ':endpoint' => $endpoint,
        ':endpoint_hash' => hash('sha256', $endpoint),
        ':p256dh' => $p256dh,
        ':auth' => $auth,
        ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    push_subscribe_json(['success' => true, 'message' => 'Notifikasi berhasil diaktifkan']);
} catch (PDOException $exception) {
    error_log('Push subscribe failed: ' . $exception->getMessage());
    push_subscribe_json(['success' => false, 'message' => 'Subscription gagal disimpan'], 500);
}
