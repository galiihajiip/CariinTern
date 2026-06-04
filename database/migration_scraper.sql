USE internship_db;

CREATE TABLE IF NOT EXISTS scraper_sources (
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

CREATE TABLE IF NOT EXISTS scraped_jobs (
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

CREATE TABLE IF NOT EXISTS scraper_logs (
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

INSERT INTO scraper_sources (name, type, url, config, css_selectors, scrape_interval_hours, created_by)
SELECT 'Glints Indonesia', 'website', 'https://glints.com/id/opportunities/jobs/explore?locationName=Indonesia&type=INTERNSHIP',
       '{"requires_js": false, "max_pages": 3}',
       '{"job_container": ".JobCardsc__JobcardContainer", "title": ".JobCardsc__StyledTitle", "company": ".CompanyLink", "location": ".LocationContainer", "url": "a"}',
       12, 1
WHERE NOT EXISTS (SELECT 1 FROM scraper_sources WHERE name = 'Glints Indonesia');

INSERT INTO scraper_sources (name, type, url, config, css_selectors, scrape_interval_hours, created_by)
SELECT 'Info Magang RSS', 'rss', 'https://www.its.ac.id/feed/',
       '{"keyword_filter": ["magang", "internship", "praktek kerja"]}', NULL, 24, 1
WHERE NOT EXISTS (SELECT 1 FROM scraper_sources WHERE name = 'Info Magang RSS');

INSERT INTO scraper_sources (name, type, url, config, css_selectors, scrape_interval_hours, created_by)
SELECT 'Google Search - Magang 2024', 'google_cse', 'https://www.googleapis.com/customsearch/v1',
       '{"api_key": "GANTI_DENGAN_API_KEY", "cx": "GANTI_DENGAN_CX_ID", "query": "lowongan magang mahasiswa Indonesia 2024", "date_restrict": "d7"}',
       NULL, 24, 1
WHERE NOT EXISTS (SELECT 1 FROM scraper_sources WHERE name = 'Google Search - Magang 2024');

INSERT INTO scraper_sources (name, type, url, config, css_selectors, scrape_interval_hours, created_by)
SELECT 'Telegram @infomagangid', 'telegram', 'https://t.me/s/infomagangid',
       '{"method": "web_scrape", "keyword_filter": ["magang", "internship", "deadline"]}', NULL, 4, 1
WHERE NOT EXISTS (SELECT 1 FROM scraper_sources WHERE name = 'Telegram @infomagangid');
