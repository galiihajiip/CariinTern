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
$totalAcceptedApplications = 0;
$latestJobs = [];

try {
    $pdo = Database::getInstance()->getConnection();
    $totalActiveJobs = (int) $pdo->query(
        'SELECT COUNT(*)
         FROM job_listings
         WHERE status = \'open\' AND deadline >= CURDATE()'
    )->fetchColumn();
    $totalCompanies = (int) $pdo->query('SELECT COUNT(*) FROM company_profiles WHERE is_verified = 1')->fetchColumn();
    $totalStudents = (int) $pdo->query('SELECT COUNT(*) FROM student_profiles')->fetchColumn();
    $totalAcceptedApplications = (int) $pdo->query('SELECT COUNT(*) FROM applications WHERE status = \'accepted\'')->fetchColumn();

    $jobsStmt = $pdo->query(
        'SELECT job_listings.id, job_listings.title, job_listings.location, job_listings.deadline,
                company_profiles.company_name,
                internship_categories.name AS category_name
         FROM job_listings
         INNER JOIN company_profiles ON company_profiles.id = job_listings.company_id
         INNER JOIN internship_categories ON internship_categories.id = job_listings.category_id
         WHERE job_listings.status = \'open\'
           AND job_listings.deadline >= CURDATE()
           AND company_profiles.is_verified = 1
         ORDER BY job_listings.created_at DESC, job_listings.id DESC
         LIMIT 3'
    );
    $latestJobs = $jobsStmt->fetchAll();
} catch (PDOException $exception) {
    error_log('Public landing query failed: ' . $exception->getMessage());
}

function landing_company_color(string $companyName): string
{
    $palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6'];
    $index = abs(crc32(strtolower($companyName))) % count($palette);

    return $palette[$index];
}

