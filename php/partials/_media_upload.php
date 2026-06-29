<?php

if (!defined('JOMU_IMAGE_UPLOAD_LIMIT_BYTES')) {
    define('JOMU_IMAGE_UPLOAD_LIMIT_BYTES', 10 * 1024 * 1024);
}

if (!defined('JOMU_VIDEO_UPLOAD_LIMIT_BYTES')) {
    define('JOMU_VIDEO_UPLOAD_LIMIT_BYTES', 200 * 1024 * 1024);
}

if (!defined('JOMU_VIDEO_UPLOAD_LIMIT_SECONDS')) {
    define('JOMU_VIDEO_UPLOAD_LIMIT_SECONDS', 180);
}

if (!function_exists('getUploadLimitForMimeType')) {
    function getUploadLimitForMimeType(string $mimeType): int
    {
        if (strpos($mimeType, 'video/') === 0) {
            return JOMU_VIDEO_UPLOAD_LIMIT_BYTES;
        }

        if (strpos($mimeType, 'image/') === 0) {
            return JOMU_IMAGE_UPLOAD_LIMIT_BYTES;
        }

        return 0;
    }
}

if (!function_exists('formatUploadLimitMb')) {
    function formatUploadLimitMb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('formatUploadDurationSeconds')) {
    function formatUploadDurationSeconds(float $seconds): string
    {
        $totalSeconds = (int) ceil($seconds);
        $minutes = intdiv($totalSeconds, 60);
        $remainingSeconds = $totalSeconds % 60;

        return $minutes > 0 ? $minutes . 'm ' . $remainingSeconds . 's' : $remainingSeconds . 's';
    }
}

if (!function_exists('readBigEndianUInt32')) {
    function readBigEndianUInt32($handle): ?int
    {
        $bytes = fread($handle, 4);
        if (!is_string($bytes) || strlen($bytes) !== 4) {
            return null;
        }

        $unpacked = unpack('Nvalue', $bytes);
        return isset($unpacked['value']) ? (int) $unpacked['value'] : null;
    }
}

if (!function_exists('readBigEndianUInt64')) {
    function readBigEndianUInt64($handle): ?float
    {
        $bytes = fread($handle, 8);
        if (!is_string($bytes) || strlen($bytes) !== 8) {
            return null;
        }

        $unpacked = unpack('Nhigh/Nlow', $bytes);
        if (!isset($unpacked['high'], $unpacked['low'])) {
            return null;
        }

        return ((float) $unpacked['high'] * 4294967296.0) + (float) $unpacked['low'];
    }
}

if (!function_exists('readMp4MvhdDurationSeconds')) {
    function readMp4MvhdDurationSeconds($handle, int $startOffset, int $boxEnd): ?float
    {
        if (fseek($handle, $startOffset) !== 0) {
            return null;
        }

        $versionAndFlags = fread($handle, 4);
        if (!is_string($versionAndFlags) || strlen($versionAndFlags) !== 4) {
            return null;
        }

        $version = ord($versionAndFlags[0]);
        if ($version === 1) {
            if (ftell($handle) + 28 > $boxEnd || fseek($handle, 16, SEEK_CUR) !== 0) {
                return null;
            }

            $timescale = readBigEndianUInt32($handle);
            $duration = readBigEndianUInt64($handle);
        } else {
            if (ftell($handle) + 16 > $boxEnd || fseek($handle, 8, SEEK_CUR) !== 0) {
                return null;
            }

            $timescale = readBigEndianUInt32($handle);
            $duration = readBigEndianUInt32($handle);
        }

        if (empty($timescale) || $duration === null) {
            return null;
        }

        return (float) $duration / (float) $timescale;
    }
}

