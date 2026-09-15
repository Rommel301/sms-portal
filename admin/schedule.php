
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/admin_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    die("Database connection failed. Please check your configuration.");
}

require_once 'enrollment_data.php';

$students = get_enrolled_students($db);
$selected_student_id = $_GET['student_id'] ?? ($students[0]['student_id'] ?? null);
$schedule = $selected_student_id ? get_schedule_for_student($db, $selected_student_id) : [];

// Standard time slots used in the schedule dropdown
$time_slots = [
    '7:00 - 8:30 AM',
    '8:30 - 10:00 AM',
    '10:00 - 11:30 AM',
    '11:30 AM - 1:00 PM',
    '1:00 - 2:30 PM',
    '2:30 - 4:00 PM',
    '4:00 - 5:30 PM',
    '5:30 - 7:00 PM',
];

// TODO: replace with a DB-driven list (e.g. get_subjects($db)) if you have a subjects table
$subject_list = [
    'Mathematics',
    'Science',
    'English',
    'Filipino',
    'Social Studies',
    'Computer Science',
    'Physical Education',
    'Values Education',
];

// TODO: replace with a DB-driven list (e.g. get_rooms($db)) if you track rooms separately
$room_list = [
    'Room 101',
    'Room 102',
    'Room 103',
    'Room 201',
    'Room 202',
    'Room 203',
    'Computer Lab',
    'Science Lab',
    'Gymnasium',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_entry'])) {
        $student_id = (int) $_POST['student_id'];
        add_schedule_entry($db, $student_id, [
            'day'     => $_POST['day'],
            'time'    => $_POST['time'],
            'subject' => $_POST['subject'],
            'room'    => $_POST['room'],
        ]);
        header("Location: schedule.php?student_id=" . $student_id);
        exit;
    }

    if (isset($_POST['delete_entry'])) {
        $student_id = (int) $_POST['student_id'];
        delete_schedule_entry($db, (int) $_POST['entry_id']);
        header("Location: schedule.php?student_id=" . $student_id);
        exit;
    }
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
 
<body class="admin-page">

<div class="sidebar">
    <h2><i class="fas fa-tools"></i> <span>Admin Panel</span></h2>
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="students.php"><i class="fas fa-users"></i> <span>Students</span></a>
        <a href="enrollments.php"><i class="fas fa-clipboard-list"></i> <span>Enrollments</span></a>
        <a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a>
        <a href="schedule.php" class="active"><i class="fas fa-calendar-alt"></i> <span>Schedule</span></a>
        <a href="grades.php"><i class="fas fa-chart-line"></i> <span>Grades</span></a>
        <a href="documents.php"><i class="fas fa-file-alt"></i> <span>Documents</span></a>
    </div>
    <div class="logout-section">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <h1><i class="fas fa-calendar-alt"></i> Schedule</h1>
        <span><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
    </div>

    <div class="enrollments-container">

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No enrolled students yet.</p>
            </div>
        <?php else: ?>

            <!-- STUDENT SELECTOR -->
            <form method="GET" class="student-select-form">
                <span class="student-label">
                    <i class="fas fa-user-graduate"></i> Student:
                </span>
                <select name="student_id" onchange="this.form.submit()" class="student-select">
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['student_id']; ?>" <?php echo ($s['student_id'] == $selected_student_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['full_name'] . ' — ' . $s['program'] . ' (' . $s['year_level'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- ADD SCHEDULE FORM (FIXED ALIGNMENT) -->
            <form method="POST" class="schedule-form">
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selected_student_id); ?>">
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-calendar-day"></i> Day</label>
                    <select name="day" required class="form-select">
                        <option value="">Select Day</option>
                        <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                            <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-clock"></i> Time</label>
                    <select name="time" required class="form-select">
                        <option value="">Select Time</option>
                        <?php foreach ($time_slots as $slot): ?>
                            <option value="<?php echo htmlspecialchars($slot); ?>"><?php echo htmlspecialchars($slot); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-book-open"></i> Subject</label>
                    <select name="subject" required class="form-select">
                        <option value="">Select Subject</option>
                        <?php foreach ($subject_list as $subj): ?>
                            <option value="<?php echo htmlspecialchars($subj); ?>"><?php echo htmlspecialchars($subj); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-door-open"></i> Room</label>
                    <select name="room" required class="form-select">
                        <option value="">Select Room</option>
                        <?php foreach ($room_list as $rm): ?>
                            <option value="<?php echo htmlspecialchars($rm); ?>"><?php echo htmlspecialchars($rm); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- BUTTON WRAPPER FOR PERFECT BASELINE -->
                <div class="form-group button-group">
                    <button type="submit" name="add_entry" class="btn-add-schedule">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </form>

            <!-- SCHEDULE TABLE -->
            <div class="schedule-table-wrapper">
                <?php if (empty($schedule)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <p>No schedule assigned to this student yet.</p>
                    </div>
                <?php else: ?>
                    <table class="enrollment-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Subject</th>
                                <th>Room</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($schedule as $entry): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($entry['day']); ?></strong></td>
                                <td><?php echo htmlspecialchars($entry['time']); ?></td>
                                <td><span class="subject-tag"><?php echo htmlspecialchars($entry['subject']); ?></span></td>
                                <td><span class="room-tag"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($entry['room']); ?></span></td>
                                <td class="actions-column">
                                    <form method="POST" onsubmit="return confirm('Remove this entry?');">
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selected_student_id); ?>">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" name="delete_entry" class="btn-remove-schedule">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
