<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/flash.php';

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    return $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . sanitize(generate_csrf_token()) . '">';
}

class Validator
{
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value === null || trim((string) $value) === '') {
            $this->addError($field, $label . ' wajib diisi');
        }

        return $this;
    }

    public function min_length(string $field, int $min, string $label): self
    {
        $value = trim((string) $this->value($field, ''));

        if ($value !== '' && strlen($value) < $min) {
            $this->addError($field, $label . ' minimal ' . $min . ' karakter');
        }

        return $this;
    }

    public function max_length(string $field, int $max, string $label): self
    {
        $value = trim((string) $this->value($field, ''));

        if ($value !== '' && strlen($value) > $max) {
            $this->addError($field, $label . ' maksimal ' . $max . ' karakter');
        }

        return $this;
    }

    public function email(string $field): self
    {
        $value = trim((string) $this->value($field, ''));

        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Format email tidak valid');
        }

        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        $value = $this->value($field);

        if ($value !== null && trim((string) $value) !== '' && !is_numeric($value)) {
            $this->addError($field, $label . ' harus berupa angka');
        }

        return $this;
    }

    public function between(string $field, float $min, float $max, string $label): self
    {
        $value = $this->value($field);

        if ($value !== null && trim((string) $value) !== '') {
            if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
                $this->addError($field, $label . ' harus antara ' . $min . ' sampai ' . $max);
            }
        }

        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = trim((string) $this->value($field, ''));

        if ($value !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, $label . ' harus berupa tanggal valid');
            }
        }

        return $this;
    }

    public function date_after(string $field, string $after_field, string $label): self
    {
        $value = trim((string) $this->value($field, ''));
        $afterValue = trim((string) $this->value($after_field, ''));

        if ($value !== '' && $afterValue !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            $afterDate = DateTimeImmutable::createFromFormat('Y-m-d', $afterValue);

            if ($date && $afterDate && $date->format('Y-m-d') === $value && $afterDate->format('Y-m-d') === $afterValue && $date <= $afterDate) {
                $this->addError($field, $label . ' harus setelah ' . $after_field);
            }
        }

        return $this;
    }

    public function in_array(string $field, array $allowed, string $label): self
    {
        $value = $this->value($field);

        if ($value !== null && trim((string) $value) !== '' && !in_array((string) $value, array_map('strval', $allowed), true)) {
            $this->addError($field, $label . ' tidak valid');
        }

        return $this;
    }

    public function unique(string $field, string $table, string $column, int $except_id = 0): self
    {
        $value = trim((string) $this->value($field, ''));

        if ($value === '') {
            return $this;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            $this->addError($field, 'Validasi unique tidak valid');
            return $this;
        }

        try {
            $pdo = Database::getInstance()->getConnection();
            $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = :value';
            $params = [':value' => $value];

            if ($except_id > 0) {
                $sql .= ' AND id <> :except_id';
                $params[':except_id'] = $except_id;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ((int) $stmt->fetchColumn() > 0) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' sudah digunakan');
            }
        } catch (PDOException $exception) {
            error_log('Unique validation failed: ' . $exception->getMessage());
            $this->addError($field, 'Validasi data gagal');
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    private function value(string $field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }
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

function ensure_activity_logs_is_read_column(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'is_read'");

        if ($stmt && $stmt->fetch() === false) {
            $pdo->exec('ALTER TABLE activity_logs ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER ip_address');
        }

        $checked = true;
    } catch (PDOException $exception) {
        error_log('Ensure activity log notification column failed: ' . $exception->getMessage());
    }
}

function get_notifications(int $user_id, int $limit = 5): array
{
    if ($user_id <= 0) {
        return [];
    }

    ensure_activity_logs_is_read_column();

    $limit = max(1, min($limit, 20));
    $role = (string) ($_SESSION['user_role'] ?? '');

    try {
        $pdo = Database::getInstance()->getConnection();

        if ($role === 'company') {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT activity_logs.id,
                        activity_logs.action,
                        activity_logs.description,
                        activity_logs.created_at,
                        activity_logs.is_read,
                        users.name AS actor_name
                 FROM activity_logs
                 INNER JOIN users ON users.id = activity_logs.user_id
                 INNER JOIN company_profiles ON company_profiles.user_id = :user_id
                 INNER JOIN job_listings ON job_listings.company_id = company_profiles.id
                 WHERE activity_logs.is_read = 0
                   AND activity_logs.action IN (\'application_created\', \'application_cancelled\')
                   AND activity_logs.description LIKE CONCAT(\'%\', job_listings.title, \'%\')
                 ORDER BY activity_logs.created_at DESC
                 LIMIT ' . $limit
            );
            $stmt->execute([':user_id' => $user_id]);

            return $stmt->fetchAll();
        }

        if ($role === 'student') {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT activity_logs.id,
                        activity_logs.action,
                        activity_logs.description,
                        activity_logs.created_at,
                        activity_logs.is_read,
                        users.name AS actor_name
                 FROM activity_logs
                 INNER JOIN users ON users.id = activity_logs.user_id
                 INNER JOIN student_profiles ON student_profiles.user_id = :user_id
                 WHERE activity_logs.is_read = 0
                   AND activity_logs.action = \'update_application_status\'
                   AND activity_logs.description LIKE CONCAT(\'%\', student_profiles.full_name, \'%\')
                 ORDER BY activity_logs.created_at DESC
                 LIMIT ' . $limit
            );
            $stmt->execute([':user_id' => $user_id]);

            return $stmt->fetchAll();
        }
    } catch (PDOException $exception) {
        error_log('Load notifications failed: ' . $exception->getMessage());
    }

    return [];
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