function landing_company_initials(string $companyName): string
{
    $words = preg_split('/\s+/', trim($companyName)) ?: [];
    $initials = '';

    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'CT';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize(APP_NAME); ?></title>
    <link rel="manifest" href="<?= rtrim(BASE_URL, '/'); ?>/manifest.json">
    <link rel="icon" href="<?= rtrim(BASE_URL, '/'); ?>/favicon.ico" sizes="any">
    <link rel="icon" href="<?= rtrim(BASE_URL, '/'); ?>/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= rtrim(BASE_URL, '/'); ?>/assets/icons/icon-152x152.png">
    <meta name="theme-color" content="#6366f1">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/custom.css" rel="stylesheet">
    <link href="<?= rtrim(BASE_URL, '/'); ?>/assets/css/theme.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
            color: #0f172a;
        }

        .landing-navbar {
            backdrop-filter: blur(18px);
            background: rgba(15, 23, 42, 0.84);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #6366f1, #0ea5e9);
        }

        .hero-section {
            min-height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            padding: 7.5rem 0 5rem;
            overflow: hidden;
            color: #fff;
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.75), rgba(15, 23, 42, 0.75)),
                url("https://images.unsplash.com/photo-1521737852567-6949f3f9f2b5?w=1920&q=80");
            background-size: cover;
            background-position: center;
        }

        .hero-section::after {
            content: "";
            position: absolute;
            inset: auto -10% -30% auto;
            width: 42rem;
            height: 42rem;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.22);
            filter: blur(20px);
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 860px;
            margin-top: 1.75rem;
        }

        .hero-eyebrow {
            display: inline-block !important;
            width: auto;
            max-width: min(100%, 760px);
            white-space: normal !important;
            line-height: 1.55 !important;
            text-align: left;
            overflow-wrap: normal;
            word-break: normal;
            overflow: visible !important;
            vertical-align: top;
            padding-top: 0.7rem !important;
            padding-bottom: 0.7rem !important;
            margin-top: 1.25rem;
            transform: translateY(0.75rem);
        }

        .hero-section .display-3 {
            font-size: clamp(2.25rem, 7vw, 4.5rem);
            line-height: 1.05;
            overflow-wrap: anywhere;
        }

        .hero-section .lead {
            font-size: clamp(1rem, 2vw, 1.25rem);
            max-width: 760px;
        }

        .typewriter {
            color: #bfdbfe;
            min-height: 2.25rem;
            font-size: clamp(1.2rem, 4vw, 1.75rem);
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .typewriter::after {
            content: "|";
            margin-left: 0.2rem;
            animation: blink 0.8s infinite;
        }

        @keyframes blink {
            0%, 49% { opacity: 1; }
            50%, 100% { opacity: 0; }
        }

        .scroll-indicator {
            position: absolute;
            z-index: 2;
            left: 50%;
            bottom: 2rem;
            transform: translateX(-50%);
            animation: bounce 1.5s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translate(-50%, 0); }
            50% { transform: translate(-50%, 12px); }
        }

        .section-padding {
            padding: 6rem 0;
        }

        .section-padding .display-6 {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            line-height: 1.15;
        }

        .stats-section {
            background: linear-gradient(135deg, #eef2ff 0%, #f0f9ff 50%, #ecfdf5 100%);
        }

        .landing-stat-card,
        .feature-card,
        .landing-job-card,
        .testimonial-card {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            overflow: hidden;
            overflow-wrap: anywhere;
        }

        .landing-stat-card .display-5 {
            font-size: clamp(2rem, 6vw, 3rem);
            line-height: 1;
        }

        .landing-stat-card:hover,
        .feature-card:hover,
        .landing-job-card:hover,
        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }

        .feature-card img {
            height: 220px;
            object-fit: cover;
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            color: #fff;
            background: linear-gradient(135deg, #6366f1, #0ea5e9);
            font-size: 2rem;
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.25);
        }

        .company-logo-generated {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 16px;
            color: #fff;
            font-weight: 800;
            letter-spacing: 0.03em;
        }

        .testimonial-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }

        .cta-band {
            color: #fff;
            background:
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.4), transparent 30%),
                linear-gradient(135deg, #0f172a, #312e81);
        }

        @media (max-width: 575.98px) {
            .hero-section {
                min-height: 680px;
                padding: 7rem 0 4.5rem;
                align-items: flex-start;
            }

            .hero-content {
                margin-top: 1rem;
            }

            .hero-eyebrow {
                border-radius: 14px !important;
                font-size: 0.82rem;
                line-height: 1.55 !important;
                padding: 0.7rem 0.75rem !important;
                margin-top: 0.5rem;
                max-width: calc(100vw - 2rem);
                transform: translateY(0.5rem);
            }

            .section-padding {
                padding: 3.5rem 0;
            }

            .brand-mark {
                width: 36px;
                height: 36px;
                border-radius: 12px;
            }

            .feature-card img {
                height: 180px;
            }

            .feature-icon {
                width: 54px;
                height: 54px;
                font-size: 1.5rem;
            }

            .landing-stat-card,
            .feature-card,
            .landing-job-card,
            .testimonial-card {
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top landing-navbar navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= rtrim(BASE_URL, '/'); ?>/index.php">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <span>CariinTern</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#fitur">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lowongan">Lowongan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimoni">Testimoni</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/login.php" class="btn btn-outline-light">Login</a>
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/register.php" class="btn btn-primary">Daftar</a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <section id="beranda" class="hero-section">
            <div class="container">
                <div class="hero-content" data-aos="fade-up">
                    <span class="hero-eyebrow badge rounded-pill bg-white bg-opacity-10 text-white border border-white border-opacity-25 px-3 py-2 mb-4">
                        Platform magang modern untuk mahasiswa dan perusahaan
                    </span>
                    <h1 class="display-3 fw-bold mb-3">Cari peluang magang terbaik dalam satu platform.</h1>
                    <p class="h3 fw-semibold typewriter mb-4" id="typewriterText"></p>
                    <p class="lead text-white-50 mb-5">
                        Temukan lowongan terverifikasi, bangun profil profesional, kirim lamaran, dan pantau status magangmu tanpa ribet.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-search me-1"></i>
                            Mulai Cari Magang
                        </a>
                        <a href="<?= rtrim(BASE_URL, '/'); ?>/register.php?role=company" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-building-add me-1"></i>
                            Daftar Sebagai Perusahaan
                        </a>
                    </div>
                </div>
            </div>
            <a href="#statistik" class="scroll-indicator text-white fs-2" aria-label="Scroll ke statistik">
                <i class="bi bi-chevron-double-down"></i>
            </a>
        </section>

        <section id="statistik" class="section-padding stats-section" data-aos="fade-up">
            <div class="container">
                <div class="text-center mx-auto mb-5" style="max-width: 720px;">
                    <span class="badge rounded-pill text-bg-primary mb-3">Statistik Platform</span>
                    <h2 class="fw-bold display-6">Angka yang terus bertumbuh</h2>
                    <p class="text-muted mb-0">CariinTern membantu mahasiswa menemukan kesempatan magang dan perusahaan menemukan talenta muda.</p>
                </div>
                <div class="row g-4">
                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card bg-white h-100 p-4 text-center">
                            <i class="bi bi-people fs-1 text-primary"></i>
                            <div class="display-5 fw-bold mt-3 landing-counter" data-target="<?= $totalStudents; ?>">0</div>
                            <div class="text-muted">Mahasiswa Terdaftar</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card bg-white h-100 p-4 text-center">
                            <i class="bi bi-building-check fs-1 text-info"></i>
                            <div class="display-5 fw-bold mt-3 landing-counter" data-target="<?= $totalCompanies; ?>">0</div>
                            <div class="text-muted">Perusahaan Mitra</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card bg-white h-100 p-4 text-center">
                            <i class="bi bi-briefcase fs-1 text-success"></i>
                            <div class="display-5 fw-bold mt-3 landing-counter" data-target="<?= $totalActiveJobs; ?>">0</div>
                            <div class="text-muted">Lowongan Aktif</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card bg-white h-100 p-4 text-center">
                            <i class="bi bi-award fs-1 text-warning"></i>
                            <div class="display-5 fw-bold mt-3 landing-counter" data-target="<?= $totalAcceptedApplications; ?>">0</div>
                            <div class="text-muted">Penempatan Berhasil</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="fitur" class="section-padding bg-white" data-aos="fade-up">
            <div class="container">
                <div class="text-center mx-auto mb-5" style="max-width: 720px;">
                    <span class="badge rounded-pill text-bg-primary mb-3">Fitur Utama</span>
                    <h2 class="fw-bold display-6">Didesain untuk proses magang yang lebih jelas</h2>
                    <p class="text-muted mb-0">Dari pencarian lowongan sampai pemantauan lamaran, semua dibuat simpel dan responsif.</p>
                </div>
                <div class="row g-4">
                    <div class="col-12 col-lg-4">
                        <div class="card feature-card h-100">
                            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=900&q=80" class="card-img-top" alt="Kolaborasi tim">
                            <div class="card-body p-4">
                                <div class="feature-icon mb-3"><i class="bi bi-people-fill"></i></div>
                                <h3 class="h4 fw-bold">Kolaborasi Kampus & Perusahaan</h3>
                                <p class="text-muted mb-0">Hubungkan mahasiswa dengan perusahaan mitra melalui alur pendaftaran yang rapi.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card feature-card h-100">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=900&q=80" class="card-img-top" alt="Profesional muda">
                            <div class="card-body p-4">
                                <div class="feature-icon mb-3"><i class="bi bi-person-vcard-fill"></i></div>
                                <h3 class="h4 fw-bold">Profil Profesional</h3>
                                <p class="text-muted mb-0">Lengkapi CV, transkrip, dan data diri untuk melamar lowongan lebih cepat.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card feature-card h-100">
                            <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=900&q=80" class="card-img-top" alt="Analitik pekerjaan">
                            <div class="card-body p-4">
                                <div class="feature-icon mb-3"><i class="bi bi-graph-up-arrow"></i></div>
                                <h3 class="h4 fw-bold">Pantau Status Lamaran</h3>
                                <p class="text-muted mb-0">Lihat progres lamaran dari pending, review, diterima, sampai ditolak.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="lowongan" class="section-padding" data-aos="fade-up">
            <div class="container">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-5">
                    <div>
                        <span class="badge rounded-pill text-bg-primary mb-3">Lowongan Terbaru</span>
                        <h2 class="fw-bold display-6 mb-2">Kesempatan baru minggu ini</h2>
                        <p class="text-muted mb-0">Lowongan dari perusahaan terverifikasi, siap kamu lamar sekarang.</p>
                    </div>
                    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/jobs/index.php" class="btn btn-outline-primary">
                        Lihat Semua Lowongan
                    </a>
                </div>

                <?php if ($latestJobs === []): ?>
                    <div class="card landing-job-card">
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
                        $companyName = (string) $job['company_name'];
                        $companyColor = landing_company_color($companyName);
                        ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <article class="card landing-job-card h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start gap-3 mb-4">
                                        <div class="company-logo-generated" style="background: <?= sanitize($companyColor); ?>;">
                                            <?= sanitize(landing_company_initials($companyName)); ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-truncate"><?= sanitize($companyName); ?></div>
                                            <div class="text-muted small">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <?= sanitize((string) $job['location']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <h3 class="h5 fw-bold mb-3"><?= sanitize((string) $job['title']); ?></h3>
                                    <div class="d-flex flex-wrap gap-2 mb-4">
                                        <span class="badge text-bg-primary"><?= sanitize((string) $job['category_name']); ?></span>
                                        <span class="badge text-bg-light border">
                                            Deadline <?= sanitize(format_date((string) $job['deadline'])); ?>
                                        </span>
                                    </div>
                                    <a href="<?= rtrim(BASE_URL, '/'); ?>/student/applications/apply.php?job_id=<?= (int) $job['id']; ?>" class="btn btn-primary w-100">
                                        Lihat Detail
                                    </a>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="testimoni" class="section-padding bg-white" data-aos="fade-up">
            <div class="container">
                <div class="text-center mx-auto mb-5" style="max-width: 720px;">
                    <span class="badge rounded-pill text-bg-primary mb-3">Testimoni</span>
                    <h2 class="fw-bold display-6">Cerita pengguna CariinTern</h2>
                </div>
                <div class="row g-4">
                    <div class="col-12 col-lg-4">
                        <div class="testimonial-card bg-white h-100 p-4">
                            <img src="https://i.pravatar.cc/80?img=1" class="testimonial-avatar mb-3" alt="Avatar testimoni 1">
                            <p class="text-muted">"CariinTern bikin proses cari magang lebih jelas. Saya bisa pantau status lamaran tanpa harus tanya berkali-kali."</p>
                            <div class="fw-bold">Galih Aji Pangestu</div>
                            <div class="small text-muted">UPN "Veteran" Jawa Timur</div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="testimonial-card bg-white h-100 p-4">
                            <img src="https://i.pravatar.cc/80?img=2" class="testimonial-avatar mb-3" alt="Avatar testimoni 2">
                            <p class="text-muted">"Profil dan dokumen tersimpan rapi. Melamar lowongan berikutnya jadi jauh lebih cepat."</p>
                            <div class="fw-bold">Fidelia Hahas Asabela</div>
                            <div class="small text-muted">UPN "Veteran" Jawa Timur</div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="testimonial-card bg-white h-100 p-4">
                            <img src="https://i.pravatar.cc/80?img=3" class="testimonial-avatar mb-3" alt="Avatar testimoni 3">
                            <p class="text-muted">"Sebagai perusahaan, kami lebih mudah melihat pelamar dan mengatur status seleksi."</p>
                            <div class="fw-bold">Mohammad Satria Putra Wicaksono</div>
                            <div class="small text-muted">UPN "Veteran" Jawa Timur</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-padding cta-band" data-aos="fade-up">
            <div class="container text-center">
                <h2 class="fw-bold display-6 mb-3">Siap mulai perjalanan magangmu?</h2>
                <p class="text-white-50 mb-4">Buat akun CariinTern dan temukan kesempatan yang cocok untuk langkah karirmu berikutnya.</p>
                <a href="<?= rtrim(BASE_URL, '/'); ?>/register.php" class="btn btn-light btn-lg px-4">Daftar Sekarang</a>
            </div>
        </section>
    </main>

    <footer class="bg-dark text-white py-5">
        <div class="container d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="d-flex align-items-center gap-2 fw-bold">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <span>CariinTern</span>
            </div>
            <div class="text-white-50">&copy; <?= date('Y'); ?> <?= sanitize(APP_NAME); ?>. Semua hak dilindungi.</div>
        </div>
    </footer>

    <script src="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });

        const typewriterWords = [
            'Temukan Magang Impianmu',
            'Bangun Karir Profesional',
            'Mulai dari Sini'
        ];
        const typewriterElement = document.getElementById('typewriterText');
        let wordIndex = 0;
        let charIndex = 0;
        let deleting = false;

        function runTypewriter() {
            const currentWord = typewriterWords[wordIndex];
            typewriterElement.textContent = currentWord.slice(0, charIndex);

            if (!deleting && charIndex < currentWord.length) {
                charIndex += 1;
                setTimeout(runTypewriter, 70);
                return;
            }

            if (!deleting && charIndex === currentWord.length) {
                deleting = true;
                setTimeout(runTypewriter, 1400);
                return;
            }

            if (deleting && charIndex > 0) {
                charIndex -= 1;
                setTimeout(runTypewriter, 38);
                return;
            }

            deleting = false;
            wordIndex = (wordIndex + 1) % typewriterWords.length;
            setTimeout(runTypewriter, 250);
        }

        runTypewriter();

        const counters = document.querySelectorAll('.landing-counter');
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const counter = entry.target;
                const target = Number(counter.dataset.target || 0);
                const duration = 1200;
                const start = performance.now();

                function animate(now) {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    counter.textContent = Math.floor(eased * target).toLocaleString('id-ID');

                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        counter.textContent = target.toLocaleString('id-ID');
                    }
                }

                requestAnimationFrame(animate);
                observer.unobserve(counter);
            });
        }, { threshold: 0.35 });

        counters.forEach((counter) => counterObserver.observe(counter));
    </script>
</body>
</html>
