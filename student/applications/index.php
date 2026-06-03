<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_upload.php';

require_role('student');

$page_title = 'Lamaran Saya';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$studentProfile = null;
$studentId = 0;
$selectedStatus = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['pending', 'review', 'accepted', 'rejected'];
$applications = [];
$statusCounts = [
    'all' => 0,
    'pending' => 0,
    'review' => 0,
    'accepted' => 0,
    'rejected' => 0,
];

if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

function student_applications_tab_url(string $status): string
{
    if ($status === '') {
        return rtrim(BASE_URL, '/') . '/student/applications/index.php';
    }

    return rtrim(BASE_URL, '/') . '/student/applications/index.php?status=' . rawurlencode($status);
}

function student_applications_status_label(string $status): string
{
    $labels = [
        'pending' => 'Menunggu',
        'review' => 'Direview',
        'accepted' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function student_applications_status_badge(string $status): string
{
    $classes = [
        'pending' => 'secondary',
        'review' => 'warning text-dark',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];

    return '<span class="badge rounded-pill text-bg-' . ($classes[$status] ?? 'secondary') . ' fs-6 px-3 py-2">'
        . sanitize(student_applications_status_label($status))
        . '</span>';
}

function student_applications_timeline_step(string $label, string $state, string $icon): string
{
    $dotClass = match ($state) {
        'complete' => 'bg-success text-white',
        'active' => 'bg-primary text-white',
        'danger' => 'bg-danger text-white',
        default => 'bg-light text-muted border',
    };
    $labelClass = $state === 'muted' ? 'text-muted' : 'fw-semibold';

    return '<div class="application-timeline-step d-flex gap-3">'
        . '<div class="application-timeline-dot ' . $dotClass . '"><i class="bi ' . sanitize($icon) . '"></i></div>'
        . '<div class="' . $labelClass . '">' . sanitize($label) . '</div>'
        . '</div>';
}

function student_applications_timeline(string $status): string
{
    $steps = [
        ['Terkirim', 'muted', 'bi-send-check'],
        ['Dalam Review', 'muted', 'bi-search'],
        ['Hasil', 'muted', 'bi-flag'],
    ];

    if ($status === 'pending') {
        $steps[0][1] = 'active';
    } elseif ($status === 'review') {
        $steps[0][1] = 'complete';
        $steps[1][1] = 'active';
    } elseif ($status === 'accepted') {
        $steps[0][1] = 'complete';
        $steps[1][1] = 'complete';
        $steps[2] = ['Diterima', 'active', 'bi-check-circle'];
    } elseif ($status === 'rejected') {
        $steps[0][1] = 'complete';
        $steps[1][1] = 'complete';
        $steps[2] = ['Ditolak', 'danger', 'bi-x-circle'];
    }

    $html = '<div class="application-timeline">';

    foreach ($steps as $step) {
        $html .= student_applications_timeline_step($step[0], $step[1], $step[2]);
    }

    return $html . '</div>';
}

try {
    $pdo = Database::getInstance()->getConnection();

    $profileStmt = $pdo->prepare(
        'SELECT id, profile_completed
         FROM student_profiles
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $profileStmt->execute([':user_id' => $userId]);
    $studentProfile = $profileStmt->fetch();

    if (!$studentProfile) {
        set_flash('warning', 'Lengkapi profil kamu terlebih dahulu');
        redirect(BASE_URL . '/student/profile.php');
    }

    $studentId = (int) $studentProfile['id'];
    $_SESSION['profile_completed'] = (int) $studentProfile['profile_completed'] === 1
        ? 100
        : (int) ($_SESSION['profile_completed'] ?? 0);

    $countsStmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS total
         FROM applications
         WHERE student_id = :student_id
         GROUP BY status'
    );
    $countsStmt->execute([':student_id' => $studentId]);

    foreach ($countsStmt->fetchAll() as $row) {
        $status = (string) $row['status'];
        $count = (int) $row['total'];

        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = $count;
            $statusCounts['all'] += $count;
        }
    }

    $conditions = ['applications.student_id = :student_id'];
    $params = [':student_id' => $studentId];

    if ($selectedStatus !== '') {
        $conditions[] = 'applications.status = :status';
        $params[':status'] = $selectedStatus;
    }

    $stmt = $pdo->prepare(
        'SELECT applications.id, applications.cover_letter, applications.status, applications.notes,
                applications.created_at AS applied_at, applications.reviewed_at,
                job_listings.id AS job_id, job_listings.title AS job_title, job_listings.deadline,
                job_listings.location, job_listings.status AS job_status,
                company_profiles.company_name, company_profiles.logo, company_profiles.is_verified
         FROM applications
         INNER JOIN job_listings ON job_listings.id = applications.job_id
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY applications.created_at DESC, applications.id DESC'
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $applications = $stmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Load student applications failed: ' . $exception->getMessage());
    set_flash('error', 'Data lamaran gagal dimuat. Silakan coba lagi nanti');
}

require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar_student.php';
?>

<style>
    .application-timeline {
        position: relative;
        display: grid;
        gap: 1rem;
        padding-left: 0.25rem;
    }

    .application-timeline::before {
        content: "";
        position: absolute;
        top: 18px;
        bottom: 18px;
        left: 18px;
        width: 2px;
        background: #dee2e6;
    }

    .application-timeline-step {
        position: relative;
        z-index: 1;
        align-items: center;
    }

    .application-timeline-dot {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
    }
</style>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Lamaran Saya</h1>
        <p class="text-muted mb-0">Pantau status lamaran magang yang sudah kamu kirim.</p>
    </div>
    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-primary">
        <i class="bi bi-search me-1"></i>
        Cari Lowongan
    </a>
</div>

<?= display_flash(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <ul class="nav nav-tabs flex-nowrap overflow-auto">
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === '' ? 'active' : ''; ?>" href="<?= student_applications_tab_url(''); ?>">
                    Semua
                    <span class="badge text-bg-secondary ms-1"><?= number_format($statusCounts['all']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'pending' ? 'active' : ''; ?>" href="<?= student_applications_tab_url('pending'); ?>">
                    Menunggu
                    <span class="badge text-bg-secondary ms-1"><?= number_format($statusCounts['pending']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'review' ? 'active' : ''; ?>" href="<?= student_applications_tab_url('review'); ?>">
                    Direview
                    <span class="badge text-bg-warning ms-1"><?= number_format($statusCounts['review']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'accepted' ? 'active' : ''; ?>" href="<?= student_applications_tab_url('accepted'); ?>">
                    Diterima
                    <span class="badge text-bg-success ms-1"><?= number_format($statusCounts['accepted']); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $selectedStatus === 'rejected' ? 'active' : ''; ?>" href="<?= student_applications_tab_url('rejected'); ?>">
                    Ditolak
                    <span class="badge text-bg-danger ms-1"><?= number_format($statusCounts['rejected']); ?></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<?php if ($applications === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-text display-3 text-primary d-block mb-3"></i>
            <h2 class="h5 fw-bold">Belum ada lamaran</h2>
            <p class="text-muted mb-3">Mulai cari lowongan magang yang cocok dan kirim lamaran pertamamu.</p>
            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-primary">
                <i class="bi bi-search me-1"></i>
                Mulai Cari Lowongan
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="d-grid gap-4">
    <?php foreach ($applications as $application): ?>
        <?php
        $applicationId = (int) $application['id'];
        $status = (string) $application['status'];
        $logoUrl = !empty($application['logo']) ? get_file_url((string) $application['logo'], 'company_logos') : 'https://placehold.co/96x96?text=Logo';
        ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <img
                            src="<?= sanitize($logoUrl); ?>"
                            alt="Logo <?= sanitize((string) $application['company_name']); ?>"
                            class="rounded-3 border object-fit-cover"
                            style="width: 72px; height: 72px;"
                        >
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold"><?= sanitize((string) $application['company_name']); ?></span>
                                <?php if ((int) $application['is_verified'] === 1): ?>
                                    <span class="badge bg-success">Terverifikasi</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="h5 fw-bold mb-2"><?= sanitize((string) $application['job_title']); ?></h2>
                            <div class="text-muted small">
                                <i class="bi bi-calendar-check me-1"></i>
                                Melamar <?= format_date((string) $application['applied_at'], 'd M Y H:i'); ?>
                                <span class="mx-2">•</span>
                                <i class="bi bi-clock me-1"></i>
                                Deadline <?= format_date((string) $application['deadline']); ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= sanitize((string) $application['location']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-lg-end">
                        <?= student_applications_status_badge($status); ?>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-lg-5">
                        <?= student_applications_timeline($status); ?>
                    </div>
                    <div class="col-12 col-lg-7">
                        <?php if (in_array($status, ['accepted', 'rejected'], true) && trim((string) ($application['notes'] ?? '')) !== ''): ?>
                            <div class="alert <?= $status === 'accepted' ? 'alert-success' : 'alert-danger'; ?> mb-3">
                                <div class="fw-semibold mb-1">Catatan Perusahaan</div>
                                <blockquote class="mb-0"><?= nl2br(sanitize((string) $application['notes'])); ?></blockquote>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/applications/apply.php?job_id=<?= (int) $application['job_id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>
                                Lihat Lowongan
                            </a>

                            <?php if ($status === 'pending'): ?>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#cancelApplicationModal<?= $applicationId; ?>"
                                >
                                    <i class="bi bi-x-circle me-1"></i>
                                    Batalkan Lamaran
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($status === 'pending'): ?>
            <div class="modal fade" id="cancelApplicationModal<?= $applicationId; ?>" tabindex="-1" aria-labelledby="cancelApplicationModalLabel<?= $applicationId; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="cancelApplicationModalLabel<?= $applicationId; ?>">Batalkan Lamaran</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Yakin ingin membatalkan lamaran untuk lowongan
                            <strong><?= sanitize((string) $application['job_title']); ?></strong>?
                            Setelah dibatalkan, kamu perlu melamar ulang dari halaman lowongan jika berubah pikiran.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tidak</button>
                            <form method="POST" action="<?= rtrim(BASE_URL, '/'); ?>/student/applications/cancel.php" class="d-inline">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="application_id" value="<?= $applicationId; ?>">
                                <button type="submit" class="btn btn-danger">Ya, Batalkan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
