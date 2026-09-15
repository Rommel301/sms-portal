
<?php


// MUST match index.php cookie params exactly
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
$profile_percentage = 0;
$profile_complete = false;
$course_count = 0;
$pending_enrollment_count = 0;
$enrollment_status = 'Not Enrolled';

/*
|--------------------------------------------------------------------------
| CONNECT TO ADMIN DATABASE
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
    $error = "Some enrollment information is currently unavailable.";
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

if (!empty($student['first_name']) || !empty($student['last_name'])) {
    $first = $student['first_name'] ?? '';
    $middle = $student['middle_name'] ?? '';
    $last = $student['last_name'] ?? '';
    $student_name = trim(preg_replace('/\s+/', ' ', $first . ' ' . $middle . ' ' . $last));
    if ($student_name === '') {
        $student_name = $student['username'] ?? 'Student';
    }
} elseif (!empty($student['name'])) {
    $student_name = $student['name'];
} elseif (!empty($student['student_name'])) {
    $student_name = $student['student_name'];
} elseif (!empty($_SESSION['student_name'])) {
    $student_name = $_SESSION['student_name'];
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
| PROFILE COMPLETION
|--------------------------------------------------------------------------
*/
$phone_value = $student['phone'] ?? $student['contact_number'] ?? $student['contact_no'] ?? '';
$profile_fields = [
    'first_name' => $student['first_name'] ?? '',
    'last_name'  => $student['last_name'] ?? '',
    'email'      => $student['email'] ?? '',
    'phone'      => $phone_value,
    'address'    => $student['address'] ?? '',
];
$completed_profile_fields = 0;
$total_profile_fields = count($profile_fields);

foreach ($profile_fields as $field_key => $field_val) {
    if (!empty($field_val) && trim((string)$field_val) !== '') {
        $completed_profile_fields++;
    }
}

$profile_percentage = ($total_profile_fields > 0)
    ? round(($completed_profile_fields / $total_profile_fields) * 100)
    : 0;

$profile_complete = ($profile_percentage >= 100);

/*
|--------------------------------------------------------------------------
| GET ENROLLMENT STATUS
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

        $active_query = "
            SELECT COUNT(*) AS count
            FROM admin_db.enrollments
            WHERE student_id = :student_id
            AND status IN ('active', 'approved')
        ";
        $active_stmt = $admin_db->prepare($active_query);
        $active_stmt->bindParam(':student_id', $student_id);
        $active_stmt->execute();
        $active_result = $active_stmt->fetch(PDO::FETCH_ASSOC);
        $course_count = (int)($active_result['count'] ?? 0);

        if ($course_count > 0) {
            $enrollment_status = 'Enrolled';
        }
    } catch (PDOException $e) {
        error_log("Enrollment error: " . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| DASHBOARD NEXT ACTION
|--------------------------------------------------------------------------
*/
if (!$profile_complete) {
    $next_action_title = "Complete Your Profile";
    $next_action_text = "Please complete your student information before starting enrollment.";
    $next_action_link = "profile.php";
    $next_action_button = "Complete Profile";
} elseif ($enrollment_status === 'Not Enrolled') {
    $next_action_title = "Start Your Enrollment";
    $next_action_text = "Your profile is complete. You can now start your enrollment.";
    $next_action_link = "enrollment.php";
    $next_action_button = "Start Enrollment";
} elseif ($enrollment_status === 'Pending') {
    $next_action_title = "Enrollment Pending";
    $next_action_text = "Your enrollment has been submitted and is waiting for administrator approval.";
    $next_action_link = "enrollment.php";
    $next_action_button = "View Enrollment";
} elseif ($enrollment_status === 'Rejected') {
    $next_action_title = "Enrollment Needs Attention";
    $next_action_text = "Your enrollment was not approved. Please review and resubmit your enrollment.";
    $next_action_link = "enrollment.php";
    $next_action_button = "Review Enrollment";
} else {
    $next_action_title = "You're Enrolled!";
    $next_action_text = "Your enrollment is active. You can now access your subjects, schedule, grades, and documents.";
    $next_action_link = "subjects.php";
    $next_action_button = "View My Subjects";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Stylesheet with cache buster -->
     <link rel="stylesheet" href="../assets/css/student.css">
</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->
<aside class="sidebar" id="sidebar">

    <h2>
        <i class="fas fa-user-graduate"></i>
        <span>Student Portal</span>
    </h2>

    <nav class="sidebar-nav">

        <a href="dashboard.php" class="active">
            <i class="fas fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="profile.php">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>

        <?php if ($enrollment_status === 'Enrolled'): ?>

            <a href="subjects.php">
                <i class="fas fa-book-open"></i>
                <span>My Subjects</span>
            </a>

            <a href="grades.php">
                <i class="fas fa-chart-column"></i>
                <span>My Grades</span>
            </a>

            <a href="schedule.php">
                <i class="fas fa-calendar-days"></i>
                <span>My Schedule</span>
            </a>

            <a href="documents.php">
                <i class="fas fa-folder-open"></i>
                <span>My Documents</span>
            </a>

        <?php else: ?>

            <a href="enrollment.php">
                <i class="fas fa-clipboard-list"></i>
                <span>Enrollment</span>
            </a>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                <span>My Subjects </span>
            </div>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                <span>My Grades </span>
            </div>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                <span>My Schedule </span>
            </div>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                <span>My Documents</span>
            </div>

        <?php endif; ?>

        <a href="announcements.php">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>

        <a href="notifications.php">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </a>

    </nav>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>

<!-- =====================================================
     MAIN CONTENT
===================================================== -->
<main class="main">

    <div class="topbar">
        <h1>
            <i class="fas fa-house"></i>
            Student Dashboard
        </h1>

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

    <!-- WELCOME -->
    <div class="info-card">
        <h3>
            <i class="fas fa-hand-wave"></i>
            Welcome, <?php echo htmlspecialchars($student_first_name); ?>! 👋
        </h3>
        <p style="color:#718096; margin:0;">
            Welcome to your Student Portal. Manage your profile, enrollment, subjects, schedule, grades, and documents from your dashboard.
        </p>
    </div>

    <!-- NEXT STEP -->
    <div class="quick-actions">
        <h3>
            <i class="fas fa-route"></i>
            Your Next Step
        </h3>

        <div class="next-step-box">
            <h3 class="next-step-title">
                <?php echo htmlspecialchars($next_action_title); ?>
            </h3>
            <p class="next-step-text">
                <?php echo htmlspecialchars($next_action_text); ?>
            </p>
            <a href="<?php echo htmlspecialchars($next_action_link); ?>" class="action-btn">
                <i class="fas fa-arrow-right"></i>
                <?php echo htmlspecialchars($next_action_button); ?>
            </a>
        </div>
    </div>

</main>


<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>


</body>
</html>
