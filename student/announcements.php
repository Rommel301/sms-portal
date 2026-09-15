
<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/student_db.php';

$database = new StudentDatabase();
$db = $database->getConnection();

$student_id = $_SESSION['student_id'];

$error = null;

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$student = [];
$student_name = 'Student';
$student_first_name = 'Student';
$enrollment_status = 'Not Enrolled';

/*
|--------------------------------------------------------------------------
| CONNECT TO ADMIN DATABASE (For Enrollment Status)
|--------------------------------------------------------------------------
*/

$admin_db = null;

try {
    $admin_db = new PDO(
        "mysql:host=localhost;dbname=admin_db",
        "root",
        ""
    );
    $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $admin_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin database connection error: " . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| GET STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

try {
    $describe_query = "DESCRIBE students";
    $describe_stmt = $db->prepare($describe_query);
    $describe_stmt->execute();
    $columns = $describe_stmt->fetchAll(PDO::FETCH_ASSOC);

    $primary_key = 'id';
    foreach ($columns as $column) {
        if (isset($column['Key']) && $column['Key'] === 'PRI') {
            $primary_key = $column['Field'];
            break;
        }
    }

    $student_query = "
        SELECT *
        FROM students
        WHERE `$primary_key` = :student_id
        LIMIT 1
    ";

    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':student_id', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        session_destroy();
        header("Location: index.php?error=invalid_account");
        exit();
    }
} catch (PDOException $e) {
    error_log("Student database error: " . $e->getMessage());
    $student = [];
    $error = "Unable to load your student information.";
}

/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$student_name = 'Student';
$student_first_name = 'Student';

if (!empty($_SESSION['student_name'])) {
    $student_name = $_SESSION['student_name'];
} elseif (!empty($student['name'])) {
    $student_name = $student['name'];
} elseif (!empty($student['first_name']) || !empty($student['last_name'])) {
    $first = $student['first_name'] ?? '';
    $middle = $student['middle_name'] ?? '';
    $last = $student['last_name'] ?? '';
    $student_name = trim($first . ' ' . $middle . ' ' . $last);
    if (empty($student_name)) {
        $student_name = $student['username'] ?? 'Student';
    }
} elseif (!empty($student['student_name'])) {
    $student_name = $student['student_name'];
} elseif (!empty($student['username'])) {
    $student_name = $student['username'];
}

if (!empty($student['first_name'])) {
    $student_first_name = $student['first_name'];
} elseif (!empty($_SESSION['student_name'])) {
    $name_parts = explode(' ', trim($_SESSION['student_name']));
    $student_first_name = $name_parts[0] ?? 'Student';
} else {
    $student_first_name = 'Student';
}

$_SESSION['student_name'] = $student_name;

/*
|--------------------------------------------------------------------------
| GET ENROLLMENT STATUS (For Sidebar Links)
|--------------------------------------------------------------------------
*/

if ($admin_db !== null) {
    try {
        $enrollment_query = "
            SELECT status
            FROM admin_db.enrollments
            WHERE student_id = :student_id
            ORDER BY id DESC
            LIMIT 1
        ";

        $enrollment_stmt = $admin_db->prepare($enrollment_query);
        $enrollment_stmt->bindParam(':student_id', $student_id);
        $enrollment_stmt->execute();
        $latest_enrollment = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);

        if ($latest_enrollment) {
            $raw_status = strtolower(trim($latest_enrollment['status'] ?? ''));
            if (in_array($raw_status, ['active', 'approved'], true)) {
                $enrollment_status = 'Enrolled';
            } elseif ($raw_status === 'pending') {
                $enrollment_status = 'Pending';
            } elseif ($raw_status === 'rejected') {
                $enrollment_status = 'Rejected';
            } else {
                $enrollment_status = 'Not Enrolled';
            }
        }
    } catch (PDOException $e) {
        error_log("Enrollment error: " . $e->getMessage());
    }
}

$current_page = 'announcements.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements — Student Portal</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/student.css?v=2">
</head>

<body>

<!-- =====================================================
     SIDEBAR (Identical to dashboard.php)
===================================================== -->

