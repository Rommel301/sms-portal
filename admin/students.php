
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/admin_db.php';
require_once 'config/student_db.php';

$database = new AdminDatabase();
$db = $database->getConnection();

$error = '';
$warning = '';
$success = '';

$studentDatabase = new StudentDatabase();
$student_db = $studentDatabase->getConnection();

// ============================================================
// HANDLE DELETE STUDENT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $delete_id = $_POST['student_id'] ?? null;
    
    if ($delete_id && ctype_digit((string) $delete_id)) {
        try {
            // Start transaction
            $student_db->beginTransaction();
            
            // Delete related records first (if any foreign keys exist)
            // Delete from enrollments if student_id is referenced
            try {
                $del_enrollments = $student_db->prepare("DELETE FROM enrollments WHERE student_id = :id");
                $del_enrollments->execute([':id' => $delete_id]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete from grades if student_id is referenced
            try {
                $del_grades = $student_db->prepare("DELETE FROM grades WHERE student_id = :id");
                $del_grades->execute([':id' => $delete_id]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete from schedule if student_id is referenced
            try {
                $del_schedule = $student_db->prepare("DELETE FROM schedule WHERE student_id = :id");
                $del_schedule->execute([':id' => $delete_id]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete from documents if student_id is referenced
            try {
                $del_documents = $student_db->prepare("DELETE FROM documents WHERE student_id = :id");
                $del_documents->execute([':id' => $delete_id]);
            } catch (PDOException $e) {
                // Table might not exist, continue
            }
            
            // Delete the student
            $delete_stmt = $student_db->prepare("DELETE FROM students WHERE student_id = :id");
            $delete_stmt->execute([':id' => $delete_id]);
            
            // ============================================================
            // RESET AUTO_INCREMENT - Re-number all remaining students
            // This ensures no gaps in the ID sequence
            // ============================================================
            
            // Get all remaining students ordered by ID
            $remaining_students = $student_db->query("
                SELECT student_id 
                FROM students 
                ORDER BY student_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Re-number all students from 1 to N (no gaps)
            $new_id = 1;
            $update_stmt = $student_db->prepare("
                UPDATE students 
                SET student_id = :new_id 
                WHERE student_id = :old_id
            ");
            
            foreach ($remaining_students as $student) {
                $update_stmt->execute([
                    ':new_id' => $new_id,
                    ':old_id' => $student['student_id']
                ]);
                $new_id++;
            }
            
            // Reset AUTO_INCREMENT to the next available ID
            $student_db->exec("ALTER TABLE students AUTO_INCREMENT = $new_id");
            
            // Commit transaction
            $student_db->commit();
            
            $success = "Student deleted successfully! IDs have been re-numbered.";
            
            // Redirect to avoid form resubmission
            header("Location: students.php?msg=deleted");
            exit();
            
        } catch (PDOException $e) {
            $student_db->rollBack();
            error_log("Delete student error: " . $e->getMessage());
            $error = "Error deleting student. Please try again.";
        }
    } else {
        $error = "Invalid student ID.";
    }
}

// Check for success message from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success = "✅ Student deleted successfully! IDs have been re-numbered.";
}

$students = [];

if ($student_db) {
    try {
        $stmt = $student_db->query("
            SELECT 
                student_id,
                admission_number,
                first_name,
                last_name,
                email,
                phone,
                date_of_birth,
                address,
                status,
                enrollment_date,
                created_at
            FROM students 
            ORDER BY student_id ASC
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Students query failed: " . $e->getMessage());
        $error = "Unable to load students data.";
        $students = [];
    }
} else {
    $error = "Student database connection issue.";
    $students = [];
}

$total_students = count($students);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Students - Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="admin-page">

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>
        <i class="fas fa-tools"></i>
        <span>Admin Panel</span>
    </h2>

    <div class="sidebar-nav">
        <a href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="students.php" class="active">
            <i class="fas fa-users"></i>
            <span>Students</span>
        </a>
        <a href="enrollments.php">
            <i class="fas fa-clipboard-list"></i>
            <span>Enrollments</span>
        </a>
        <a href="subjects.php">
            <i class="fas fa-book"></i>
            <span>Subjects</span>
        </a>
        <a href="schedule.php">
            <i class="fas fa-calendar-alt"></i>
            <span>Schedule</span>
        </a>
        <a href="grades.php">
            <i class="fas fa-chart-line"></i>
            <span>Grades</span>
        </a>
        <a href="documents.php">
            <i class="fas fa-file-alt"></i>
            <span>Documents</span>
        </a>
    </div>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <div class="topbar">
        <h1>
            <i class="fas fa-users"></i>
            Students Management
        </h1>
        <span>
            <i class="fas fa-user-circle"></i>
            Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
        </span>
    </div>

    <?php if (!empty($success)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($warning)): ?>
        <div class="message warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($warning); ?>
        </div>
    <?php endif; ?>

    <div class="students-container">

        <div class="students-list-header">
            <h2 style="margin: 0;">
                <i class="fas fa-list"></i>
                Student List
            </h2>
            <span class="students-total">
                <i class="fas fa-database"></i>
                Total: <?php echo number_format($total_students); ?> students
            </span>
        </div>

        <?php if (empty($students)): ?>

            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h3>No Students Found</h3>
                <p>There are no students registered in the system yet.</p>
            </div>

        <?php else: ?>

            <div class="students-table-wrap">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-id-card"></i> Admission No.</th>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-calendar-check"></i> Status</th>
                            <th class="actions-col"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                            $status = $student['status'] ?? 'inactive';
                            $sid = $student['student_id'] ?? 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sid ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['admission_number'] ?? 'N/A'); ?></td>
                                <td class="student-name"><?php echo htmlspecialchars($full_name ?: 'N/A'); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($student['email'] ?? ''); ?>" class="student-email-link">
                                        <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-pill <?php echo $status === 'active' ? 'active' : 'inactive'; ?>">
                                        <i class="fas <?php echo $status === 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <?php echo ucfirst(htmlspecialchars($status)); ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <a href="student_view.php?id=<?php echo htmlspecialchars($sid); ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="student_edit.php?id=<?php echo htmlspecialchars($sid); ?>" class="btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this student? This will also delete all related records (enrollments, grades, schedule, documents).');">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($sid); ?>">
                                            <button type="submit" name="delete_student" class="btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>

</div>

<script>
    setTimeout(function () {
        var messages = document.querySelectorAll('.message');
        messages.forEach(function (message) {
            if (message) {
                message.style.display = 'none';
            }
        });
    }, 5000);
</script>

</body>
</html>