if (!function_exists('findMp4DurationSecondsInRange')) {
    function findMp4DurationSecondsInRange($handle, int $startOffset, int $endOffset, int $depth = 0): ?float
    {
        if ($depth > 8) {
            return null;
        }

        $offset = $startOffset;
        $containerTypes = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts', 'udta', 'meta'];

        while ($offset + 8 <= $endOffset) {
            if (fseek($handle, $offset) !== 0) {
                return null;
            }

            $boxSize = readBigEndianUInt32($handle);
            $boxType = fread($handle, 4);
            if ($boxSize === null || !is_string($boxType) || strlen($boxType) !== 4) {
                return null;
            }

            $headerSize = 8;
            if ($boxSize === 1) {
                $largeSize = readBigEndianUInt64($handle);
                if ($largeSize === null) {
                    return null;
                }

                $boxSize = (int) $largeSize;
                $headerSize = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $endOffset - $offset;
            }

            if ($boxSize < $headerSize) {
                return null;
            }

            $boxEnd = $offset + $boxSize;
            if ($boxEnd > $endOffset || $boxEnd <= $offset) {
                return null;
            }

            if ($boxType === 'mvhd') {
                return readMp4MvhdDurationSeconds($handle, $offset + $headerSize, $boxEnd);
            }

            if (in_array($boxType, $containerTypes, true)) {
                $childStart = $offset + $headerSize;
                if ($boxType === 'meta') {
                    $childStart += 4;
                }

                if ($childStart < $boxEnd) {
                    $durationSeconds = findMp4DurationSecondsInRange($handle, $childStart, $boxEnd, $depth + 1);
                    if ($durationSeconds !== null) {
                        return $durationSeconds;
                    }
                }
            }

            $offset = $boxEnd;
        }

        return null;
    }
}

if (!function_exists('getMp4VideoDurationSeconds')) {
    function getMp4VideoDurationSeconds(string $filePath): ?float
    {
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize <= 0) {
            return null;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return null;
        }

        try {
            return findMp4DurationSecondsInRange($handle, 0, (int) $fileSize);
        } finally {
            fclose($handle);
        }
    }
}

if (!function_exists('getFfprobeVideoDurationSeconds')) {
    function getFfprobeVideoDurationSeconds(string $filePath): ?float
    {
        if (!function_exists('shell_exec') || !function_exists('escapeshellarg')) {
            return null;
        }

        $command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filePath) . ' 2>&1';
        $output = @shell_exec($command);
        if (!is_string($output)) {
            return null;
        }

        $durationText = trim($output);
        if (!preg_match('/^\d+(?:\.\d+)?$/', $durationText)) {
            return null;
        }

        $durationSeconds = (float) $durationText;
        return $durationSeconds > 0 ? $durationSeconds : null;
    }
}

if (!function_exists('getUploadedVideoDurationSeconds')) {
    function getUploadedVideoDurationSeconds(string $filePath): ?float
    {
        $ffprobeDurationSeconds = getFfprobeVideoDurationSeconds($filePath);
        if ($ffprobeDurationSeconds !== null) {
            return $ffprobeDurationSeconds;
        }

        return getMp4VideoDurationSeconds($filePath);
    }
}

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
                UPLOAD_ERR_INI_SIZE => 'This file is too large for the current server upload limit.',
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

        $uploadLimitBytes = getUploadLimitForMimeType($detectedMime);
        if ($uploadLimitBytes > 0 && (int) $file['size'] > $uploadLimitBytes) {
            $mediaKind = strpos($detectedMime, 'video/') === 0 ? 'Video' : 'Image';
            handleUploadFailure($mediaKind . ' too large. Maximum is ' . formatUploadLimitMb($uploadLimitBytes) . ' MB.');
        }

        if (strpos($detectedMime, 'video/') === 0) {
            $durationSeconds = getUploadedVideoDurationSeconds($file['tmp_name']);
            if ($durationSeconds === null) {
                handleUploadFailure('Unable to read this video length. Please choose another video.');
            }

            if ($durationSeconds > JOMU_VIDEO_UPLOAD_LIMIT_SECONDS) {
                handleUploadFailure(
                    'Video files can be up to ' . formatUploadDurationSeconds((float) JOMU_VIDEO_UPLOAD_LIMIT_SECONDS)
                    . '. This video is ' . formatUploadDurationSeconds($durationSeconds) . '.'
                );
            }
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
