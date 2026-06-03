<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Aksi hapus kategori harus melalui form');
    redirect(BASE_URL . '/admin/categories/index.php');
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
    redirect(BASE_URL . '/admin/categories/index.php');
}

$validator = (new Validator($_POST))
    ->required('category_id', 'Kategori')
    ->numeric('category_id', 'Kategori');
$validationErrors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];
$categoryId = (int) ($_POST['category_id'] ?? 0);

if ($validationErrors !== [] || $categoryId <= 0) {
    set_flash('error', $validationErrors[0] ?? 'Kategori tidak valid');
    redirect(BASE_URL . '/admin/categories/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $categoryStmt = $pdo->prepare('SELECT name FROM internship_categories WHERE id = :id LIMIT 1');
    $categoryStmt->execute([':id' => $categoryId]);
    $category = $categoryStmt->fetch();

    if (!$category) {
        set_flash('error', 'Kategori tidak ditemukan');
        redirect(BASE_URL . '/admin/categories/index.php');
    }

    $jobStmt = $pdo->prepare('SELECT COUNT(*) FROM job_listings WHERE category_id = :category_id');
    $jobStmt->execute([':category_id' => $categoryId]);
    $jobsCount = (int) $jobStmt->fetchColumn();

    if ($jobsCount > 0) {
        set_flash('error', 'Kategori tidak dapat dihapus karena masih memiliki lowongan terkait');
        redirect(BASE_URL . '/admin/categories/index.php');
    }

    $deleteStmt = $pdo->prepare('DELETE FROM internship_categories WHERE id = :id');
    $deleteStmt->execute([':id' => $categoryId]);

    log_activity((int) ($_SESSION['user_id'] ?? 0), 'delete_category', 'Admin menghapus kategori: ' . $category['name']);
    set_flash('success', 'Kategori berhasil dihapus');
} catch (PDOException $exception) {
    error_log('Delete category failed: ' . $exception->getMessage());
    set_flash('error', 'Kategori gagal dihapus. Silakan coba lagi nanti');
}

redirect(BASE_URL . '/admin/categories/index.php');
