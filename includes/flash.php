<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash'][$type])) {
        $_SESSION['flash'][$type] = [];
    }

    $_SESSION['flash'][$type][] = $message;
}

function get_flash(string $type): array
{
    if (!isset($_SESSION['flash'][$type])) {
        return [];
    }

    $messages = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);

    return $messages;
}

function has_flash(string $type): bool
{
    return !empty($_SESSION['flash'][$type]);
}

function display_flash(): string
{
    $alertClasses = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info',
    ];

    $html = '';

    foreach ($alertClasses as $type => $alertClass) {
        $messages = get_flash($type);

        foreach ($messages as $message) {
            $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            $html .= sprintf(
                '<div class="alert %s alert-dismissible fade show auto-dismiss" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>',
                $alertClass,
                $safeMessage
            );
        }
    }

    return $html;
}
