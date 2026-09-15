
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
require_once 'enrollment_data.php';

$database = new AdminDatabase();
$db = $database->getConnection();

$studentDatabase = new StudentDatabase();
$student_db = $studentDatabase->getConnection();

$student_id = $_GET['id'] ?? null;

if (!$student_id || !ctype_digit((string) $student_id)) {
    header("Location: students.php");
    exit();
}

$student = null;
$error = '';

try {
    $stmt = $student_db->prepare("SELECT * FROM students WHERE student_id = :id LIMIT 1");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: students.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("student_view.php error: " . $e->getMessage());
    $error = "Unable to load student information.";
}

$full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

// Latest enrollment record for this student, if any
$latest_enrollment = null;
try {
    $enr_stmt = $db->prepare("
        SELECT * FROM enrollments 
        WHERE student_id = :id 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $enr_stmt->execute([':id' => $student_id]);
    $latest_enrollment = $enr_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("student_view.php enrollment fetch error: " . $e->getMessage());
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Details - Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="admin-page">

<div class="sidebar">
    <h2><i class="fas fa-tools"></i> <span>Admin Panel</span></h2>
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="students.php" class="active"><i class="fas fa-users"></i> <span>Students</span></a>
        <a href="enrollments.php"><i class="fas fa-clipboard-list"></i> <span>Enrollments</span></a>
        <a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a>
        <a href="schedule.php"><i class="fas fa-calendar-alt"></i> <span>Schedule</span></a>
        <a href="grades.php"><i class="fas fa-chart-line"></i> <span>Grades</span></a>
        <a href="documents.php"><i class="fas fa-file-alt"></i> <span>Documents</span></a>
    </div>
    <div class="logout-section">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="main">

    <div class="topbar">
        <h1><i class="fas fa-user"></i> Student Details</h1>
        <span><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
    </div>

    <div class="view-container">

        <a href="students.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Students
        </a>

        <?php if (!empty($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- PERSONAL INFO -->
        <div class="view-card">
            <div class="view-header">
                <div class="view-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($full_name ?: 'N/A'); ?></h2>
                    <div class="sub">
                        Student ID: <?php echo htmlspecialchars($student['student_id']); ?> ·
                        Admission No: <?php echo htmlspecialchars($student['admission_number'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <label>Email</label>
                    <div class="value"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Phone</label>
                    <div class="value"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <label>Date of Birth</label>
                    <div class="value">
                        <?php echo !empty($student['date_of_birth']) ? htmlspecialchars(date('F d, Y', strtotime($student['date_of_birth']))) : 'N/A'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Account Status</label>
                    <div class="value">
                        <span class="status-pill <?php echo ($student['status'] ?? '') === 'active' ? 'active' : 'rejected'; ?>">
                            <?php echo ucfirst(htmlspecialchars($student['status'] ?? 'inactive')); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item full-width">
                    <label>Address</label>
                    <div class="value"><?php echo nl2br(htmlspecialchars($student['address'] ?? 'N/A')); ?></div>
                </div>
                <?php if (!empty($student['created_at'])): ?>
                <div class="info-item">
                    <label>Account Created</label>
                    <div class="value"><?php echo htmlspecialchars(date('F d, Y', strtotime($student['created_at']))); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ENROLLMENT INFO -->
        <div class="view-card">
            <h3 class="section-title"><i class="fas fa-clipboard-list"></i> Latest Enrollment</h3>

            <?php if (!$latest_enrollment): ?>
                <p class="no-enrollment">This student has not submitted an enrollment yet.</p>
            <?php else: ?>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Program</label>
                        <div class="value"><?php echo htmlspecialchars($latest_enrollment['program'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Year Level</label>
                        <div class="value"><?php echo htmlspecialchars($latest_enrollment['year_level'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Semester</label>
                        <div class="value"><?php echo htmlspecialchars($latest_enrollment['semester'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Academic Year</label>
                        <div class="value"><?php echo htmlspecialchars($latest_enrollment['academic_year'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Enrollment Status</label>
                        <div class="value">
                            <?php
                            $enr_status = $latest_enrollment['status'] ?? 'pending';
                            $pill_class = $enr_status === 'active' ? 'active' : ($enr_status === 'rejected' ? 'rejected' : 'pending');
                            ?>
                            <span class="status-pill <?php echo $pill_class; ?>">
                                <?php echo ucfirst(htmlspecialchars($enr_status)); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Submitted</label>
                        <div class="value">
                            <?php echo !empty($latest_enrollment['submitted_at']) ? htmlspecialchars(date('F d, Y', strtotime($latest_enrollment['submitted_at']))) : 'N/A'; ?>
                        </div>
                    </div>
                    <?php if (!empty($latest_enrollment['remarks'])): ?>
                    <div class="info-item full-width">
                        <label>Admin Remarks</label>
                        <div class="value"><?php echo nl2br(htmlspecialchars($latest_enrollment['remarks'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="manage-container">
                    <a href="enrollments.php?filter=all" class="btn-manage">
                        <i class="fas fa-clipboard-list"></i> Manage Enrollments
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>
