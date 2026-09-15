
<?php
// student/start_enrollment.php

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: enrollment.php");
    exit();
}

require_once __DIR__ . '/config/student_db.php';
require_once __DIR__ . '/config/admin_db.php';
require_once __DIR__ . '/../includes/upload.php';

// Uploads live OUTSIDE webroot
$upload_directory = dirname(__DIR__) . '/private_uploads/enrollment/' . $student_id . '/';

/* ─── Collect input ───────────────────────────────────── */
$full_name      = trim($_POST['full_name'] ?? '');
$address        = trim($_POST['address'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$email          = trim($_POST['email'] ?? '');
$program        = trim($_POST['program'] ?? '');
$year_level     = trim($_POST['year_level'] ?? '');
$semester       = trim($_POST['semester'] ?? '');
$academic_year  = trim($_POST['academic_year'] ?? '');

if (
    empty($full_name) || empty($address) || empty($contact_number) ||
    empty($email) || empty($program) || empty($year_level) ||
    empty($semester) || empty($academic_year)
) {
    die("Please complete all required enrollment information.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address.");
}

foreach (['birth_certificate', 'valid_id', 'id_picture'] as $file) {
    if (!isset($_FILES[$file]) || $_FILES[$file]['error'] !== UPLOAD_ERR_OK) {
        die("Please upload all required documents.");
    }
}

/* ─── Process ─────────────────────────────────────────── */
try {
    $admin_db = (new StudentAdminDatabase())->getConnection();

    $admin_db->beginTransaction();

    // Prevent duplicate active/pending enrollment
    $check = $admin_db->prepare("
        SELECT id FROM enrollments
        WHERE student_id = :student_id
          AND status IN ('pending', 'active')
        LIMIT 1
    ");
    $check->execute([':student_id' => $student_id]);

    if ($check->fetch()) {
        $admin_db->rollBack();
        header("Location: enrollment.php?error=already_submitted");
        exit();
    }

    // Insert enrollment
    $stmt = $admin_db->prepare("
        INSERT INTO enrollments (
            student_id, full_name, address, contact_number,
            email, program, year_level, semester, academic_year,
            status, submitted_at
        ) VALUES (
            :student_id, :full_name, :address, :contact_number,
            :email, :program, :year_level, :semester, :academic_year,
            'pending', NOW()
        )
    ");
    $stmt->execute([
        ':student_id'     => $student_id,
        ':full_name'      => $full_name,
        ':address'        => $address,
        ':contact_number' => $contact_number,
        ':email'          => $email,
        ':program'        => $program,
        ':year_level'     => $year_level,
        ':semester'       => $semester,
        ':academic_year'  => $academic_year,
    ]);

    $enrollment_id = $admin_db->lastInsertId();

    // Upload (uses includes/upload.php helper)
    $birth_certificate = upload_document($_FILES['birth_certificate'], $upload_directory, 'birth_certificate');
    $valid_id          = upload_document($_FILES['valid_id'],          $upload_directory, 'valid_id');
    $id_picture        = upload_document($_FILES['id_picture'],        $upload_directory, 'id_picture');

    // Save document records
    $doc_stmt = $admin_db->prepare("
        INSERT INTO enrollment_documents (
            enrollment_id, student_id, document_type, file_name,
            status, uploaded_at
        ) VALUES (
            :enrollment_id, :student_id, :document_type, :file_name,
            'pending', NOW()
        )
    ");

    foreach ([
        ['birth_certificate', $birth_certificate],
        ['valid_id',          $valid_id],
        ['id_picture',        $id_picture],
    ] as [$type, $file]) {
        $doc_stmt->execute([
            ':enrollment_id' => $enrollment_id,
            ':student_id'    => $student_id,
            ':document_type' => $type,
            ':file_name'     => $file,
        ]);
    }

    // Notification
    $admin_db->prepare("
        INSERT INTO notifications (student_id, message, type, created_at, is_read)
        VALUES (:student_id, :message, 'enrollment', NOW(), 0)
    ")->execute([
        ':student_id' => $student_id,
        ':message'    => '📝 Your enrollment has been submitted and is pending review by the admin.',
    ]);

    $admin_db->commit();

    header("Location: enrollment.php");
    exit();

} catch (UploadException $e) {
    if (isset($admin_db) && $admin_db->inTransaction()) {
        $admin_db->rollBack();
    }
    die($e->getMessage());

} catch (PDOException $e) {
    if (isset($admin_db) && $admin_db->inTransaction()) {
        $admin_db->rollBack();
    }
    error_log("Enrollment submission error: " . $e->getMessage());
    header("Location: enrollment.php?error=failed");
    exit();
}
