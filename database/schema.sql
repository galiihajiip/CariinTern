CREATE DATABASE IF NOT EXISTS internship_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE internship_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS scraper_logs;
DROP TABLE IF EXISTS scraped_jobs;
DROP TABLE IF EXISTS scraper_sources;
DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS job_listings;
DROP TABLE IF EXISTS student_profiles;
DROP TABLE IF EXISTS company_profiles;
DROP TABLE IF EXISTS internship_categories;
DROP TABLE IF EXISTS study_programs;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'company', 'student') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    avatar VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    faculty VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_study_programs_code (code),
    KEY idx_study_programs_is_active (is_active),
    KEY idx_study_programs_faculty (faculty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE internship_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_internship_categories_slug (slug),
    KEY idx_internship_categories_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    industry VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    website VARCHAR(200) NULL DEFAULT NULL,
    logo VARCHAR(255) NULL DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    verified_by INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_company_profiles_user_id (user_id),
    KEY idx_company_profiles_verified_by (verified_by),
    KEY idx_company_profiles_is_verified (is_verified),
    KEY idx_company_profiles_industry (industry),
    CONSTRAINT fk_company_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_company_profiles_verified_by
        FOREIGN KEY (verified_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    program_id INT UNSIGNED NOT NULL,
    semester INT NOT NULL,
    gpa DECIMAL(3,2) NOT NULL,
    cv_file VARCHAR(255) NULL DEFAULT NULL,
    transcript_file VARCHAR(255) NULL DEFAULT NULL,
    profile_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_profiles_user_id (user_id),
    UNIQUE KEY unique_student_profiles_student_id (student_id),
    KEY idx_student_profiles_program_id (program_id),
    KEY idx_student_profiles_profile_completed (profile_completed),
    CONSTRAINT fk_student_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_student_profiles_program
        FOREIGN KEY (program_id) REFERENCES study_programs (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_student_profiles_semester CHECK (semester BETWEEN 1 AND 14),
    CONSTRAINT chk_student_profiles_gpa CHECK (gpa BETWEEN 0.00 AND 4.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_listings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    location VARCHAR(100) NOT NULL,
    quota INT NOT NULL DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    deadline DATE NOT NULL,
    status ENUM('open', 'closed', 'draft') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_job_listings_company_id (company_id),
    KEY idx_job_listings_category_id (category_id),
    KEY idx_job_listings_status (status),
    KEY idx_job_listings_deadline (deadline),
    KEY idx_job_listings_location (location),
    CONSTRAINT fk_job_listings_company
        FOREIGN KEY (company_id) REFERENCES company_profiles (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_job_listings_category
        FOREIGN KEY (category_id) REFERENCES internship_categories (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_job_listings_quota CHECK (quota > 0),
    CONSTRAINT chk_job_listings_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    cover_letter TEXT NULL,
    status ENUM('pending', 'review', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT UNSIGNED NULL DEFAULT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (student_id, job_id),
    KEY idx_applications_student_id (student_id),
    KEY idx_applications_job_id (job_id),
    KEY idx_applications_status (status),
    KEY idx_applications_reviewed_by (reviewed_by),
    CONSTRAINT fk_applications_student
        FOREIGN KEY (student_id) REFERENCES student_profiles (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_applications_job
        FOREIGN KEY (job_id) REFERENCES job_listings (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_applications_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_logs_user_id (user_id),
    KEY idx_activity_logs_action (action),
    KEY idx_activity_logs_is_read (is_read),
    KEY idx_activity_logs_created_at (created_at),
    CONSTRAINT fk_activity_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(500) NOT NULL,
    auth VARCHAR(100) NOT NULL,
    user_agent VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_push_subscriptions_endpoint (endpoint_hash),
    KEY idx_push_subscriptions_user_id (user_id),
    CONSTRAINT fk_push_subscriptions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scraper_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('website', 'rss', 'telegram', 'google_cse') NOT NULL,
    url TEXT NOT NULL,
    config JSON NULL,
    css_selectors JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_scraped_at TIMESTAMP NULL DEFAULT NULL,
    scrape_interval_hours INT NOT NULL DEFAULT 6,
    total_scraped INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_scraper_sources_type (type),
    KEY idx_scraper_sources_is_active (is_active),
    KEY idx_scraper_sources_created_by (created_by),
    CONSTRAINT fk_scraper_sources_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scraped_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT UNSIGNED NOT NULL,
    external_id VARCHAR(255) NULL DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    company_name VARCHAR(150) NULL DEFAULT NULL,
    location VARCHAR(100) NULL DEFAULT NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    deadline DATE NULL,
    source_url TEXT NOT NULL,
    category_id INT UNSIGNED NULL DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'duplicate') NOT NULL DEFAULT 'pending',
    approved_by INT UNSIGNED NULL DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    job_listing_id INT UNSIGNED NULL DEFAULT NULL,
    raw_data JSON NULL,
    scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_scraped_jobs_external (source_id, external_id),
    KEY idx_scraped_jobs_source_id (source_id),
    KEY idx_scraped_jobs_category_id (category_id),
    KEY idx_scraped_jobs_status (status),
    KEY idx_scraped_jobs_approved_by (approved_by),
    KEY idx_scraped_jobs_job_listing_id (job_listing_id),
    CONSTRAINT fk_scraped_jobs_source
        FOREIGN KEY (source_id) REFERENCES scraper_sources (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_scraped_jobs_category
        FOREIGN KEY (category_id) REFERENCES internship_categories (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_scraped_jobs_approved_by
        FOREIGN KEY (approved_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_scraped_jobs_job_listing
        FOREIGN KEY (job_listing_id) REFERENCES job_listings (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scraper_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT UNSIGNED NOT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('running', 'success', 'failed', 'partial') NOT NULL DEFAULT 'running',
    items_found INT NOT NULL DEFAULT 0,
    items_new INT NOT NULL DEFAULT 0,
    items_duplicate INT NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    KEY idx_scraper_logs_source_id (source_id),
    KEY idx_scraper_logs_status (status),
    KEY idx_scraper_logs_started_at (started_at),
    CONSTRAINT fk_scraper_logs_source
        FOREIGN KEY (source_id) REFERENCES scraper_sources (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password, role, is_active)
VALUES
    ('Administrator', 'admin@internship.com', '$2y$10$tZHng9FJhQIM86d3d4SVT.tvgdjJ/uUYsUbgsKWfhQA3ys0g8iNgO', 'admin', 1);

INSERT INTO study_programs (name, code, faculty, is_active)
VALUES
    ('Teknik Informatika', 'TI', 'Fakultas Teknik', 1),
    ('Sistem Informasi', 'SI', 'Fakultas Ilmu Komputer', 1),
    ('Manajemen', 'MJ', 'Fakultas Ekonomi dan Bisnis', 1);

INSERT INTO internship_categories (name, slug, description, is_active)
VALUES
    ('Teknologi', 'teknologi', 'Kategori magang untuk bidang teknologi informasi dan software development.', 1),
    ('Bisnis', 'bisnis', 'Kategori magang untuk bidang bisnis, administrasi, dan pengembangan usaha.', 1),
    ('Desain', 'desain', 'Kategori magang untuk bidang desain grafis, UI/UX, dan kreatif visual.', 1),
    ('Engineering', 'engineering', 'Kategori magang untuk bidang rekayasa, manufaktur, dan operasional teknis.', 1);

INSERT INTO scraper_sources (name, type, url, config, css_selectors, scrape_interval_hours, created_by)
VALUES
    (
        'Glints Indonesia',
        'website',
        'https://glints.com/id/opportunities/jobs/explore?locationName=Indonesia&type=INTERNSHIP',
        '{"requires_js": false, "max_pages": 3}',
        '{"job_container": ".JobCardsc__JobcardContainer", "title": ".JobCardsc__StyledTitle", "company": ".CompanyLink", "location": ".LocationContainer", "url": "a"}',
        12,
        1
    ),
    (
        'Info Magang RSS',
        'rss',
        'https://www.its.ac.id/feed/',
        '{"keyword_filter": ["magang", "internship", "praktek kerja"]}',
        NULL,
        24,
        1
    ),
    (
        'Google Search - Magang 2024',
        'google_cse',
        'https://www.googleapis.com/customsearch/v1',
        '{"api_key": "GANTI_DENGAN_API_KEY", "cx": "GANTI_DENGAN_CX_ID", "query": "lowongan magang mahasiswa Indonesia 2024", "date_restrict": "d7"}',
        NULL,
        24,
        1
    ),
    (
        'Telegram @infomagangid',
        'telegram',
        'https://t.me/s/infomagangid',
        '{"method": "web_scrape", "keyword_filter": ["magang", "internship", "deadline"]}',
        NULL,
        4,
        1
    );
