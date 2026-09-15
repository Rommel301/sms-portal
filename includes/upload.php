
<?php
/**
 * Safe file upload helper with MIME validation.
 */

final class UploadException extends RuntimeException {}

/**
 * Validate and move an uploaded file.
 *
 * @param array  $file        $_FILES['...'] entry
 * @param string $destination Absolute target path
 * @param string $prefix      Filename prefix
 * @param array  $allowedMime Allowed MIME types
 * @param int    $maxBytes    Max size in bytes
 * @return string             New filename
 * @throws UploadException
 */
function upload_document(
    array $file,
    string $destination,
    string $prefix,
    array $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'],
    int $maxBytes = 5 * 1024 * 1024
): string {
    // Check basic errors
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new UploadException('Invalid upload parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new UploadException('No file was uploaded.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UploadException('File is too large.');
        default:
            throw new UploadException('Upload failed (code ' . $file['error'] . ').');
    }

    if ($file['size'] > $maxBytes) {
        throw new UploadException('File exceeds the maximum allowed size.');
    }

    // Verify it's an actual uploaded file
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadException('Possible file upload attack.');
    }

    // MIME check (spoof-resistant)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMime, true)) {
        throw new UploadException('Invalid file type: ' . htmlspecialchars($mime));
    }

    // Extension check (defense in depth)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $map = [
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'application/pdf' => ['pdf'],
    ];

    $ok = false;
    foreach ($allowedMime as $allowed) {
        if (isset($map[$allowed]) && in_array($ext, $map[$allowed], true)) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        throw new UploadException('File extension does not match its contents.');
    }

    // Ensure destination exists
    if (!is_dir($destination)) {
        if (!mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new UploadException('Could not create upload directory.');
        }
    }

    // Generate safe new name
    $newName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target  = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new UploadException('Failed to save file.');
    }

    // Restrict permissions
    @chmod($target, 0644);

    return $newName;
}
