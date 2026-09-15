
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/admin_db.php';

// Check if $db is properly defined
if (!isset($db) || !($db instanceof PDO)) {
    die("Database connection failed. Please check your configuration.");
}

// Include enrollment functions
require_once 'enrollment_data.php';

// Get filter from URL parameter
$filter = $_GET['filter'] ?? 'pending';

// Validate filter
$valid_filters = ['pending', 'active', 'rejected', 'all'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'pending';
}

// Get enrollments based on filter
try {
    if ($filter === 'all') {
        $enrollments = get_all_enrollments($db, null);
    } else {
        $enrollments = get_all_enrollments($db, $filter);
    }
} catch (PDOException $e) {
    error_log("Error fetching enrollments: " . $e->getMessage());
    $enrollments = [];
}

// Get counts for each status
$pending_count = count_enrollments_by_status($db, 'pending');
$active_count = count_enrollments_by_status($db, 'active');
$rejected_count = count_enrollments_by_status($db, 'rejected');

// Handle form submissions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enrollment_action'])) {
    $enrollment_id = (int) $_POST['enrollment_id'];
    $action = $_POST['enrollment_action'];
    $remarks = $_POST['remarks'] ?? null;
    
    try {
        // Get student_id for notification
        $student_stmt = $db->prepare("SELECT student_id FROM enrollments WHERE id = :id");
        $student_stmt->execute([':id' => $enrollment_id]);
        $enrollment_data = $student_stmt->fetch(PDO::FETCH_ASSOC);
        $student_id = $enrollment_data['student_id'] ?? null;
        
        // CREATE NOTIFICATIONS TABLE IF NOT EXISTS
        $db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) DEFAULT 'general',
                is_read BOOLEAN DEFAULT FALSE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student_id (student_id),
                INDEX idx_is_read (is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        if ($action === 'approve') {
            update_enrollment_status($db, $enrollment_id, 'active', $remarks);
            
            // 🔔 SEND APPROVAL NOTIFICATION
            if ($student_id) {
                $notif_stmt = $db->prepare("
                    INSERT INTO notifications (student_id, message, type, created_at, is_read)
                    VALUES (
                        :student_id,
                        '🎉 CONGRATULATIONS! Your enrollment has been APPROVED! You can now access your subjects, grades, and schedule. Welcome to the school!',
                        'enrollment',
                        NOW(),
                        0
                    )
                ");
                $notif_stmt->execute([':student_id' => $student_id]);
            }
            
        } elseif ($action === 'reject') {
            update_enrollment_status($db, $enrollment_id, 'rejected', $remarks);
            
            // 🔔 SEND REJECTION NOTIFICATION
            if ($student_id) {
                $message = "❌ Your enrollment has been REJECTED. ";
                if (!empty($remarks)) {
                    $message .= "Reason: " . $remarks . " ";
                }
                $message .= "Please re-submit your enrollment with the correct information and documents.";
                
                $notif_stmt = $db->prepare("
                    INSERT INTO notifications (student_id, message, type, created_at, is_read)
                    VALUES (:student_id, :message, 'enrollment', NOW(), 0)
                ");
                $notif_stmt->execute([
                    ':student_id' => $student_id,
                    ':message' => $message
                ]);
            }
        }
        
        // Redirect to avoid form resubmission
        header("Location: enrollments.php?filter=" . $filter);
        exit;
        
    } catch (PDOException $e) {
        error_log("Error updating enrollment: " . $e->getMessage());
    }
}

// Get admin name from session
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollments - Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        <a href="students.php">
            <i class="fas fa-users"></i>
            <span>Students</span>
        </a>

        <a href="enrollments.php" class="active">
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

    <!-- LOGOUT -->
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
            <i class="fas fa-clipboard-list"></i>
            Enrollments
        </h1>

        <span>
            <i class="fas fa-user-circle"></i>
            Welcome, <?php echo htmlspecialchars($admin_name); ?>
        </span>
    </div>

    <div class="enrollments-container">

        <!-- FILTERS -->
        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-hourglass-half"></i>
                Pending <span class="count"><?php echo $pending_count; ?></span>
            </a>
            <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                Approved <span class="count"><?php echo $active_count; ?></span>
            </a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i>
                Rejected <span class="count"><?php echo $rejected_count; ?></span>
            </a>
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                All
            </a>
        </div>

        <!-- EMPTY -->
        <?php if (empty($enrollments)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No enrollments found for this filter.</p>
                <p class="sub-text">Try selecting a different filter or check back later.</p>
            </div>

        <?php else: ?>

            <!-- TABLE -->
            <div class="table-wrapper">
                <table class="enrollment-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Year / Semester</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($enrollments as $enrollment): ?>
                        <tr>
                            <td>
                                <div class="student-info">
                                    <span class="student-name"><?php echo htmlspecialchars($enrollment['full_name']); ?></span>
                                    <span class="student-email"><?php echo htmlspecialchars($enrollment['email']); ?></span>
                                </div>
                            </td>

                            <td class="program-name"><?php echo htmlspecialchars($enrollment['program']); ?></td>

                            <td>
                                <div class="year-info">
                                    <span class="year-level"><?php echo htmlspecialchars($enrollment['year_level']); ?></span>
                                    <span class="year-semester-detail">
                                        <?php echo htmlspecialchars($enrollment['semester']); ?>
                                        ·
                                        <?php echo htmlspecialchars($enrollment['academic_year']); ?>
                                    </span>
                                </div>
                            </td>

                            <td class="submitted-date"><?php echo htmlspecialchars($enrollment['submitted_at']); ?></td>

                            <td>
                                <span class="status-pill status-<?php echo htmlspecialchars($enrollment['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($enrollment['status'])); ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($enrollment['status'] === 'pending'): ?>
                                    <form method="POST" class="enrollment-action-form">
                                        <input type="hidden" name="enrollment_id" value="<?php echo htmlspecialchars($enrollment['id']); ?>">
                                        <input type="hidden" name="enrollment_action" class="js-action-field" value="approve">

                                        <div class="action-wrapper">
                                            <div class="row-actions">
                                                <button type="submit" class="btn-approve">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn-reject" onclick="
                                                    if (confirm('Reject this enrollment?')) {
                                                        this.closest('form').querySelector('.js-action-field').value = 'reject';
                                                        this.closest('form').submit();
                                                    }
                                                ">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                            <div class="remarks-wrapper">
                                                <input type="text" name="remarks" placeholder="Add remarks (optional)" class="remarks-input">
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <span class="no-actions">No actions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TABLE FOOTER -->
            <div class="table-footer">
                <i class="fas fa-users"></i>
                Showing <?php echo count($enrollments); ?> enrollment(s)
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
