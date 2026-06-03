<?php

define('APP_ENV', 'development');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'internship_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('BASE_URL', 'http://localhost/internship-system');
define('UPLOAD_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_DOC_TYPES', ['application/pdf']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

define('APP_NAME', 'SiMagang - Sistem Pendaftaran Magang');
define('SESSION_TIMEOUT', 3600);
