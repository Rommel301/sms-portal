
<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/student_db.php';
require_once '../includes/enrollment_data.php';

$database = new StudentDatabase();
$db = $database->getConnection();

$student_id = $_SESSION['student_id'];

/*
|--------------------------------------------------------------------------
| ENROLLMENT CHECK
|--------------------------------------------------------------------------
*/

$enrollment_check = get_enrollment_data($db, $student_id);

$enrollment_status =
    $enrollment_check['enrollment_status'] ?? 'Not Enrolled';

if ($enrollment_status !== 'Enrolled') {
    header("Location: enrollment.php");
    exit();
}

$student = $enrollment_check['student'] ?? [];

/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$student_name =
    $_SESSION['student_name']
    ?? trim(
        ($student['first_name'] ?? '') . ' ' .
        ($student['last_name'] ?? '')
    );

if (empty(trim($student_name))) {
    $student_name = 'Student';
}

/*
|--------------------------------------------------------------------------
| SUBJECTS - Fetch from admin_db.student_subjects
|--------------------------------------------------------------------------
| IMPORTANT: The admin side (admin/subjects.php -> add_subject_for_student)
| writes rows into the `student_subjects` table that lives in `admin_db`
| (see the admin_db schema dump — student_subjects is created there, NOT
| in student_db). The old version of this file queried student_subjects
| through the StudentDatabase connection (student_db), which does not
| have that table, so subjects the admin added would never show up here.
|
| Fix: connect to admin_db directly for this query, the same way
| enrollment.php already does to pull `existing_enrollment`.
*/

function get_student_subjects($student_id)
{
    try {
        $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
        $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $admin_db->prepare("
            SELECT 
                id,
                subject_code AS code,
                subject_name AS name,
                units,
                instructor,
                schedule
            FROM student_subjects
            WHERE student_id = :student_id
            ORDER BY subject_code ASC
        ");
        $stmt->execute([':student_id' => $student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_student_subjects error: " . $e->getMessage());
        return [];
    }
}

$enrolled_subjects = get_student_subjects($student_id);

/*
|--------------------------------------------------------------------------
| TOTAL UNITS
|--------------------------------------------------------------------------
*/

$total_units = 0;

foreach ($enrolled_subjects as $subject) {
    $total_units += (float)($subject['units'] ?? 0);
}

$current_page = 'subjects.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Subjects — Student Portal</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

   <link rel="stylesheet" href="../assets/css/student.css">

</head>

<body>


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

        <a href="subjects.php" class="active">
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

        <a href="announcements.php">
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


<div class="main">

    <div class="topbar">

        <div>

            <h1>
                <i class="fas fa-book-open"></i>
                My Subjects
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


    <!-- INFO CARD -->
    <div class="info-card">

        <h3>
            <i class="fas fa-book-open"></i>
            Enrolled Subjects
        </h3>

        <?php if (empty($enrolled_subjects)): ?>

            <p>
                You are officially enrolled, but no subjects
                have been assigned to you yet. Please wait for your admin to assign your subjects.
            </p>

        <?php else: ?>

            <p>
                You are enrolled in 
                <span class="highlight"><?php echo count($enrolled_subjects); ?></span> 
                subject(s) this term. 
                Total units: <span class="highlight"><?php echo number_format($total_units, 1); ?></span>.
            </p>

        <?php endif; ?>

    </div>


    <!-- SUBJECTS TABLE -->
    <?php if (empty($enrolled_subjects)): ?>

        <div class="info-card">

            <h3>
                <i class="fas fa-clock"></i>
                No Subjects Assigned
            </h3>

            <p>
                Your administrator will assign your subjects.
                Once assigned, they will appear here.
            </p>

        </div>

    <?php else: ?>

        <div class="subjects-wrapper">

            <table class="subjects-table">

                <thead>

                    <tr>
                        <th>Code</th>
                        <th>Subject</th>
                        <th>Units</th>
                        <th>Instructor</th>
                        <th>Schedule</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($enrolled_subjects as $subj): ?>

                    <tr>

                        <td>
                            <span class="code-badge">
                                <?php echo htmlspecialchars($subj['code'] ?? ''); ?>
                            </span>
                        </td>

                        <td class="subject-name">
                            <?php echo htmlspecialchars($subj['name'] ?? ''); ?>
                        </td>

                        <td>
                            <span class="units-badge">
                                <?php echo number_format((float)($subj['units'] ?? 0), 1); ?>
                            </span>
                        </td>

                        <td class="instructor-name">
                            <i class="fas fa-user-tie" style="color:#255F38; width:16px;"></i>
                            <?php echo htmlspecialchars($subj['instructor'] ?? '—'); ?>
                        </td>

                        <td class="schedule-info">
                            <i class="fas fa-clock" style="color:#255F38; width:16px;"></i>
                            <?php echo htmlspecialchars($subj['schedule'] ?? '—'); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
