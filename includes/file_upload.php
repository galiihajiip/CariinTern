<?php

require_once __DIR__ . '/../config/config.php';

function validate_file(array $file, string $type = 'document'): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'message' => get_upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)),
        ];
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return [
            'valid' => false,
            'message' => 'File upload tidak valid',
        ];
    }

    if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
        return [
            'valid' => false,
            'message' => 'Ukuran file tidak boleh lebih dari 5MB',
        ];
    }

    $allowedTypes = $type === 'image' ? ALLOWED_IMAGE_TYPES : ALLOWED_DOC_TYPES;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        return [
            'valid' => false,
            'message' => 'Validasi file gagal',
        ];
    }

    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        return [
            'valid' => false,
            'message' => $type === 'image'
                ? 'File harus berupa gambar JPG, PNG, atau WebP'
                : 'File harus berupa PDF',
        ];
    }

    return [
        'valid' => true,
        'message' => 'File valid',
    ];
}

function upload_file(array $file, string $destination_folder, string $prefix = ''): array
{
    $type = infer_upload_type($destination_folder);
    $validation = validate_file($file, $type);

    if (!$validation['valid']) {
        return [
            'success' => false,
            'filename' => '',
            'message' => $validation['message'],
        ];
    }

    $targetDir = get_upload_directory($destination_folder);

    if ($targetDir === null) {
        return [
            'success' => false,
            'filename' => '',
            'message' => 'Folder upload tidak valid',
        ];
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        return [
            'success' => false,
            'filename' => '',
            'message' => 'Folder upload tidak dapat dibuat',
        ];
    }

    $extension = get_extension_from_mime($file['tmp_name'], $type);

    if ($extension === null) {
        return [
            'success' => false,
            'filename' => '',
            'message' => 'Ekstensi file tidak valid',
        ];
    }

    $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
    $filename = sprintf(
        '%s_%d_%s.%s',
        $safePrefix !== '' ? $safePrefix : $type,
        time(),
        bin2hex(random_bytes(8)),
        $extension
    );
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => false,
            'filename' => '',
            'message' => 'File gagal diupload',
        ];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'message' => 'File berhasil diupload',
    ];
}

function delete_file(string $filename, string $folder): bool
{
    $filePath = get_upload_file_path($filename, $folder);

    if ($filePath === null || !is_file($filePath)) {
        return false;
    }

    return unlink($filePath);
}

function get_file_url(string $filename, string $folder): string
{
    $safeFolder = trim(str_replace('\\', '/', $folder), '/');
    $safeFilename = basename($filename);

    if ($safeFolder === '' || $safeFilename === '') {
        return '';
    }

    return rtrim(BASE_URL, '/') . '/uploads/' . rawurlencode($safeFolder) . '/' . rawurlencode($safeFilename);
}

function get_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary upload tidak tersedia',
        UPLOAD_ERR_CANT_WRITE => 'File gagal ditulis ke server',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP',
        default => 'Upload file gagal',
    };
}

function infer_upload_type(string $destination_folder): string
{
    $folder = strtolower($destination_folder);

    return str_contains($folder, 'logo') || str_contains($folder, 'image') || str_contains($folder, 'photo')
        ? 'image'
        : 'document';
}

function get_extension_from_mime(string $tmpName, string $type): ?string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        return null;
    }

    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    $extensions = $type === 'image'
        ? [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ]
        : [
            'application/pdf' => 'pdf',
        ];

    return $extensions[$mimeType] ?? null;
}

function get_upload_directory(string $folder): ?string
{
    $basePath = realpath(UPLOAD_PATH);

    if ($basePath === false) {
        return null;
    }

    $safeFolder = trim(str_replace('\\', '/', $folder), '/');

    if ($safeFolder === '' || str_contains($safeFolder, '..')) {
        return null;
    }

    return $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safeFolder);
}

function get_upload_file_path(string $filename, string $folder): ?string
{
    $directory = get_upload_directory($folder);

    if ($directory === null || basename($filename) !== $filename) {
        return null;
    }

    return $directory . DIRECTORY_SEPARATOR . $filename;
}
