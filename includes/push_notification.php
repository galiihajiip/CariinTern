<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function ensure_push_subscriptions_table(): void
{
    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                endpoint TEXT NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                p256dh VARCHAR(500) NOT NULL,
                auth VARCHAR(100) NOT NULL,
                user_agent VARCHAR(255) NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_push_subscriptions_endpoint (endpoint_hash),
                KEY idx_push_subscriptions_user_id (user_id),
                CONSTRAINT fk_push_subscriptions_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (PDOException $exception) {
        error_log('Ensure push_subscriptions table failed: ' . $exception->getMessage());
    }
}

function send_push_notification(array $subscription, string $title, string $body, string $url, string $icon = ''): bool
{
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

    if (!is_file($autoloadPath)) {
        error_log('Push notification skipped: install minishlink/web-push with Composer to enable sending.');
        return false;
    }

    require_once $autoloadPath;

    if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
        error_log('Push notification skipped: Minishlink WebPush class not found.');
        return false;
    }

    try {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $icon !== '' ? $icon : rtrim(BASE_URL, '/') . '/assets/icons/icon-192x192.png',
            'badge' => rtrim(BASE_URL, '/') . '/assets/icons/icon-96x96.png',
            'url' => $url,
            'vibrate' => [200, 100, 200],
        ], JSON_THROW_ON_ERROR);

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);

        $webPush->queueNotification(
            \Minishlink\WebPush\Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'publicKey' => $subscription['p256dh'],
                'authToken' => $subscription['auth'],
            ]),
            $payload
        );

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                error_log('Push notification failed: ' . $report->getReason());
                return false;
            }
        }

        return true;
    } catch (Throwable $exception) {
        error_log('Push notification send failed: ' . $exception->getMessage());
        return false;
    }
}

function notify_user(int $userId, string $title, string $body, string $url): void
{
    ensure_push_subscriptions_table();

    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT endpoint, p256dh, auth
             FROM push_subscriptions
             WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);

        foreach ($stmt->fetchAll() as $subscription) {
            send_push_notification($subscription, $title, $body, $url);
        }
    } catch (PDOException $exception) {
        error_log('Notify user failed: ' . $exception->getMessage());
    }
}

function notify_role(string $role, string $title, string $body, string $url): void
{
    ensure_push_subscriptions_table();

    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT push_subscriptions.endpoint, push_subscriptions.p256dh, push_subscriptions.auth
             FROM push_subscriptions
             INNER JOIN users ON users.id = push_subscriptions.user_id
             WHERE users.role = :role AND users.is_active = 1'
        );
        $stmt->execute([':role' => $role]);

        foreach ($stmt->fetchAll() as $subscription) {
            send_push_notification($subscription, $title, $body, $url);
        }
    } catch (PDOException $exception) {
        error_log('Notify role failed: ' . $exception->getMessage());
    }
}