<div class="sidebar">

    <h2>
        <i class="fas fa-user-graduate"></i>
        Student Portal
    </h2>

    <div class="sidebar-nav">

        <a href="dashboard.php">
            <i class="fas fa-house"></i>
            Dashboard
        </a>

        <a href="profile.php">
            <i class="fas fa-user"></i>
            My Profile
        </a>

        <?php if ($enrollment_status === 'Enrolled'): ?>

            <a href="subjects.php">
                <i class="fas fa-book-open"></i>
                My Subjects
            </a>

            <a href="grades.php">
                <i class="fas fa-chart-column"></i>
                My Grades
            </a>

            <a href="schedule.php">
                <i class="fas fa-calendar-days"></i>
                My Schedule
            </a>

            <a href="documents.php">
                <i class="fas fa-folder-open"></i>
                My Documents
            </a>

        <?php else: ?>

            <a href="enrollment.php">
                <i class="fas fa-clipboard-list"></i>
                Enrollment
            </a>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Subjects
            </div>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Grades
            </div>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Schedule
            </div>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Documents
            </div>

        <?php endif; ?>

        <a href="announcements.php" class="active">
            <i class="fas fa-bullhorn"></i>
            Announcements
        </a>

        <a href="notifications.php">
            <i class="fas fa-bell"></i>
            Notifications
        </a>

    </div>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-right-from-bracket"></i>
            Logout
        </a>
    </div>

</div>

<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main">

    <div class="topbar">
        <!-- Mobile Menu Button -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="toggleSidebar()" style="background: none; border: none; font-size: 24px; cursor: pointer; display: none; color: #255F38; padding: 0;" id="menu-toggle">
                ☰
            </button>
            
            <h1>
                <i class="fas fa-bullhorn"></i>
                Announcements
            </h1>
        </div>

        <a href="profile.php" class="topbar-profile">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <span>
                <?php echo htmlspecialchars($student_name); ?>
            </span>
        </a>
    </div>

    <!-- ERROR MESSAGE -->
    <?php if ($error): ?>
        <div class="message warning">
            <i class="fas fa-triangle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         ANNOUNCEMENTS LIST
    ===================================================== -->
    
    <div class="announcements-wrapper">
        <h3>
            <i class="fas fa-bullhorn"></i>
            Latest Announcements
        </h3>
        
        <!-- Announcement Item 1 -->
        <div class="announcement-item green">
            <div class="announcement-header">
                <span class="announcement-title">📢 Enrollment Period is Open!</span>
                <span class="announcement-date">August 17, 2026</span>
            </div>
            <p class="announcement-text">
                The enrollment for the 1st Semester of Academic Year 2026-2027 is now open. 
                Please log in to your portal and complete the enrollment process before the deadline.
            </p>
        </div>

        <!-- Announcement Item 2 -->
        <div class="announcement-item yellow">
            <div class="announcement-header">
                <span class="announcement-title">⚠️ System Maintenance</span>
                <span class="announcement-date">August 18, 2026</span>
            </div>
            <p class="announcement-text">
                The student portal will undergo scheduled maintenance on Saturday, August 20, 2026 from 10:00 PM to 2:00 AM. 
                Please save your work and log out before this time.
            </p>
        </div>

        <!-- Announcement Item 3 -->
        <div class="announcement-item blue">
            <div class="announcement-header">
                <span class="announcement-title">🎉 Welcome New Students!</span>
                <span class="announcement-date">August 15, 2026</span>
            </div>
            <p class="announcement-text">
                A warm welcome to all new students joining our institution this semester! 
                Make sure to check your email for orientation schedules and important reminders.
            </p>
        </div>

    </div>

</div>

<!-- =====================================================
     MOBILE JAVASCRIPT (Sidebar Toggle)
===================================================== -->

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    }

    function checkScreenSize() {
        const menuBtn = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (window.innerWidth <= 576) {
            if(menuBtn) menuBtn.style.display = 'block';
        } else {
            if(menuBtn) menuBtn.style.display = 'none';
            if(sidebar) sidebar.classList.remove('open');
        }
    }

    window.addEventListener('resize', checkScreenSize);
    window.addEventListener('load', checkScreenSize);

    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const menuBtn = document.getElementById('menu-toggle');
        if (window.innerWidth <= 576 && 
            sidebar && !sidebar.contains(event.target) && 
            menuBtn && !menuBtn.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    });
</script>

</body>
</html>
