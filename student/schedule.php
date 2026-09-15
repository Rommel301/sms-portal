
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
| SCHEDULE — pulled from admin_db.student_schedule
|--------------------------------------------------------------------------
*/

$schedule = [];

try {
    $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
    $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $admin_db->prepare("
        SELECT day, time, subject, room
        FROM student_schedule
        WHERE student_id = :student_id
        ORDER BY FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), time ASC
    ");
    $stmt->execute([':student_id' => $student_id]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching schedule: " . $e->getMessage());
    $schedule = [];
}

$current_page = 'schedule.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Schedule — Student Portal</title>

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

        <a href="subjects.php">
            <i class="fas fa-book-open"></i>
            My Subjects
        </a>

        <a href="grades.php">
            <i class="fas fa-chart-column"></i>
            My Grades
        </a>

        <a href="schedule.php" class="active">
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
                <i class="fas fa-calendar-days"></i>
                My Schedule
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


    <div class="info-card">

        <h3>
            <i class="fas fa-calendar-days"></i>
            Weekly Schedule
        </h3>

        <p>
            Your class schedule will appear here once it has
            been assigned by the administrator.
        </p>

    </div>


    <?php if (empty($schedule)): ?>

        <div class="info-card">

            <h3>
                <i class="fas fa-clock"></i>
                No Schedule Available
            </h3>

            <p>
                You are officially enrolled, but your class
                schedule has not been assigned yet.
            </p>

        </div>

    <?php else: ?>

        <div class="schedule-wrapper">

            <table class="schedule-table">

                <thead>

                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Room</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($schedule as $s): ?>

                    <tr>

                        <td>
                            <span class="day-badge">
                                <?php echo htmlspecialchars($s['day']); ?>
                            </span>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($s['time']); ?>
                        </td>

                        <td class="subject-name">
                            <?php echo htmlspecialchars($s['subject']); ?>
                        </td>

                        <td>
                            <span class="room-badge">
                                <?php echo htmlspecialchars($s['room']); ?>
                            </span>
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
