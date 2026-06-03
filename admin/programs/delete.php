<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Aksi hapus program studi harus melalui form');
    redirect(BASE_URL . '/admin/programs/index.php');
}

$programId = (int) ($_POST['program_id'] ?? 0);

if ($programId <= 0) {
    set_flash('error', 'Program studi tidak valid');
    redirect(BASE_URL . '/admin/programs/index.php');
}

try {
    $pdo = Database::getInstance()->getConnection();
    $programStmt = $pdo->prepare('SELECT name FROM study_programs WHERE id = :id LIMIT 1');
    $programStmt->execute([':id' => $programId]);
    $program = $programStmt->fetch();

    if (!$program) {
        set_flash('error', 'Program studi tidak ditemukan');
        redirect(BASE_URL . '/admin/programs/index.php');
    }

    $studentStmt = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE program_id = :program_id');
    $studentStmt->execute([':program_id' => $programId]);
    $studentsCount = (int) $studentStmt->fetchColumn();

    if ($studentsCount > 0) {
        set_flash('error', 'Program studi tidak dapat dihapus karena masih memiliki mahasiswa terdaftar');
        redirect(BASE_URL . '/admin/programs/index.php');
    }

    $deleteStmt = $pdo->prepare('DELETE FROM study_programs WHERE id = :id');
    $deleteStmt->execute([':id' => $programId]);

    log_activity((int) ($_SESSION['user_id'] ?? 0), 'delete_program', 'Admin menghapus program studi: ' . $program['name']);
    set_flash('success', 'Program studi berhasil dihapus');
} catch (PDOException $exception) {
    error_log('Delete study program failed: ' . $exception->getMessage());
    set_flash('error', 'Program studi gagal dihapus. Silakan coba lagi nanti');
}

redirect(BASE_URL . '/admin/programs/index.php');
