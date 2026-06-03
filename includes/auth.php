<?php

require_once __DIR__ . '/functions.php';

function login_rate_limit_status(): array
{
    $now = time();
    $window = 600;
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $attemptTime = (int) ($_SESSION['login_attempt_time'] ?? 0);

    if ($attemptTime > 0 && ($now - $attemptTime) >= $window) {
        unset($_SESSION['login_attempts'], $_SESSION['login_attempt_time']);
        return ['locked' => false, 'remaining' => 0];
    }

    if ($attempts > 5 && $attemptTime > 0) {
        return [
            'locked' => true,
            'remaining' => max(1, $window - ($now - $attemptTime)),
        ];
    }

    return ['locked' => false, 'remaining' => 0];
}

function record_failed_login_attempt(): void
{
    $now = time();
    $window = 600;
    $attemptTime = (int) ($_SESSION['login_attempt_time'] ?? 0);

    if ($attemptTime === 0 || ($now - $attemptTime) >= $window) {
        $_SESSION['login_attempts'] = 1;
        $_SESSION['login_attempt_time'] = $now;
        return;
    }

    $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
}

function clear_failed_login_attempts(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['login_attempt_time']);
}

function login_user(string $email, string $password): array
{
    $rateLimit = login_rate_limit_status();

    if ($rateLimit['locked']) {
        $minutes = (int) ceil(((int) $rateLimit['remaining']) / 60);

        return [
            'success' => false,
            'message' => 'Terlalu banyak percobaan login gagal. Coba lagi dalam ' . $minutes . ' menit',
            'role' => null,
        ];
    }

    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => trim($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            record_failed_login_attempt();

            return [
                'success' => false,
                'message' => 'Email atau password salah',
                'role' => null,
            ];
        }

        if ((int) $user['is_active'] !== 1) {
            record_failed_login_attempt();

            return [
                'success' => false,
                'message' => 'Akun tidak aktif. Silakan hubungi administrator',
                'role' => $user['role'],
            ];
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();

        clear_failed_login_attempts();
        update_last_login((int) $user['id']);
        log_activity((int) $user['id'], 'login', 'User berhasil login');

        return [
            'success' => true,
            'message' => 'Login berhasil',
            'role' => $user['role'],
        ];
    } catch (PDOException $exception) {
        error_log('Login failed: ' . $exception->getMessage());

        return [
            'success' => false,
            'message' => 'Login gagal. Silakan coba lagi nanti',
            'role' => null,
        ];
    }
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    redirect(BASE_URL . '/login.php');
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Silakan login terlebih dahulu');
        redirect(BASE_URL . '/login.php');
    }
}

function require_role(string|array $roles): void
{
    require_login();

    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $currentRole = $_SESSION['user_role'] ?? '';

    if (!in_array($currentRole, $allowedRoles, true)) {
        set_flash('error', 'Akses ditolak');
        redirect(get_dashboard_url($currentRole));
    }
}

function check_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }

    $loginTime = (int) ($_SESSION['login_time'] ?? 0);

    if ($loginTime > 0 && (time() - $loginTime) > SESSION_TIMEOUT) {
        set_flash('error', 'Sesi Anda telah berakhir. Silakan login kembali');
        logout_user();
    }
}

function register_user(array $data): array
{
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $role = trim($data['role'] ?? '');
    $allowedRoles = ['admin', 'company', 'student'];

    if ($name === '' || $email === '' || $password === '' || $role === '') {
        return [
            'success' => false,
            'message' => 'Semua field wajib diisi',
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Format email tidak valid',
        ];
    }

    if (!in_array($role, $allowedRoles, true)) {
        return [
            'success' => false,
            'message' => 'Role tidak valid',
        ];
    }

    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Email sudah digunakan',
            ];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
        );

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_BCRYPT),
            ':role' => $role,
        ]);

        $userId = (int) $pdo->lastInsertId();
        log_activity($userId, 'register', 'User baru berhasil terdaftar');

        return [
            'success' => true,
            'message' => 'Registrasi berhasil',
            'user_id' => $userId,
        ];
    } catch (PDOException $exception) {
        error_log('Registration failed: ' . $exception->getMessage());

        return [
            'success' => false,
            'message' => 'Registrasi gagal. Silakan coba lagi nanti',
        ];
    }
}

function get_user_by_id(int $id): ?array
{
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT id, name, email, role, is_active, avatar, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    } catch (PDOException $exception) {
        error_log('Get user failed: ' . $exception->getMessage());

        return null;
    }
}

function update_last_login(int $user_id): void
{
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':id' => $user_id]);
    } catch (PDOException $exception) {
        error_log('Update last login failed: ' . $exception->getMessage());
    }
}

function get_dashboard_url(string $role): string
{
    return match ($role) {
        'admin' => BASE_URL . '/admin/dashboard.php',
        'company' => BASE_URL . '/company/dashboard.php',
        'student' => BASE_URL . '/student/dashboard.php',
        default => BASE_URL . '/login.php',
    };
}
