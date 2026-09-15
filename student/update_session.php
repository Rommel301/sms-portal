
<?php
session_start();

header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['action']) && $data['action'] === 'hide_rejected') {
    if (isset($_SESSION['student_id'])) {
        $student_id = $_SESSION['student_id'];
        // Set session flag to remember that this user has seen rejected files
        $_SESSION['has_seen_rejected_' . $student_id] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'Session updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Student ID not found in session'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}
?>
