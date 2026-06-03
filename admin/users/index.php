<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$page_title = 'Manajemen User';
$allowedRoles = ['admin', 'company', 'student'];
$selectedRole = trim((string) ($_GET['role'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($currentPage - 1) * $perPage;
$users = [];
$totalUsers = 0;
$totalPages = 1;

if (!in_array($selectedRole, $allowedRoles, true)) {
    $selectedRole = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        set_flash('error', 'User tidak valid');
    } elseif ($userId === $currentUserId) {
        set_flash('error', 'Anda tidak boleh menghapus akun sendiri');
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND id <> :current_user_id');
            $stmt->execute([
                ':id' => $userId,
                ':current_user_id' => $currentUserId,
            ]);

            if ($stmt->rowCount() > 0) {
                log_activity($currentUserId, 'delete_user', 'Admin menghapus user ID ' . $userId);
                set_flash('success', 'User berhasil dihapus');
            } else {
                set_flash('error', 'User tidak ditemukan atau tidak dapat dihapus');
            }
        } catch (PDOException $exception) {
            error_log('Delete user failed: ' . $exception->getMessage());
            set_flash('error', 'User gagal dihapus. Silakan coba lagi nanti');
        }
    }

    $queryParams = [];
    if ($selectedRole !== '') {
        $queryParams['role'] = $selectedRole;
    }
    if ($search !== '') {
        $queryParams['search'] = $search;
    }
    if ($currentPage > 1) {
        $queryParams['page'] = $currentPage;
    }

    $redirectUrl = BASE_URL . '/admin/users/index.php';
    if ($queryParams !== []) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }

    redirect($redirectUrl);
}

try {
    $pdo = Database::getInstance()->getConnection();
    $conditions = [];
    $params = [];

    if ($selectedRole !== '') {
        $conditions[] = 'role = :role';
        $params[':role'] = $selectedRole;
    }

    if ($search !== '') {
        $conditions[] = '(name LIKE :search OR email LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalUsers = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalUsers / $perPage));

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $perPage;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, is_active, created_at
         FROM users' . $whereSql . '
         ORDER BY created_at DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load admin users failed: ' . $exception->getMessage());
    set_flash('error', 'Data user gagal dimuat. Silakan coba lagi nanti');
}

if (!function_exists('admin_users_role_badge')) {
    function admin_users_role_badge(string $role): string
    {
        $classes = [
            'admin' => 'primary',
            'company' => 'success',
            'student' => 'info',
        ];
        $labels = [
            'admin' => 'Admin',
            'company' => 'Perusahaan',
            'student' => 'Mahasiswa',
        ];

        $class = $classes[$role] ?? 'secondary';
        $label = $labels[$role] ?? ucfirst($role);

        return '<span class="badge bg-' . $class . '">' . sanitize($label) . '</span>';
    }
}

if (!function_exists('admin_users_page_url')) {
    function admin_users_page_url(int $page, string $role, string $search): string
    {
        $params = ['page' => $page];

        if ($role !== '') {
            $params['role'] = $role;
        }

        if ($search !== '') {
            $params['search'] = $search;
        }

        return BASE_URL . '/admin/users/index.php?' . http_build_query($params);
    }
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_admin.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Manajemen User</h1>
        <p class="text-muted mb-0">Kelola akun admin, perusahaan, dan mahasiswa.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/users/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Tambah User
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET" action="index.php">
            <div class="col-12 col-md-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Semua Role</option>
                    <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="company" <?= $selectedRole === 'company' ? 'selected' : ''; ?>>Perusahaan</option>
                    <option value="student" <?= $selectedRole === 'student' ? 'selected' : ''; ?>>Mahasiswa</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="search" class="form-label">Cari Nama/Email</label>
                <input
                    type="search"
                    class="form-control"
                    id="search"
                    name="search"
                    value="<?= sanitize($search); ?>"
                    placeholder="Ketik nama atau email..."
                >
            </div>
            <div class="col-12 col-md-3 d-grid d-md-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>
                    Filter
                </button>
                <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/users/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Daftar User</h2>
                <p class="text-muted small mb-0">Menampilkan <?= number_format(count($users)); ?> dari <?= number_format($totalUsers); ?> user.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 72px;">No</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status Aktif</th>
                        <th>Tanggal Daftar</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Tidak ada user yang cocok.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $index => $user): ?>
                        <?php
                        $userId = (int) $user['id'];
                        $isCurrentAdmin = $userId === (int) ($_SESSION['user_id'] ?? 0);
                        $modalId = 'deleteUserModal' . $userId;
                        ?>
                        <tr>
                            <td><?= number_format($offset + $index + 1); ?></td>
                            <td>
                                <div class="fw-semibold"><?= sanitize((string) $user['name']); ?></div>
                                <?php if ($isCurrentAdmin): ?>
                                    <span class="badge text-bg-light">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize((string) $user['email']); ?></td>
                            <td><?= admin_users_role_badge((string) $user['role']); ?></td>
                            <td>
                                <?php if ((int) $user['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize(format_date((string) $user['created_at'], 'd M Y')); ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/admin/users/edit.php?id=<?= $userId; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>

                                    <?php if (!$isCurrentAdmin): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#<?= $modalId; ?>"
                                        >
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Pagination user">
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= admin_users_page_url(max(1, $currentPage - 1), $selectedRole, $search); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                        <li class="page-item <?= $page === $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="<?= admin_users_page_url($page, $selectedRole, $search); ?>"><?= $page; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= admin_users_page_url(min($totalPages, $currentPage + 1), $selectedRole, $search); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($users as $user): ?>
    <?php
    $userId = (int) $user['id'];
    $isCurrentAdmin = $userId === (int) ($_SESSION['user_id'] ?? 0);

    if ($isCurrentAdmin) {
        continue;
    }

    $modalId = 'deleteUserModal' . $userId;
    ?>
    <div class="modal fade" id="<?= $modalId; ?>" tabindex="-1" aria-labelledby="<?= $modalId; ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $modalId; ?>Label">Konfirmasi Hapus User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Apakah Anda yakin ingin menghapus user ini?</p>
                    <div class="fw-semibold"><?= sanitize((string) $user['name']); ?></div>
                    <div class="text-muted small"><?= sanitize((string) $user['email']); ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="index.php?<?= http_build_query(array_filter([
                        'role' => $selectedRole,
                        'search' => $search,
                        'page' => $currentPage > 1 ? $currentPage : null,
                    ], static fn ($value) => $value !== null && $value !== '')); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $userId; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
