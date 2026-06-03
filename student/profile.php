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
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $nim = strtoupper(trim((string) ($_POST['student_id'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $programId = (int) ($_POST['program_id'] ?? 0);
    $semester = (int) ($_POST['semester'] ?? 0);
    $gpa = (float) ($_POST['gpa'] ?? -1);
    $errors = [];

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

    if ($fullName === '' || strlen($fullName) < 3) {
        $errors[] = 'Nama lengkap wajib diisi minimal 3 karakter';
    }

    if ($nim === '') {
        $errors[] = 'NIM wajib diisi';
    }

    if ($phone === '') {
        $errors[] = 'No. HP wajib diisi';
    }

    if ($address === '') {
        $errors[] = 'Alamat wajib diisi';
    }

    if ($semester < 1 || $semester > 14) {
        $errors[] = 'Semester harus antara 1 sampai 14';
    }

    if ($gpa < 0 || $gpa > 4) {
        $errors[] = 'IPK harus antara 0.00 sampai 4.00';
    }

    try {
        $programCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM study_programs WHERE id = :id AND is_active = 1');
        $programCheckStmt->execute([':id' => $programId]);

        if ((int) $programCheckStmt->fetchColumn() === 0) {
            $errors[] = 'Program studi tidak valid';
        }

        $nimCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE student_id = :student_id AND user_id <> :user_id');
        $nimCheckStmt->execute([
            ':student_id' => $nim,
            ':user_id' => $userId,
        ]);

        if ((int) $nimCheckStmt->fetchColumn() > 0) {
            $errors[] = 'NIM sudah digunakan mahasiswa lain';
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

$avatarUrl = $old['avatar'] !== ''
    ? rtrim(BASE_URL, '/') . '/uploads/' . ltrim($old['avatar'], '/')
    : 'https://placehold.co/160x160?text=Foto';
$cvUrl = $old['cv_file'] !== '' ? get_file_url($old['cv_file'], 'cv') : '';
$transcriptUrl = $old['transcript_file'] !== '' ? get_file_url($old['transcript_file'], 'transcripts') : '';

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
        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal-pane" type="button" role="tab" aria-controls="personal-pane" aria-selected="true">
            Data Diri
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab" aria-controls="documents-pane" aria-selected="false">
            Upload Dokumen
        </button>
    </li>
</ul>

<div class="tab-content" id="profileTabsContent">
    <div class="tab-pane fade show active" id="personal-pane" role="tabpanel" aria-labelledby="personal-tab" tabindex="0">
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

    <div class="tab-pane fade" id="documents-pane" role="tabpanel" aria-labelledby="documents-tab" tabindex="0">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Upload Dokumen</h2>
                <p class="text-muted">Area upload dokumen akan dilengkapi pada bagian berikutnya. Dokumen saat ini:</p>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold mb-1">CV</div>
                            <?php if ($cvUrl !== ''): ?>
                                <a href="<?= sanitize($cvUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-earmark-person me-1"></i>
                                    Lihat CV
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum diupload</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold mb-1">Transkrip</div>
                            <?php if ($transcriptUrl !== ''): ?>
                                <a href="<?= sanitize($transcriptUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-earmark-text me-1"></i>
                                    Lihat Transkrip
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum diupload</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
