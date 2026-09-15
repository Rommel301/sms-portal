
<?php
// student/download.php

session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/config/student_db.php';
require_once __DIR__ . '/config/admin_db.php';
require_once __DIR__ . '/../includes/enrollment_data.php';

$database = new StudentDatabase();
$db = $database->getConnection();

$student_id = $_SESSION['student_id'];

$enrollment_check = get_enrollment_data($db, $student_id);

if ($enrollment_check['enrollment_status'] !== 'Enrolled') {
    header("Location: enrollment.php");
    exit();
}

$requested_id = $_GET['id'] ?? '';

if (!ctype_digit((string)$requested_id)) {
    http_response_code(404);
    die('Document not found.');
}

try {
    $admin_db = (new StudentAdminDatabase())->getConnection();

    $stmt = $admin_db->prepare("
        SELECT document_type, file_name, status
        FROM enrollment_documents
        WHERE id = :id AND student_id = :student_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id'         => $requested_id,
        ':student_id' => $student_id,
    ]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("download.php DB error: " . $e->getMessage());
    http_response_code(500);
    die('Something went wrong.');
}

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

if (strtolower($doc['status']) === 'rejected') {
    http_response_code(403);
    die('This document has been rejected and is not available for download.');
}

// Files live OUTSIDE webroot
$file_path = dirname(__DIR__) . '/private_uploads/enrollment/' . $student_id . '/' . $doc['file_name'];

if (!is_file($file_path)) {
    http_response_code(404);
    die('Document not found.');
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$mime_type = $mime_types[$ext] ?? 'application/octet-stream';

$download_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $doc['document_type']) . '.' . $ext;

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

readfile($file_path);
exit();
