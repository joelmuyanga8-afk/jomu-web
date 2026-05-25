<?php

if (!function_exists('handleUploadFailure')) {
    function handleUploadFailure($message) {
        global $uploadErrorRedirect;

        if (!empty($uploadErrorRedirect)) {
            header('Location: ' . $uploadErrorRedirect . '?error=' . urlencode($message));
            exit();
        }

        echo json_encode(['success' => false, 'error' => $message]);
        exit();
    }
}

if (!function_exists('processUploadedMediaFile')) {
    function processUploadedMediaFile(array $file, array $allowedTypes, string $uploadDir): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            handleUploadFailure('Invalid upload request.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'This video is too large for the current server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'This file is too large for the current form upload limit.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted before it finished.',
                UPLOAD_ERR_NO_FILE => 'Please choose an image or video to upload.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
                UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
            ];

            handleUploadFailure($uploadErrors[$file['error']] ?? 'Upload failed.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            handleUploadFailure('Invalid uploaded file.');
        }

        if ($file['size'] > 20 * 1024 * 1024) {
            handleUploadFailure('File too large.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);

        if ($detectedMime === false) {
            handleUploadFailure('Unable to validate file type.');
        }

        $isAllowedType = false;
        foreach ($allowedTypes as $allowedType) {
            if (substr($allowedType, -2) === '/*') {
                $prefix = substr($allowedType, 0, -1);
                if (strpos($detectedMime, $prefix) === 0) {
                    $isAllowedType = true;
                    break;
                }
                continue;
            }

            if ($detectedMime === $allowedType) {
                $isAllowedType = true;
                break;
            }
        }

        if (!$isAllowedType) {
            handleUploadFailure('Invalid file.');
        }

        $dangerousExtensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh',
            'exe', 'bat', 'cmd', 'com', 'msi', 'js', 'html', 'htm', 'svg', 'shtml'
        ];

        $originalExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeOriginalExtension = preg_replace('/[^a-z0-9]/', '', $originalExtension);

        $mimeExtensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/x-flv' => 'flv',
            'video/3gpp' => '3gp',
            'video/3gpp2' => '3g2',
            'video/ogg' => 'ogv',
            'video/mpeg' => 'mpeg',
            'video/x-matroska' => 'mkv',
        ];

        $extension = $mimeExtensionMap[$detectedMime] ?? null;

        if ($extension === null) {
            if ($safeOriginalExtension !== '' && !in_array($safeOriginalExtension, $dangerousExtensions, true)) {
                $extension = $safeOriginalExtension;
            } else {
                $mimeSubtype = strtolower(substr(strrchr($detectedMime, '/'), 1));
                $extension = preg_replace('/[^a-z0-9]/', '', $mimeSubtype);
            }
        }

        if ($extension === '' || in_array($extension, $dangerousExtensions, true)) {
            handleUploadFailure('Unsafe file extension.');
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newFileName = uniqid('media_', true) . '.' . $extension;
        $targetPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            handleUploadFailure('Failed to upload file.');
        }

        return [
            'targetPath' => $targetPath,
            'detectedMime' => $detectedMime,
        ];
    }
}

if (isset($file, $allowedTypes, $uploadDir)) {
    $uploadResult = processUploadedMediaFile($file, $allowedTypes, $uploadDir);
    $targetPath = $uploadResult['targetPath'];
    $detectedMime = $uploadResult['detectedMime'];
}
