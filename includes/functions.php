<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/flash.php';

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function get_current_user_data(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? null,
    ];
}

function current_user(): ?array
{
    return get_current_user_data();
}

function format_date(string $date, string $format = 'd M Y'): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date($format, $timestamp);
}

function format_currency(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function truncate_text(string $text, int $length = 100): string
{
    $cleanText = trim($text);

    if (strlen($cleanText) <= $length) {
        return $cleanText;
    }

    return rtrim(substr($cleanText, 0, $length)) . '...';
}

function generate_slug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);

    return trim($slug, '-');
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return $datetime;
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'baru saja';
    }

    $units = [
        31536000 => 'tahun',
        2592000 => 'bulan',
        604800 => 'minggu',
        86400 => 'hari',
        3600 => 'jam',
        60 => 'menit',
    ];

    foreach ($units as $seconds => $label) {
        if ($diff >= $seconds) {
            $value = floor($diff / $seconds);

            return $value . ' ' . $label . ' lalu';
        }
    }

    return 'baru saja';
}

function get_status_badge(string $status): string
{
    $classes = [
        'pending' => 'secondary',
        'review' => 'warning',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];

    $badgeClass = $classes[$status] ?? 'secondary';
    $label = ucfirst($status);

    return sprintf(
        '<span class="badge bg-%s">%s</span>',
        $badgeClass,
        sanitize($label)
    );
}

function log_activity(?int $user_id, string $action, string $description = ''): void
{
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, :action, :description, :ip_address)'
        );

        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':description' => $description !== '' ? $description : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);
    } catch (PDOException $exception) {
        error_log('Activity log failed: ' . $exception->getMessage());
    }
}
