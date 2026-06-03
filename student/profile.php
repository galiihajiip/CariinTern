<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/file_upload.php';

require_role('student');

$page_title = 'Profil Mahasiswa';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$studentProfile = null;
$studyPrograms = [];
$profileCompletion = 0;
$activeTab = ($_GET['tab'] ?? '') === 'documents' ? 'documents' : 'personal';
$old = [
    'full_name' => '',
    'student_id' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'program_id' => '',
    'semester' => '1',
    'gpa' => '0.00',
    'avatar' => '',
    'cv_file' => '',
    'transcript_file' => '',
];

function student_profile_completion(array $profile): int
{
    $fields = [
        'full_name',
        'student_id',
        'phone',
        'address',
        'program_id',
        'cv_file',
        'transcript_file',
    ];
    $filled = 0;

    foreach ($fields as $field) {
        $value = $profile[$field] ?? null;

        if ($value !== null && trim((string) $value) !== '' && (string) $value !== '0') {
            $filled++;
        }
    }

    return (int) round(($filled / count($fields)) * 100);
}

function student_profile_load(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT student_profiles.*, users.email, users.avatar, users.name AS account_name,
                study_programs.name AS program_name
         FROM student_profiles
         INNER JOIN users ON users.id = student_profiles.user_id
         LEFT JOIN study_programs ON study_programs.id = student_profiles.program_id
         WHERE student_profiles.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();

    return $profile ?: null;
}

function student_profile_file_meta(string $filename, string $folder): ?array
{
    $filePath = get_upload_file_path($filename, $folder);

    if ($filePath === null || !is_file($filePath)) {
        return null;
    }

    return [
        'size' => filesize($filePath) ?: 0,
        'modified_at' => filemtime($filePath) ?: null,
    ];
}

function student_profile_format_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    return number_format(max(1, $bytes / 1024), 1) . ' KB';
}

