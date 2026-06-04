<?php

define('APP_ENV', 'production');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
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

define('APP_NAME', 'CariinTern');
define('SESSION_TIMEOUT', 3600);

define('VAPID_SUBJECT', 'mailto:admin@internship.com');
define('VAPID_PUBLIC_KEY', 'BLR9NJM7aT01ohZcb0K1Trna9xSvU2oGg0JTSh-1So4qHi56rO_dH96DVHDjd-yMnl7VjK-8Lz3D1Rrs6GnW6gc');
define('VAPID_PRIVATE_KEY', 'C927TbVIa9OGUoDwmQ665O-u2KxycM8EZHg8aDqoUVQ');
