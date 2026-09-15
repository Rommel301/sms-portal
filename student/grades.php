
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
| Student must be approved/enrolled before accessing grades.
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
| GRADES - Fetch from admin_db.student_grades
|--------------------------------------------------------------------------
*/

function get_student_grades($student_id)
{
    try {
        $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
        $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $admin_db->prepare("
            SELECT
                subject_code  AS code,
                subject_name  AS name,
                midterm_grade AS midterm,
                final_grade   AS final,
                remarks
            FROM student_grades
            WHERE student_id = :student_id
            ORDER BY subject_code ASC
        ");
        $stmt->execute([':student_id' => $student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_student_grades error: " . $e->getMessage());
        return [];
    }
}

$grades = get_student_grades($student_id);

/*
|--------------------------------------------------------------------------
| Helper: classify a grade value into a CSS class.
|--------------------------------------------------------------------------
*/

function classify_grade($value)
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return 'pending';
    }

    $value = (float) $value;

    if ($value >= 90) {
        return 'high';
    } elseif ($value < 75) {
        return 'fail';
    } elseif ($value < 80) {
        return 'low';
    }

    return 'pass';
}

/*
|--------------------------------------------------------------------------
| GPA
|--------------------------------------------------------------------------
*/

$total_grade = 0;
$count = 0;
$incomplete_count = 0;

foreach ($grades as $g) {

    $midterm_is_numeric = is_numeric($g['midterm'] ?? null);
    $final_is_numeric   = is_numeric($g['final'] ?? null);

    if (!$midterm_is_numeric || !$final_is_numeric) {
        $incomplete_count++;
        continue;
    }

    $avg = ((float)$g['midterm'] + (float)$g['final']) / 2;

    $total_grade += $avg;
    $count++;
}

$gpa = $count > 0
    ? round($total_grade / $count, 2)
    : 0;

$current_page = 'grades.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Grades — Student Portal</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/student.css">
</head>

<body>

<!-- =====================================================
     SIDEBAR
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

            <a href="grades.php" class="active">
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

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                My Subjects
            </div>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                My Grades
            </div>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                My Schedule
            </div>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                My Documents
            </div>

        <?php endif; ?>

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


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main">

    <div class="topbar">

        <div>
            <h1>
                <i class="fas fa-chart-column"></i>
                My Grades
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


    <!-- =================================================
         ACADEMIC PERFORMANCE INFO CARD
    ================================================== -->

    <div class="info-card academic-info">

        <h3>
            <i class="fas fa-chart-line"></i>
            Academic Performance
        </h3>

        <p>
            Your grades are displayed below. If you have any questions
            about your grades, please contact your instructor or the
            registrar's office.
        </p>

    </div>


    <!-- =================================================
         NO GRADES YET
    ================================================== -->

    <?php if (empty($grades)): ?>

        <div class="no-grades-card">

            <i class="fas fa-chart-line empty-icon"></i>

            <h3>
                No Grades Available
            </h3>

            <p>
                You are currently enrolled, but no grades have
                been recorded for your account yet. Please check
                back later for updates.
            </p>

        </div>

    <?php else: ?>

        <div class="grades-wrapper">

            <table class="grades-table">

                <thead>

                    <tr>
                        <th>Code</th>
                        <th>Subject</th>
                        <th>Midterm</th>
                        <th>Final</th>
                        <th>Average</th>
                        <th>Remarks</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($grades as $g): ?>

                    <?php

                    $midterm_is_numeric = is_numeric($g['midterm'] ?? null);
                    $final_is_numeric   = is_numeric($g['final'] ?? null);

                    $avg_display = '—';
                    $avg_class   = 'pending';

                    if ($midterm_is_numeric && $final_is_numeric) {
                        $avg = round(((float)$g['midterm'] + (float)$g['final']) / 2, 1);
                        $avg_display = $avg;
                        $avg_class = classify_grade($avg);
                    }

                    $final_class = classify_grade($g['final'] ?? null);

                    $midterm_display = ($g['midterm'] !== null && $g['midterm'] !== '')
                        ? $g['midterm']
                        : '—';

                    $final_display = ($g['final'] !== null && $g['final'] !== '')
                        ? $g['final']
                        : '—';

                    // Remarks class
                    $remarks = $g['remarks'] ?? '—';
                    $remarks_class = '';
                    if (strtolower($remarks) === 'passed') {
                        $remarks_class = 'passed';
                    } elseif (strtolower($remarks) === 'failed') {
                        $remarks_class = 'failed';
                    } elseif (strtolower($remarks) === 'inc') {
                        $remarks_class = 'inc';
                    }

                    ?>

                    <tr>

                        <td>
                            <span class="code-badge">
                                <?php echo htmlspecialchars($g['code'] ?? ''); ?>
                            </span>
                        </td>

                        <td class="subject-name">
                            <?php echo htmlspecialchars($g['name'] ?? ''); ?>
                        </td>

                        <td class="grade-cell">
                            <?php echo htmlspecialchars($midterm_display); ?>
                        </td>

                        <td>
                            <span class="grade-final <?php echo $final_class; ?>">
                                <?php echo htmlspecialchars($final_display); ?>
                            </span>
                        </td>

                        <td>
                            <span class="grade-final <?php echo $avg_class; ?>">
                                <?php echo htmlspecialchars((string)$avg_display); ?>
                            </span>
                        </td>

                        <td class="grade-remarks <?php echo $remarks_class; ?>">
                            <?php echo htmlspecialchars($remarks); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

            <!-- GPA SUMMARY -->
            <div class="gpa-summary">
                <div class="gpa-left">
                    <?php if ($count > 0): ?>
                        <div class="gpa-box gpa-box-green">
                            <span class="gpa-label">
                                <i class="fas fa-calculator"></i>
                                GPA:
                            </span>
                            <span class="gpa-value">
                                <?php echo number_format($gpa, 2); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="gpa-box gpa-box-gray">
                            <span class="gpa-label">
                                <i class="fas fa-clock"></i>
                                GPA:
                            </span>
                            <span class="gpa-value">
                                —
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="gpa-right">
                    <?php if ($count > 0 && $incomplete_count > 0): ?>
                        <p class="gpa-note">
                            <i class="fas fa-info-circle"></i>
                            GPA is based on <?php echo $count; ?> of <?php echo count($grades); ?> subject(s) — <?php echo $incomplete_count; ?> subject(s) marked INC or without both grades yet are excluded.
                        </p>
                    <?php elseif ($count > 0): ?>
                        <p class="gpa-note gpa-note-success">
                            <i class="fas fa-check-circle"></i>
                            GPA calculated from <?php echo $count; ?> subject(s).
                        </p>
                    <?php else: ?>
                        <p class="gpa-note gpa-note-warning">
                            <i class="fas fa-info-circle"></i>
                            No complete grades yet. Subjects marked INC or without both grades are excluded from GPA calculation.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
