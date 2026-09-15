
<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['student_id'];
$notification_id = $_POST['notification_id'] ?? null;

if (!$notification_id) {
    echo json_encode(['error' => 'Notification ID required']);
    exit();
}

try {
    $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
    $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $admin_db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = :id AND student_id = :student_id
    ");
    $stmt->execute([
        ':id' => $notification_id,
        ':student_id' => $student_id
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("mark_notification_read error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>
