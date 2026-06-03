<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/file_upload.php';

if (is_logged_in()) {
    redirect(get_dashboard_url((string) ($_SESSION['user_role'] ?? '')));
}

$totalActiveJobs = 0;
$totalCompanies = 0;
$totalStudents = 0;
$latestJobs = [];
$categories = [];

try {
    $pdo = Database::getInstance()->getConnection();

    $totalActiveJobs = (int) $pdo->query(
        'SELECT COUNT(*)
         FROM job_listings
         WHERE status = \'open\' AND deadline >= CURDATE()'
    )->fetchColumn();

    $totalCompanies = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles')->fetchColumn();
    $totalStudents = (int) $pdo->query('SELECT COUNT(*) FROM student_profiles')->fetchColumn();

    $categoryStmt = $pdo->query(
        'SELECT id, name, slug, description
         FROM internship_categories
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $categories = $categoryStmt->fetchAll();

    $jobsStmt = $pdo->query(
        'SELECT job_listings.id, job_listings.title, job_listings.description, job_listings.location,
                job_listings.quota, job_listings.deadline, job_listings.created_at,
                company_profiles.company_name, company_profiles.logo, company_profiles.is_verified,
                internship_categories.name AS category_name,
                COALESCE(accepted_counts.accepted_count, 0) AS accepted_count
         FROM job_listings
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
         LEFT JOIN (
             SELECT job_id, COUNT(*) AS accepted_count
             FROM applications
             WHERE status = \'accepted\'
             GROUP BY job_id
         ) accepted_counts ON accepted_counts.job_id = job_listings.id
         WHERE job_listings.status = \'open\'
           AND job_listings.deadline >= CURDATE()
           AND company_profiles.is_verified = 1
         ORDER BY job_listings.created_at DESC, job_listings.id DESC
         LIMIT 6'
    );
    $latestJobs = $jobsStmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Public landing query failed: ' . $exception->getMessage());
}

$categoryIcons = [
    'teknologi' => 'bi-cpu',
    'bisnis' => 'bi-briefcase',
    'desain' => 'bi-palette',
    'engineering' => 'bi-gear',
];

function public_category_icon(array $category, array $iconMap): string
{
    $slug = strtolower((string) ($category['slug'] ?? ''));

    return $iconMap[$slug] ?? 'bi-tags';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CariinTern — <?= sanitize(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9Oer+R4sF86dIHNDz4JxQmK2h5ZC7pERxKED7ZtGh6y9" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/custom.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
        }

        .landing-navbar {
            backdrop-filter: blur(18px);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(222, 226, 230, 0.75);
        }

        .hero-section {
            position: relative;
            overflow: hidden;
            padding: 8rem 0 5rem;
            background:
                radial-gradient(circle at top left, rgba(13, 110, 253, 0.18), transparent 35%),
                linear-gradient(135deg, #ffffff 0%, #eef5ff 100%);
        }

        .hero-section::after {
            content: "";
            position: absolute;
            right: -8rem;
            top: 6rem;
            width: 24rem;
            height: 24rem;
            border-radius: 50%;
            background: rgba(13, 110, 253, 0.08);
        }

        .hero-card {
            position: relative;
            z-index: 1;
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 1rem 3rem rgba(13, 110, 253, 0.14);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            color: #fff;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
        }

        .section-padding {
            padding: 5rem 0;
        }

        .category-card,
        .landing-job-card,
        .stat-panel {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 0.75rem 2rem rgba(15, 23, 42, 0.07);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .category-card:hover,
        .landing-job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.1);
        }

        .category-icon {
            width: 54px;
            height: 54px;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef5ff;
            color: #0d6efd;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top landing-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= rtrim(BASE_URL, '/'); ?>/index.php">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <span>Cariin<span class="text-primary">Tern</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lowongan">Lowongan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tentang">Tentang</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/login.php" class="btn btn-outline-primary">Login</a>
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/register.php" class="btn btn-primary">Daftar</a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <section id="beranda" class="hero-section">
            <div class="container position-relative">
                <div class="row align-items-center g-5">
                    <div class="col-12 col-lg-7">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 mb-3">
                            Platform pendaftaran magang kampus
                        </span>
                        <h1 class="display-4 fw-bold mb-3">Cari lowongan magang lebih cepat dengan CariinTern.</h1>
                        <p class="lead text-muted mb-4">
                            Temukan program magang dari perusahaan terverifikasi, lengkapi profil, kirim lamaran, dan pantau statusnya dalam satu tempat.
                        </p>
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-search me-1"></i>
                                Cari Lowongan
                            </a>
                            <a href="<?= rtrim(BASE_URL, '/'); ?>/register.php" class="btn btn-outline-primary btn-lg">
                                Daftar Sebagai Mahasiswa
                            </a>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="card hero-card">
                            <div class="card-body p-4 p-xl-5">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="brand-mark"><i class="bi bi-graph-up-arrow"></i></div>
                                    <div>
                                        <div class="fw-bold">Statistik Platform</div>
                                        <div class="text-muted small">Update langsung dari database</div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="stat-panel bg-primary text-white p-4">
                                            <div class="small opacity-75">Lowongan Aktif</div>
                                            <div class="display-6 fw-bold mb-0 counter" data-target="<?= $totalActiveJobs; ?>">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-panel bg-white p-4">
                                            <div class="small text-muted">Perusahaan</div>
                                            <div class="h2 fw-bold mb-0 counter" data-target="<?= $totalCompanies; ?>">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-panel bg-white p-4">
                                            <div class="small text-muted">Mahasiswa</div>
                                            <div class="h2 fw-bold mb-0 counter" data-target="<?= $totalStudents; ?>">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="tentang" class="section-padding bg-white">
            <div class="container">
                <div class="text-center mx-auto mb-5" style="max-width: 680px;">
                    <span class="badge bg-primary-subtle text-primary rounded-pill mb-2">Kategori</span>
                    <h2 class="fw-bold">Jelajahi bidang magang populer</h2>
                    <p class="text-muted mb-0">Pilih kategori yang paling sesuai dengan minat, jurusan, dan rencana kariermu.</p>
                </div>
                <div class="row g-4">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php?category_id=<?= (int) $category['id']; ?>" class="text-decoration-none text-dark">
                                <div class="card category-card h-100">
                                    <div class="card-body p-4">
                                        <div class="category-icon mb-3">
                                            <i class="bi <?= sanitize(public_category_icon($category, $categoryIcons)); ?>"></i>
                                        </div>
                                        <h3 class="h5 fw-bold mb-2"><?= sanitize((string) $category['name']); ?></h3>
                                        <p class="text-muted small mb-0"><?= sanitize(truncate_text((string) ($category['description'] ?? 'Temukan lowongan magang sesuai kategori ini.'), 85)); ?></p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="lowongan" class="section-padding">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
                    <div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill mb-2">Lowongan Terbaru</span>
                        <h2 class="fw-bold mb-1">Kesempatan magang yang baru dibuka</h2>
                        <p class="text-muted mb-0">Daftar lowongan dari perusahaan terverifikasi.</p>
                    </div>
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-outline-primary">
                        Lihat Semua Lowongan
                    </a>
                </div>

                <?php if ($latestJobs === []): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-briefcase display-4 text-primary d-block mb-3"></i>
                            <h3 class="h5 fw-bold">Belum ada lowongan aktif</h3>
                            <p class="text-muted mb-0">Silakan cek kembali nanti.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <?php foreach ($latestJobs as $job): ?>
                        <?php
                        $quota = (int) $job['quota'];
                        $remainingQuota = max(0, $quota - (int) $job['accepted_count']);
                        $logoUrl = !empty($job['logo']) ? get_file_url((string) $job['logo'], 'company_logos') : 'https://placehold.co/96x96?text=Logo';
                        ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card landing-job-card h-100">
                                <div class="card-body d-flex flex-column p-4">
                                    <div class="d-flex align-items-start gap-3 mb-3">
                                        <img
                                            src="<?= sanitize($logoUrl); ?>"
                                            alt="Logo <?= sanitize((string) $job['company_name']); ?>"
                                            class="rounded-3 border object-fit-cover"
                                            style="width: 64px; height: 64px;"
                                        >
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate"><?= sanitize((string) $job['company_name']); ?></div>
                                            <span class="badge <?= (int) $job['is_verified'] === 1 ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?= (int) $job['is_verified'] === 1 ? 'Terverifikasi' : 'Belum Verifikasi'; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <h3 class="h5 fw-bold mb-2"><?= sanitize((string) $job['title']); ?></h3>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-primary-subtle text-primary"><?= sanitize((string) $job['category_name']); ?></span>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            <?= sanitize((string) $job['location']); ?>
                                        </span>
                                    </div>
                                    <p class="text-muted small flex-grow-1"><?= sanitize(truncate_text((string) $job['description'], 120)); ?></p>
                                    <div class="row g-2 small mb-3">
                                        <div class="col-6">
                                            <div class="text-muted">Kuota Tersisa</div>
                                            <div class="fw-semibold <?= $remainingQuota <= 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?= number_format($remainingQuota); ?>/<?= number_format($quota); ?>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <div class="text-muted">Deadline</div>
                                            <div class="fw-semibold"><?= format_date((string) $job['deadline']); ?></div>
                                        </div>
                                    </div>
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/applications/apply.php?job_id=<?= (int) $job['id']; ?>" class="btn btn-primary mt-auto">
                                        Lihat Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section-padding bg-white">
            <div class="container">
                <div class="row g-4 text-center">
                    <div class="col-12 col-md-4">
                        <div class="stat-panel p-4 h-100">
                            <i class="bi bi-briefcase text-primary fs-1"></i>
                            <div class="display-5 fw-bold counter mt-2" data-target="<?= $totalActiveJobs; ?>">0</div>
                            <div class="text-muted">Lowongan Aktif</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="stat-panel p-4 h-100">
                            <i class="bi bi-building text-primary fs-1"></i>
                            <div class="display-5 fw-bold counter mt-2" data-target="<?= $totalCompanies; ?>">0</div>
                            <div class="text-muted">Perusahaan Terdaftar</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="stat-panel p-4 h-100">
                            <i class="bi bi-people text-primary fs-1"></i>
                            <div class="display-5 fw-bold counter mt-2" data-target="<?= $totalStudents; ?>">0</div>
                            <div class="text-muted">Mahasiswa Terdaftar</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-6">
                    <div class="d-flex align-items-center gap-2 fw-bold mb-2">
                        <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                        <span>CariinTern</span>
                    </div>
                    <p class="text-white-50 mb-0">&copy; <?= date('Y'); ?> CariinTern. Semua hak dilindungi.</p>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="d-flex justify-content-lg-end gap-3">
                        <a href="#" class="text-white-50 fs-4" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white-50 fs-4" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="text-white-50 fs-4" aria-label="GitHub"><i class="bi bi-github"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        const counters = document.querySelectorAll('.counter');
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const counter = entry.target;
                const target = Number(counter.dataset.target || 0);
                const duration = 1000;
                const start = performance.now();

                function animate(now) {
                    const progress = Math.min((now - start) / duration, 1);
                    counter.textContent = Math.floor(progress * target).toLocaleString('id-ID');

                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        counter.textContent = target.toLocaleString('id-ID');
                    }
                }

                requestAnimationFrame(animate);
                observer.unobserve(counter);
            });
        }, { threshold: 0.4 });

        counters.forEach((counter) => counterObserver.observe(counter));
    </script>
</body>
</html>
