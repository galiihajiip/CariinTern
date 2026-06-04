<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function trigger_scraper_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    trigger_scraper_json(['success' => false, 'message' => 'Method tidak diizinkan'], 405);
}

if (!is_logged_in() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    trigger_scraper_json(['success' => false, 'message' => 'Akses ditolak'], 403);
}

$sourceId = trim((string) ($_POST['source_id'] ?? ''));
$target = $sourceId === 'all' ? '--all --force' : '--source=' . (int) $sourceId;

if ($sourceId !== 'all' && (int) $sourceId <= 0) {
    trigger_scraper_json(['success' => false, 'message' => 'Source tidak valid'], 422);
}

$phpBinary = PHP_BINARY ?: 'php';
$script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scraper' . DIRECTORY_SEPARATOR . 'run_scraper.php';
$command = '"' . $phpBinary . '" "' . $script . '" ' . $target;

if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
    pclose(popen('start /B "" ' . $command, 'r'));
} else {
    proc_close(proc_open($command . ' > /dev/null 2>&1 &', [], $pipes));
}

trigger_scraper_json([
    'success' => true,
    'message' => $sourceId === 'all' ? 'Semua scraper dijalankan di background' : 'Scraper source #' . (int) $sourceId . ' dijalankan di background',
]);
