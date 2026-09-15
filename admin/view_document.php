
<?php
// admin/view_document.php
// Streams a document from private_uploads/ to the browser (admin only).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Access denied.');
}

require_once 'config/admin_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed.');
}

$doc_id = $_GET['id'] ?? null;

if (!$doc_id || !ctype_digit((string)$doc_id)) {
    http_response_code(400);
    die('Invalid document ID.');
}

try {
    $stmt = $db->prepare("
        SELECT student_id, file_name, document_type
        FROM enrollment_documents
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $doc_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("view_document error: " . $e->getMessage());
    http_response_code(500);
    die('Database error.');
}

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

$file_path = dirname(__DIR__) . '/private_uploads/enrollment/'
           . $doc['student_id'] . '/'
           . $doc['file_name'];

if (!is_file($file_path)) {
    http_response_code(404);
    die('File missing on disk.');
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_map = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

// Clean any output buffer
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($file_path);
exit;