try {
    $pdo = Database::getInstance()->getConnection();

    $programStmt = $pdo->query(
        'SELECT id, name, code, faculty
         FROM study_programs
         WHERE is_active = 1
         ORDER BY faculty ASC, name ASC'
    );
    $studyPrograms = $programStmt->fetchAll();

    $studentProfile = student_profile_load($pdo, $userId);

    if (!$studentProfile && $studyPrograms !== []) {
        $insertStmt = $pdo->prepare(
            'INSERT INTO student_profiles (user_id, student_id, full_name, phone, address, program_id, semester, gpa)
             VALUES (:user_id, :student_id, :full_name, :phone, :address, :program_id, :semester, :gpa)'
        );
        $insertStmt->execute([
            ':user_id' => $userId,
            ':student_id' => 'TMP' . $userId,
            ':full_name' => (string) ($_SESSION['user_name'] ?? 'Mahasiswa'),
            ':phone' => '',
            ':address' => '',
            ':program_id' => (int) $studyPrograms[0]['id'],
            ':semester' => 1,
            ':gpa' => 0.00,
        ]);
        $studentProfile = student_profile_load($pdo, $userId);
    }

    if (!$studentProfile) {
        set_flash('error', 'Profil mahasiswa gagal dimuat. Pastikan program studi aktif tersedia');
    } else {
        $profileCompletion = student_profile_completion($studentProfile);
        $_SESSION['profile_completed'] = $profileCompletion;
        $old = [
            'full_name' => sanitize((string) $studentProfile['full_name']),
            'student_id' => sanitize((string) $studentProfile['student_id']),
            'email' => sanitize((string) $studentProfile['email']),
            'phone' => sanitize((string) $studentProfile['phone']),
            'address' => sanitize((string) $studentProfile['address']),
            'program_id' => (string) (int) $studentProfile['program_id'],
            'semester' => (string) (int) $studentProfile['semester'],
            'gpa' => number_format((float) $studentProfile['gpa'], 2, '.', ''),
            'avatar' => sanitize((string) ($studentProfile['avatar'] ?? '')),
            'cv_file' => sanitize((string) ($studentProfile['cv_file'] ?? '')),
            'transcript_file' => sanitize((string) ($studentProfile['transcript_file'] ?? '')),
        ];
    }
} catch (PDOException $exception) {
    error_log('Load student profile failed: ' . $exception->getMessage());
    set_flash('error', 'Profil mahasiswa gagal dimuat');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'personal') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/student/profile.php');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $nim = strtoupper(trim((string) ($_POST['student_id'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $programId = (int) ($_POST['program_id'] ?? 0);
    $semester = (int) ($_POST['semester'] ?? 0);
    $gpa = (float) ($_POST['gpa'] ?? -1);
    $validationData = $_POST;
    $validationData['student_id'] = $nim;
    $validator = (new Validator($validationData))
        ->required('form_type', 'Form')
        ->in_array('form_type', ['personal'], 'Form')
        ->required('full_name', 'Nama lengkap')
        ->min_length('full_name', 3, 'Nama lengkap')
        ->max_length('full_name', 100, 'Nama lengkap')
        ->required('student_id', 'NIM')
        ->max_length('student_id', 30, 'NIM')
        ->unique('student_id', 'student_profiles', 'student_id', (int) ($studentProfile['id'] ?? 0))
        ->required('phone', 'No. HP')
        ->max_length('phone', 20, 'No. HP')
        ->required('address', 'Alamat')
        ->required('program_id', 'Program studi')
        ->numeric('program_id', 'Program studi')
        ->required('semester', 'Semester')
        ->numeric('semester', 'Semester')
        ->between('semester', 1, 14, 'Semester')
        ->required('gpa', 'IPK')
        ->numeric('gpa', 'IPK')
        ->between('gpa', 0, 4, 'IPK');
    $errors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];

    $old = [
        'full_name' => sanitize($fullName),
        'student_id' => sanitize($nim),
        'email' => $old['email'],
        'phone' => sanitize($phone),
        'address' => sanitize($address),
        'program_id' => (string) $programId,
        'semester' => (string) $semester,
        'gpa' => number_format(max(0, $gpa), 2, '.', ''),
        'avatar' => $old['avatar'],
        'cv_file' => $old['cv_file'],
        'transcript_file' => $old['transcript_file'],
    ];

    try {
        $programCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM study_programs WHERE id = :id AND is_active = 1');
        $programCheckStmt->execute([':id' => $programId]);

        if ((int) $programCheckStmt->fetchColumn() === 0) {
            $errors[] = 'Program studi tidak valid';
        }

        if ($errors === []) {
            $nextProfile = [
                'full_name' => $fullName,
                'student_id' => $nim,
                'phone' => $phone,
                'address' => $address,
                'program_id' => $programId,
                'cv_file' => $studentProfile['cv_file'] ?? '',
                'transcript_file' => $studentProfile['transcript_file'] ?? '',
            ];
            $nextCompletion = student_profile_completion($nextProfile);
            $completedFlag = $nextCompletion === 100 ? 1 : 0;

            $pdo->beginTransaction();

            $userStmt = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
            $userStmt->execute([
                ':name' => $fullName,
                ':id' => $userId,
            ]);

            $profileStmt = $pdo->prepare(
                'UPDATE student_profiles
                 SET student_id = :student_id,
                     full_name = :full_name,
                     phone = :phone,
                     address = :address,
                     program_id = :program_id,
                     semester = :semester,
                     gpa = :gpa,
                     profile_completed = :profile_completed
                 WHERE user_id = :user_id'
            );
            $profileStmt->execute([
                ':student_id' => $nim,
                ':full_name' => $fullName,
                ':phone' => $phone,
                ':address' => $address,
                ':program_id' => $programId,
                ':semester' => $semester,
                ':gpa' => $gpa,
                ':profile_completed' => $completedFlag,
                ':user_id' => $userId,
            ]);

            $pdo->commit();

            $_SESSION['user_name'] = $fullName;
            $_SESSION['profile_completed'] = $nextCompletion;

            log_activity($userId, 'update_student_profile', 'Mahasiswa memperbarui data diri');
            set_flash('success', 'Data diri berhasil diperbarui');
            redirect(BASE_URL . '/student/profile.php');
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Update student profile failed: ' . $exception->getMessage());
        $errors[] = 'Data diri gagal diperbarui. Silakan coba lagi nanti';
    }

    foreach ($errors as $error) {
        set_flash('error', $error);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'documents') {
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        set_flash('error', 'Permintaan tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/student/profile.php?tab=documents');
    }

    $activeTab = 'documents';
    $documentAction = trim((string) ($_POST['document_action'] ?? 'upload'));
    $validator = (new Validator($_POST))
        ->required('form_type', 'Form')
        ->in_array('form_type', ['documents'], 'Form')
        ->required('document_action', 'Aksi dokumen')
        ->in_array('document_action', ['upload', 'delete_cv', 'delete_transcript'], 'Aksi dokumen');
    $errors = $validator->fails() ? array_merge(...array_values($validator->errors())) : [];
    $hasCvUpload = isset($_FILES['cv_file']) && ($_FILES['cv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $hasTranscriptUpload = isset($_FILES['transcript_file']) && ($_FILES['transcript_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $oldCvFile = (string) ($studentProfile['cv_file'] ?? '');
    $oldTranscriptFile = (string) ($studentProfile['transcript_file'] ?? '');
    $newCvFile = $oldCvFile;
    $newTranscriptFile = $oldTranscriptFile;
    $uploadedCvFile = '';
    $uploadedTranscriptFile = '';
    $studentNim = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($studentProfile['student_id'] ?? 'student_' . $userId));

    if (!$studentProfile) {
        $errors[] = 'Profil mahasiswa belum tersedia';
    }

    if ($documentAction === 'delete_cv') {
        $newCvFile = '';
        $hasCvUpload = false;
        $hasTranscriptUpload = false;
    } elseif ($documentAction === 'delete_transcript') {
        $newTranscriptFile = '';
        $hasCvUpload = false;
        $hasTranscriptUpload = false;
    } elseif (!$hasCvUpload && !$hasTranscriptUpload) {
        $errors[] = 'Pilih minimal satu dokumen PDF untuk diupload';
    }

    if ($errors === [] && $hasCvUpload) {
        $upload = upload_file($_FILES['cv_file'], 'cv', 'cv_' . $studentNim);

        if (!$upload['success']) {
            $errors[] = 'CV: ' . $upload['message'];
        } else {
            $uploadedCvFile = $upload['filename'];
            $newCvFile = $upload['filename'];
        }
    }

    if ($errors === [] && $hasTranscriptUpload) {
        $upload = upload_file($_FILES['transcript_file'], 'transcripts', 'transkrip_' . $studentNim);

        if (!$upload['success']) {
            $errors[] = 'Transkrip: ' . $upload['message'];
        } else {
            $uploadedTranscriptFile = $upload['filename'];
            $newTranscriptFile = $upload['filename'];
        }
    }

    if ($errors !== []) {
        if ($uploadedCvFile !== '') {
            delete_file($uploadedCvFile, 'cv');
        }

        if ($uploadedTranscriptFile !== '') {
            delete_file($uploadedTranscriptFile, 'transcripts');
        }
    }

    if ($errors === []) {
        try {
            $nextProfile = [
                'full_name' => $studentProfile['full_name'] ?? '',
                'student_id' => $studentProfile['student_id'] ?? '',
                'phone' => $studentProfile['phone'] ?? '',
                'address' => $studentProfile['address'] ?? '',
                'program_id' => $studentProfile['program_id'] ?? '',
                'cv_file' => $newCvFile,
                'transcript_file' => $newTranscriptFile,
            ];
            $nextCompletion = student_profile_completion($nextProfile);
            $completedFlag = $nextCompletion === 100 ? 1 : 0;

            $stmt = $pdo->prepare(
                'UPDATE student_profiles
                 SET cv_file = :cv_file,
                     transcript_file = :transcript_file,
                     profile_completed = :profile_completed
                 WHERE user_id = :user_id'
            );
            $stmt->execute([
                ':cv_file' => $newCvFile !== '' ? $newCvFile : null,
                ':transcript_file' => $newTranscriptFile !== '' ? $newTranscriptFile : null,
                ':profile_completed' => $completedFlag,
                ':user_id' => $userId,
            ]);

            if (($documentAction === 'delete_cv' || $uploadedCvFile !== '') && $oldCvFile !== '') {
                delete_file($oldCvFile, 'cv');
            }

            if (($documentAction === 'delete_transcript' || $uploadedTranscriptFile !== '') && $oldTranscriptFile !== '') {
                delete_file($oldTranscriptFile, 'transcripts');
            }

            $_SESSION['profile_completed'] = $nextCompletion;

            log_activity($userId, 'update_student_documents', 'Mahasiswa memperbarui dokumen profil');
            set_flash('success', 'Dokumen profil berhasil diperbarui');
            redirect(BASE_URL . '/student/profile.php?tab=documents');
        } catch (PDOException $exception) {
            if ($uploadedCvFile !== '') {
                delete_file($uploadedCvFile, 'cv');
            }

            if ($uploadedTranscriptFile !== '') {
                delete_file($uploadedTranscriptFile, 'transcripts');
            }

            error_log('Update student documents failed: ' . $exception->getMessage());
            $errors[] = 'Dokumen gagal diperbarui. Silakan coba lagi nanti';
        }
    }

    foreach ($errors as $error) {
        set_flash('error', $error);
    }
}

$avatarUrl = $old['avatar'] !== ''
    ? rtrim(BASE_URL, '/') . '/uploads/' . ltrim($old['avatar'], '/')
    : 'https://placehold.co/160x160?text=Foto';
$cvUrl = $old['cv_file'] !== '' ? get_file_url($old['cv_file'], 'cv') : '';
$transcriptUrl = $old['transcript_file'] !== '' ? get_file_url($old['transcript_file'], 'transcripts') : '';
$cvMeta = $old['cv_file'] !== '' ? student_profile_file_meta($old['cv_file'], 'cv') : null;
$transcriptMeta = $old['transcript_file'] !== '' ? student_profile_file_meta($old['transcript_file'], 'transcripts') : null;

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_student.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Profil Mahasiswa</h1>
        <p class="text-muted mb-0">Kelola data diri dan dokumen pendukung lamaran magang.</p>
    </div>
    <span class="badge <?= $profileCompletion === 100 ? 'bg-success' : 'bg-warning text-dark'; ?> fs-6">
        Kelengkapan <?= $profileCompletion; ?>%
    </span>
</div>

<?= display_flash(); ?>

<ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'personal' ? 'active' : ''; ?>" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal-pane" type="button" role="tab" aria-controls="personal-pane" aria-selected="<?= $activeTab === 'personal' ? 'true' : 'false'; ?>">
            Data Diri
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'documents' ? 'active' : ''; ?>" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab" aria-controls="documents-pane" aria-selected="<?= $activeTab === 'documents' ? 'true' : 'false'; ?>">
            Upload Dokumen
        </button>
    </li>
</ul>

<div class="tab-content" id="profileTabsContent">
    <div class="tab-pane fade <?= $activeTab === 'personal' ? 'show active' : ''; ?>" id="personal-pane" role="tabpanel" aria-labelledby="personal-tab" tabindex="0">
        <div class="row g-4">
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <img
                            src="<?= sanitize($avatarUrl); ?>"
                            alt="Foto profil mahasiswa"
                            class="rounded-circle border object-fit-cover mb-3"
                            style="width: 160px; height: 160px;"
                        >
                        <h2 class="h5 fw-bold mb-1"><?= $old['full_name'] !== '' ? $old['full_name'] : 'Mahasiswa'; ?></h2>
                        <p class="text-muted mb-3"><?= $old['email']; ?></p>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="bi bi-camera me-1"></i>
                            Ganti Foto
                        </button>

                        <hr>

                        <div class="text-start">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Kelengkapan Profil</span>
                                <span><?= $profileCompletion; ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div
                                    class="progress-bar <?= $profileCompletion === 100 ? 'bg-success' : 'bg-warning'; ?>"
                                    role="progressbar"
                                    style="width: <?= $profileCompletion; ?>%;"
                                    aria-valuenow="<?= $profileCompletion; ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                ></div>
                            </div>
                            <div class="small text-muted mt-2">Lengkapi data diri dan dokumen agar bisa melamar magang.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 fw-bold mb-3">Data Diri</h2>

                        <form id="personalForm" class="needs-validation" method="POST" action="profile.php" novalidate>
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_type" value="personal">

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="fullName" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="fullName" name="full_name" value="<?= $old['full_name']; ?>" minlength="3" required>
                                    <div class="invalid-feedback">Nama lengkap minimal 3 karakter.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="studentId" class="form-label">NIM</label>
                                    <input type="text" class="form-control" id="studentId" name="student_id" value="<?= $old['student_id']; ?>" required>
                                    <div class="invalid-feedback">NIM wajib diisi dan harus unik.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" value="<?= $old['email']; ?>" readonly>
                                    <div class="form-text">Email login tidak dapat diubah dari halaman ini.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="phone" class="form-label">No. HP</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?= $old['phone']; ?>" required>
                                    <div class="invalid-feedback">No. HP wajib diisi.</div>
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?= $old['address']; ?></textarea>
                                    <div class="invalid-feedback">Alamat wajib diisi.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="programId" class="form-label">Program Studi</label>
                                    <select class="form-select" id="programId" name="program_id" required>
                                        <option value="">Pilih Program Studi</option>
                                        <?php foreach ($studyPrograms as $program): ?>
                                            <?php $programId = (int) $program['id']; ?>
                                            <option value="<?= $programId; ?>" <?= $old['program_id'] === (string) $programId ? 'selected' : ''; ?>>
                                                <?= sanitize((string) $program['name']); ?> (<?= sanitize((string) $program['code']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Program studi wajib dipilih.</div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="semester" class="form-label">Semester</label>
                                    <select class="form-select" id="semester" name="semester" required>
                                        <?php for ($semester = 1; $semester <= 14; $semester++): ?>
                                            <option value="<?= $semester; ?>" <?= $old['semester'] === (string) $semester ? 'selected' : ''; ?>>
                                                <?= $semester; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="invalid-feedback">Semester harus 1-14.</div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="gpa" class="form-label">IPK</label>
                                    <input type="number" class="form-control" id="gpa" name="gpa" value="<?= $old['gpa']; ?>" min="0" max="4" step="0.01" required>
                                    <div class="invalid-feedback">IPK harus antara 0.00 sampai 4.00.</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="<?= rtrim(BASE_URL, '/'); ?>/student/dashboard.php" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'documents' ? 'show active' : ''; ?>" id="documents-pane" role="tabpanel" aria-labelledby="documents-tab" tabindex="0">
        <form id="documentsForm" class="needs-validation" method="POST" action="profile.php?tab=documents" enctype="multipart/form-data" novalidate>
            <?= csrf_field(); ?>
            <input type="hidden" name="form_type" value="documents">
            <input type="hidden" name="document_action" value="upload">

            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="h5 fw-bold mb-1">CV</h2>
                                    <p class="text-muted small mb-0">Maks. 5MB, format PDF saja.</p>
                                </div>
                                <i class="bi bi-file-earmark-pdf fs-2 text-danger"></i>
                            </div>

                            <div class="border rounded-3 bg-light p-3 mb-3">
                                <?php if ($old['cv_file'] !== ''): ?>
                                    <div class="fw-semibold text-break"><?= $old['cv_file']; ?></div>
                                    <div class="small text-muted">
                                        <?= $cvMeta ? student_profile_format_file_size((int) $cvMeta['size']) : 'Ukuran tidak tersedia'; ?>
                                        <?php if ($cvMeta && $cvMeta['modified_at']): ?>
                                            · <?= date('d M Y H:i', (int) $cvMeta['modified_at']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a href="<?= sanitize($cvUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download me-1"></i>
                                            Download
                                        </a>
                                        <button type="submit" name="document_action" value="delete_cv" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i>
                                            Hapus
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Belum diupload</span>
                                <?php endif; ?>
                            </div>

                            <label class="upload-area d-block text-center p-4" for="cvFile" data-drop-target="cvFile">
                                <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                                <div class="fw-semibold mt-2">Klik atau drag PDF CV ke sini</div>
                                <div class="text-muted small">File akan menggantikan CV lama setelah disimpan.</div>
                                <div class="small text-primary mt-2" id="cvFilePreview"></div>
                            </label>
                            <input type="file" class="form-control d-none document-input" id="cvFile" name="cv_file" accept="application/pdf,.pdf" data-preview="cvFilePreview">
                            <div class="invalid-feedback d-block" id="cvFileError"></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="h5 fw-bold mb-1">Transkrip</h2>
                                    <p class="text-muted small mb-0">Maks. 5MB, format PDF saja.</p>
                                </div>
                                <i class="bi bi-file-earmark-pdf fs-2 text-danger"></i>
                            </div>

                            <div class="border rounded-3 bg-light p-3 mb-3">
                                <?php if ($old['transcript_file'] !== ''): ?>
                                    <div class="fw-semibold text-break"><?= $old['transcript_file']; ?></div>
                                    <div class="small text-muted">
                                        <?= $transcriptMeta ? student_profile_format_file_size((int) $transcriptMeta['size']) : 'Ukuran tidak tersedia'; ?>
                                        <?php if ($transcriptMeta && $transcriptMeta['modified_at']): ?>
                                            · <?= date('d M Y H:i', (int) $transcriptMeta['modified_at']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a href="<?= sanitize($transcriptUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download me-1"></i>
                                            Download
                                        </a>
                                        <button type="submit" name="document_action" value="delete_transcript" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i>
                                            Hapus
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Belum diupload</span>
                                <?php endif; ?>
                            </div>

                            <label class="upload-area d-block text-center p-4" for="transcriptFile" data-drop-target="transcriptFile">
                                <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                                <div class="fw-semibold mt-2">Klik atau drag PDF transkrip ke sini</div>
                                <div class="text-muted small">File akan menggantikan transkrip lama setelah disimpan.</div>
                                <div class="small text-primary mt-2" id="transcriptFilePreview"></div>
                            </label>
                            <input type="file" class="form-control d-none document-input" id="transcriptFile" name="transcript_file" accept="application/pdf,.pdf" data-preview="transcriptFilePreview">
                            <div class="invalid-feedback d-block" id="transcriptFileError"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                        <div>
                            <div class="fw-semibold">Estimasi Kelengkapan Profil</div>
                            <div class="text-muted small">Progress akan bertambah otomatis saat CV/transkrip dipilih.</div>
                        </div>
                        <span class="badge bg-primary fs-6" id="documentProgressLabel"><?= $profileCompletion; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div
                            class="progress-bar bg-primary"
                            id="documentProgressBar"
                            role="progressbar"
                            style="width: <?= $profileCompletion; ?>%;"
                            aria-valuenow="<?= $profileCompletion; ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        ></div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= rtrim(BASE_URL, '/'); ?>/student/profile.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>
                            Simpan Dokumen
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avatarModalLabel">Ganti Foto Profil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-0">
                    Fitur upload foto profil akan diaktifkan pada bagian upload berikutnya.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    const personalForm = document.getElementById('personalForm');
    const gpaInput = document.getElementById('gpa');
    const documentsForm = document.getElementById('documentsForm');
    const documentInputs = document.querySelectorAll('.document-input');
    const documentProgressBar = document.getElementById('documentProgressBar');
    const documentProgressLabel = document.getElementById('documentProgressLabel');
    const maxPdfSize = 5 * 1024 * 1024;
    const baseCompletion = <?= (int) $profileCompletion; ?>;
    const hasExistingCv = <?= $old['cv_file'] !== '' ? 'true' : 'false'; ?>;
    const hasExistingTranscript = <?= $old['transcript_file'] !== '' ? 'true' : 'false'; ?>;
    const documentWeight = Math.round(100 / 7);

    gpaInput.addEventListener('input', () => {
        const value = Number(gpaInput.value);
        gpaInput.setCustomValidity(value < 0 || value > 4 ? 'IPK harus antara 0.00 sampai 4.00.' : '');
    });

    personalForm.addEventListener('submit', event => {
        const gpa = Number(gpaInput.value);
        gpaInput.setCustomValidity(gpa < 0 || gpa > 4 ? 'IPK harus antara 0.00 sampai 4.00.' : '');

        if (!personalForm.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        personalForm.classList.add('was-validated');
    });

    function formatFileSize(bytes) {
        return bytes >= 1024 * 1024
            ? (bytes / (1024 * 1024)).toFixed(2) + ' MB'
            : (bytes / 1024).toFixed(1) + ' KB';
    }

    function validatePdfInput(input) {
        const file = input.files[0];
        const error = document.getElementById(input.id + 'Error');
        const preview = document.getElementById(input.dataset.preview);

        error.textContent = '';
        input.classList.remove('is-invalid');

        if (!file) {
            preview.textContent = '';
            return true;
        }

        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

        if (!isPdf) {
            error.textContent = 'File harus berformat PDF.';
            input.classList.add('is-invalid');
            return false;
        }

        if (file.size > maxPdfSize) {
            error.textContent = 'Ukuran file maksimal 5MB.';
            input.classList.add('is-invalid');
            return false;
        }

        preview.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
        return true;
    }

    function updateDocumentProgress() {
        let estimatedCompletion = baseCompletion;

        if (!hasExistingCv && document.getElementById('cvFile').files.length > 0) {
            estimatedCompletion += documentWeight;
        }

        if (!hasExistingTranscript && document.getElementById('transcriptFile').files.length > 0) {
            estimatedCompletion += documentWeight;
        }

        estimatedCompletion = Math.min(100, estimatedCompletion);
        documentProgressBar.style.width = estimatedCompletion + '%';
        documentProgressBar.setAttribute('aria-valuenow', String(estimatedCompletion));
        documentProgressLabel.textContent = estimatedCompletion + '%';
    }

    documentInputs.forEach(input => {
        input.addEventListener('change', () => {
            validatePdfInput(input);
            updateDocumentProgress();
        });
    });

    document.querySelectorAll('[data-drop-target]').forEach(area => {
        const input = document.getElementById(area.dataset.dropTarget);

        ['dragenter', 'dragover'].forEach(eventName => {
            area.addEventListener(eventName, event => {
                event.preventDefault();
                area.classList.add('border-primary');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            area.addEventListener(eventName, event => {
                event.preventDefault();
                area.classList.remove('border-primary');
            });
        });

        area.addEventListener('drop', event => {
            const file = event.dataTransfer.files[0];
            if (!file) return;

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;
            validatePdfInput(input);
            updateDocumentProgress();
        });
    });

    documentsForm.addEventListener('submit', event => {
        const submitter = event.submitter;

        if (submitter && submitter.name === 'document_action' && submitter.value.startsWith('delete_')) {
            return;
        }

        let valid = true;
        let hasSelectedFile = false;

        documentInputs.forEach(input => {
            if (input.files.length > 0) {
                hasSelectedFile = true;
            }

            valid = validatePdfInput(input) && valid;
        });

        if (!hasSelectedFile) {
            event.preventDefault();
            event.stopPropagation();
            document.getElementById('cvFileError').textContent = 'Pilih minimal satu dokumen PDF untuk diupload.';
            return;
        }

        if (!valid) {
            event.preventDefault();
            event.stopPropagation();
        }

        documentsForm.classList.add('was-validated');
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
