
<?php
// config/paths.php
// Define base paths for the application

// Get the document root path
define('BASE_PATH', realpath(__DIR__ . '/../'));

// Define upload directories
define('UPLOAD_DIR', BASE_PATH . '/student/uploads/');
define('ENROLLMENT_UPLOAD_DIR', UPLOAD_DIR . 'enrollment/');

// Function to get document path
function get_document_path($student_id, $file_name) {
    return ENROLLMENT_UPLOAD_DIR . $student_id . '/' . $file_name;
}

// Function to get document URL
function get_document_url($student_id, $file_name) {
    return '/student/uploads/enrollment/' . $student_id . '/' . $file_name;
}
?>
