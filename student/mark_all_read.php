
<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['student_id'];

try {
    $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
    $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $admin_db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE student_id = :student_id AND is_read = 0
    ");
    $stmt->execute([':student_id' => $student_id]);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);

} catch (PDOException $e) {
    error_log("mark_all_read error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>
