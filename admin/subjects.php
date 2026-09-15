
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/admin_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    die("Database connection failed. Please check your configuration.");
}

require_once 'enrollment_data.php';
require_once 'subject_data.php';

$students = get_enrolled_students($db);

$selected_student_id = $_GET['student_id'] ?? ($students[0]['student_id'] ?? null);
$subjects = $selected_student_id ? get_subjects_for_student($db, $selected_student_id) : [];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        $student_id = (int) $_POST['student_id'];
        $ok = add_subject_for_student($db, $student_id, [
            'subject_code' => $_POST['subject_code'],
            'subject_name' => $_POST['subject_name'],
            'units'        => $_POST['units'],
            'instructor'   => $_POST['instructor'],
            'schedule'     => $_POST['schedule'],
        ]);
        $message = $ok ? 'Subject added.' : 'Failed to add subject.';
        header("Location: subjects.php?student_id=" . $student_id);
        exit;
    }

    if (isset($_POST['delete_subject'])) {
        $student_id = (int) $_POST['student_id'];
        delete_subject($db, (int) $_POST['subject_row_id']);
        header("Location: subjects.php?student_id=" . $student_id);
        exit;
    }
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Get selected student program
$selected_program = '';
foreach ($students as $s) {
    if ($s['student_id'] == $selected_student_id) {
        $selected_program = $s['program'] ?? 'N/A';
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects - Admin Portal</title>
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
        <a href="subjects.php" class="active"><i class="fas fa-book"></i> <span>Subjects</span></a>
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
        <h1><i class="fas fa-book"></i> Subjects</h1>
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
            <div class="student-selector-wrapper">
                <form method="GET">
                    <select name="student_id" onchange="this.form.submit()">
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['student_id']; ?>" <?php echo ($s['student_id'] == $selected_student_id) ? 'selected' : ''; ?>>
                                <?php 
                                $last_name = $s['last_name'] ?? '';
                                $first_name = $s['first_name'] ?? '';
                                $program = $s['program'] ?? '';
                                $year_level = $s['year_level'] ?? '';
                                
                                if (empty($last_name) && empty($first_name)) {
                                    try {
                                        $name_stmt = $db->prepare("
                                            SELECT full_name FROM enrollments 
                                            WHERE student_id = :student_id 
                                            AND status = 'active' 
                                            LIMIT 1
                                        ");
                                        $name_stmt->execute([':student_id' => $s['student_id']]);
                                        $enrollment = $name_stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($enrollment && !empty($enrollment['full_name'])) {
                                            $display_name = $enrollment['full_name'] . ' — ' . $program . ' (' . $year_level . ')';
                                        } else {
                                            $display_name = 'Student #' . $s['student_id'] . ' — ' . $program . ' (' . $year_level . ')';
                                        }
                                    } catch (PDOException $e) {
                                        $display_name = 'Student #' . $s['student_id'] . ' — ' . $program . ' (' . $year_level . ')';
                                    }
                                } else {
                                    $display_name = $last_name . ', ' . $first_name . ' — ' . $program . ' (' . $year_level . ')';
                                }
                                
                                echo htmlspecialchars($display_name); 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- SUBJECTS HEADER WITH COUNT -->
            <div class="subjects-header">
                <div>
                    <span class="subject-count-badge">
                        <i class="fas fa-book"></i> 
                        <?php echo count($subjects); ?> Subject(s) Assigned
                    </span>
                </div>
                <div class="program-info">
                    <i class="fas fa-graduation-cap"></i> 
                    <?php echo htmlspecialchars($selected_program); ?>
                </div>
            </div>

            <!-- ADD SUBJECT FORM WITH DROPDOWN -->
            <div class="subject-dropdown-container">
                <div class="form-title">
                    <i class="fas fa-plus-circle"></i> Add Subject
                    <span>NEW</span>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selected_student_id); ?>">
                    
                    <div class="subject-form-group">
                        <!-- Subject Code -->
                        <div class="form-field">
                            <label><i class="fas fa-code"></i> Subject Code</label>
                            <select name="subject_code" id="subject_code" class="subject-code-select" required onchange="autoFillSubject()">
                                <option value="">-- Select Code --</option>
                                
                                <?php 
                                $groups = [
                                    '1st Year Subjects' => ['101', '102', '103', '104', '105'],
                                    '2nd Year Subjects' => ['201', '202', '203', '204', '205'],
                                    '3rd Year Subjects' => ['301', '302', '303', '304'],
                                    '4th Year Subjects' => ['401', '402', '403', '404'],
                                    'General Education' => ['G101', 'G102', 'G103', 'G104']
                                ];
                                
                                foreach ($groups as $group_name => $codes):
                                ?>
                                    <optgroup label="<?php echo $group_name; ?>">
                                        <?php foreach ($codes as $code): ?>
                                            <?php if (isset($subject_list[$code])): ?>
                                                <option value="<?php echo $code; ?>">
                                                    <?php echo $code . ' - ' . $subject_list[$code]['name']; ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                
                                <option value="custom">-- Custom (Manual Entry) --</option>
                            </select>
                        </div>
                        
                        <!-- Subject Name -->
                        <div class="form-field">
                            <label><i class="fas fa-book"></i> Subject Name</label>
                            <input type="text" name="subject_name" id="subject_name" class="subject-input" required>
                        </div>
                        
                        <!-- Units -->
                        <div class="form-field">
                            <label><i class="fas fa-calculator"></i> Units</label>
                            <input type="number" name="units" id="units" class="subject-input" step="0.5" required>
                        </div>
                        
                        <!-- Instructor -->
                        <div class="form-field">
                            <label><i class="fas fa-user-tie"></i> Instructor</label>
                            <input type="text" name="instructor" id="instructor" class="subject-input" required>
                        </div>
                        
                        <!-- Schedule -->
                        <div class="form-field">
                            <label><i class="fas fa-clock"></i> Schedule</label>
                            <input type="text" name="schedule" id="schedule" class="subject-input" placeholder="MWF 9:00-10:00" required>
                        </div>
                        
                        <!-- Add Button -->
                        <div class="form-field button-field">
                            <button type="submit" name="add_subject" class="btn-add-subject">
                                <i class="fas fa-plus"></i> Add Subject
                            </button>
                        </div>
                    </div>
                    
                    <div class="quick-add-hint">
                        <i class="fas fa-info-circle"></i>
                        Select a subject code to auto-fill all fields, or choose "Custom" to enter manually.
                    </div>
                </form>
            </div>

            <!-- SUBJECTS TABLE -->
            <?php if (empty($subjects)): ?>
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <p>No subjects assigned to this student yet.</p>
                </div>
            <?php else: ?>
                <div class="subjects-table-wrapper">
                    <table class="enrollment-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject</th>
                                <th>Units</th>
                                <th>Instructor</th>
                                <th>Schedule</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subjects as $subj): ?>
                            <tr>
                                <td><strong class="subject-code-tag"><?php echo htmlspecialchars($subj['subject_code'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($subj['subject_name'] ?? ''); ?></td>
                                <td><span class="subject-units-tag"><?php echo htmlspecialchars($subj['units'] ?? ''); ?></span></td>
                                <td><?php echo htmlspecialchars($subj['instructor'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($subj['schedule'] ?? ''); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Remove this subject?');">
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selected_student_id); ?>">
                                        <input type="hidden" name="subject_row_id" value="<?php echo $subj['id'] ?? ''; ?>">
                                        <button type="submit" name="delete_subject" class="btn-delete-subject" title="Delete Subject">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
function autoFillSubject() {
    const codeSelect = document.getElementById('subject_code');
    const selectedCode = codeSelect.value;
    
    if (selectedCode === 'custom') {
        document.getElementById('subject_name').value = '';
        document.getElementById('units').value = '';
        document.getElementById('instructor').value = '';
        document.getElementById('schedule').value = '';
        
        document.getElementById('subject_name').readOnly = false;
        document.getElementById('units').readOnly = false;
        document.getElementById('instructor').readOnly = false;
        document.getElementById('schedule').readOnly = false;
        
        document.getElementById('subject_name').classList.remove('auto-filled');
        document.getElementById('units').classList.remove('auto-filled');
        document.getElementById('instructor').classList.remove('auto-filled');
        document.getElementById('schedule').classList.remove('auto-filled');
        return;
    }
    
    const subjectData = <?php echo json_encode($subject_list); ?>;
    
    if (subjectData[selectedCode]) {
        const data = subjectData[selectedCode];
        document.getElementById('subject_name').value = data.name || '';
        document.getElementById('units').value = data.units || '';
        document.getElementById('instructor').value = data.instructor || '';
        document.getElementById('schedule').value = data.schedule || '';
        
        document.getElementById('subject_name').readOnly = true;
        document.getElementById('units').readOnly = true;
        document.getElementById('instructor').readOnly = true;
        document.getElementById('schedule').readOnly = true;
        
        document.getElementById('subject_name').classList.add('auto-filled');
        document.getElementById('units').classList.add('auto-filled');
        document.getElementById('instructor').classList.add('auto-filled');
        document.getElementById('schedule').classList.add('auto-filled');
    } else {
        document.getElementById('subject_name').value = '';
        document.getElementById('units').value = '';
        document.getElementById('instructor').value = '';
        document.getElementById('schedule').value = '';
        
        document.getElementById('subject_name').readOnly = false;
        document.getElementById('units').readOnly = false;
        document.getElementById('instructor').readOnly = false;
        document.getElementById('schedule').readOnly = false;
        
        document.getElementById('subject_name').classList.remove('auto-filled');
        document.getElementById('units').classList.remove('auto-filled');
        document.getElementById('instructor').classList.remove('auto-filled');
        document.getElementById('schedule').classList.remove('auto-filled');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    autoFillSubject();
});
</script>

</body> 
</html>
