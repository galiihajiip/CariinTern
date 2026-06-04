#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied. Run from CLI only.');
}

define('BASE_PATH', dirname(__DIR__));
define('CLI_MODE', true);

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/scraper/HttpClient.php';
require_once BASE_PATH . '/scraper/HtmlParser.php';
require_once BASE_PATH . '/scraper/ScraperEngine.php';

$options = getopt('', ['source:', 'all', 'dry-run', 'force']);
$db = Database::getInstance()->getConnection();
$engine = new ScraperEngine($db);

if (isset($options['all'])) {
    $where = 'WHERE is_active = 1';

    if (!isset($options['force'])) {
        $where .= ' AND (last_scraped_at IS NULL OR TIMESTAMPDIFF(HOUR, last_scraped_at, NOW()) >= scrape_interval_hours)';
    }

    $sources = $db->query(
        'SELECT id, name
         FROM scraper_sources ' . $where . '
         ORDER BY id ASC'
    )->fetchAll();

    foreach ($sources as $source) {
        echo '[' . date('Y-m-d H:i:s') . '] Scraping: ' . $source['name'] . PHP_EOL;

        if (isset($options['dry-run'])) {
            echo "  -> Dry run only\n";
            continue;
        }

        $result = $engine->run((int) $source['id']);
        echo '  -> Found: ' . ($result['found'] ?? 0) . ', New: ' . ($result['new'] ?? 0) . ', Duplicate: ' . ($result['duplicate'] ?? 0) . PHP_EOL;
        sleep(rand(3, 8));
    }
} elseif (isset($options['source'])) {
    $sourceId = (int) $options['source'];

    if (isset($options['dry-run'])) {
        echo 'Dry run source #' . $sourceId . PHP_EOL;
    } else {
        print_r($engine->run($sourceId));
    }
} else {
    echo "Usage: php run_scraper.php [--source=ID] [--all] [--dry-run] [--force]\n";
}

echo 'Done at ' . date('Y-m-d H:i:s') . PHP_EOL;
