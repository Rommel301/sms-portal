
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

$data = get_enrollment_data($db, $student_id);

$student           = $data['student'];
$profile_complete  = $data['profile_complete'];
$enrollment_status = $data['enrollment_status'];
$course_count      = $data['course_count'];

// Get student first name
$student_name = $student['first_name'] ?? '';
if (empty($student_name)) {
    $student_name = $_SESSION['student_name'] ?? 'Student';
}

/*
|--------------------------------------------------------------------------
| Student information
|--------------------------------------------------------------------------
*/
$full_name = trim(
    ($student['first_name'] ?? '') . ' ' .
    ($student['middle_name'] ?? '') . ' ' .
    ($student['last_name'] ?? '')
);

if (empty($full_name)) {
    $full_name = $student_name;
}

$student_address  = $student['address'] ?? '';
$contact_number   = $student['phone'] ?? $student['contact_number'] ?? $student['contact_no'] ?? '';
$email            = $student['email'] ?? '';
$admission_number = $student['admission_number'] ?? $student_id;

$program   = $student['program'] ?? '';
$year      = $student['year_level'] ?? '';

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/
$error_message = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'already_submitted') {
        $error_message = "You already have a pending enrollment. Please wait for admin review.";
    } elseif ($_GET['error'] === 'failed') {
        $error_message = "There was an error submitting your enrollment. Please try again.";
    }
}

$is_retry = isset($_GET['retry']);

/*
|--------------------------------------------------------------------------
| Get existing enrollment details from admin_db
|--------------------------------------------------------------------------
*/
$existing_enrollment = null;
$enrollment_remarks = '';

try {
    $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
    $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $admin_db->prepare("
        SELECT * FROM enrollments
        WHERE student_id = :student_id
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([':student_id' => $student_id]);
    $existing_enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_enrollment && !empty($existing_enrollment['remarks'])) {
        $enrollment_remarks = $existing_enrollment['remarks'];
    }

} catch (PDOException $e) {
    error_log("Error fetching enrollment: " . $e->getMessage());
}

$current_page = 'enrollment.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment — Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        <a href="dashboard.php">
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

            <a href="enrollment.php" class="active">
                <i class="fas fa-clipboard-list"></i>
                <span>Enrollment</span>
            </a>

            <div class="sidebar-disabled">
                <i class="fas fa-lock"></i>
                <span>My Subjects</span>
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
                <span>My Documents </span>
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
            <i class="fas fa-clipboard-list"></i>
            Enrollment
        </h1>

        <a href="profile.php" class="topbar-profile">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <span>
                <?php echo htmlspecialchars($full_name); ?>
            </span>
        </a>
    </div>

    <div class="enrollment-container">

        <div class="enrollment-header">
            <h2>Student Enrollment</h2>
            <p>Complete the enrollment process and submit your requirements for administrator review.</p>
        </div>

        <!-- ERROR MESSAGE -->
        <?php if (!empty($error_message)): ?>
            <div class="notice notice-error">
                <div class="notice-icon error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="notice-content">
                    <h3 class="notice-title error-title">Notice</h3>
                    <p class="notice-text"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- 1. PROFILE INCOMPLETE -->
        <?php if (!$profile_complete): ?>
            <div class="notice notice-rejected">
                <div class="notice-icon error-icon">
                    <i class="fas fa-circle-exclamation"></i>
                </div>
                <div class="notice-content">
                    <h3 class="notice-title error-title">Profile Incomplete</h3>
                    <p class="notice-text">
                        Please complete your student profile before starting your enrollment.
                    </p>
                    <div style="margin-top: 15px;">
                        <a href="profile.php" class="action-btn">
                            <i class="fas fa-user-edit"></i> Complete My Profile
                        </a>
                    </div>
                </div>
            </div>

        <!-- 2. ENROLLMENT PENDING (ONLY SHOWS THIS SCREEN, HIDES THE FORM) -->
        <?php elseif ($enrollment_status === 'Pending'): ?>
            <div class="notice notice-pending">
                <div class="notice-icon pending-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="notice-content">
                    <h3 class="notice-title pending-title">⏳ Enrollment Pending</h3>
                    <p class="notice-text">
                        <strong><?php echo htmlspecialchars($full_name); ?></strong>, your enrollment has been submitted and is currently waiting for administrator review.
                    </p>
                    <div class="notice-info-box pending-info">
                        <p>
                            <i class="fas fa-clock"></i>
                            <strong>Status:</strong> Your enrollment is pending approval. The admin will review your documents and information shortly.
                        </p>
                    </div>
                    <div class="notice-status">
                        <span class="status-pill status-pending">
                            <i class="fas fa-clock"></i> Pending Review
                        </span>
                    </div>
                    <?php if (!empty($enrollment_remarks)): ?>
                        <div class="notice-remarks">
                            <strong><i class="fas fa-comment"></i> Admin Remarks:</strong>
                            <p><?php echo htmlspecialchars($enrollment_remarks); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="notice-actions" style="margin-top: 20px;">
                        <a href="dashboard.php" class="action-btn action-btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

        <!-- 3. ENROLLMENT APPROVED / ENROLLED -->
        <?php elseif ($enrollment_status === 'Enrolled'): ?>
            <div class="notice notice-approved">
                <div class="notice-icon success-icon">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div class="notice-content">
                    <h3 class="notice-title success-title">🎉 Congratulations, <?php echo htmlspecialchars($full_name); ?>!</h3>
                    <p class="notice-text">
                        Your enrollment has been approved. You are now officially enrolled!
                    </p>
                    <div class="notice-info-box success-info">
                        <p>
                            <i class="fas fa-check-circle"></i>
                            <strong>Status:</strong> You are now officially enrolled for this semester.
                        </p>
                    </div>
                    <div class="notice-status">
                        <span class="status-pill status-approved">
                            <i class="fas fa-check-circle"></i> Enrolled
                        </span>
                    </div>
                    <?php if (!empty($enrollment_remarks)): ?>
                        <div class="notice-remarks">
                            <strong><i class="fas fa-comment"></i> Admin Remarks:</strong>
                            <p><?php echo htmlspecialchars($enrollment_remarks); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="notice-actions" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="subjects.php" class="action-btn action-btn-success">
                            <i class="fas fa-book-open"></i> View My Subjects
                        </a>
                        <a href="schedule.php" class="action-btn action-btn-primary">
                            <i class="fas fa-calendar"></i> View My Schedule
                        </a>
                    </div>
                </div>
            </div>

        <!-- 4. ENROLLMENT REJECTED -->
        <?php elseif ($enrollment_status === 'Rejected' && !$is_retry): ?>
            <div class="notice notice-rejected">
                <div class="notice-icon error-icon">
                    <i class="fas fa-circle-xmark"></i>
                </div>
                <div class="notice-content">
                    <h3 class="notice-title error-title">Enrollment Rejected</h3>
                    <p class="notice-text">
                        <strong><?php echo htmlspecialchars($full_name); ?></strong>, your enrollment was rejected by the administrator.
                    </p>
                    <?php if (!empty($enrollment_remarks)): ?>
                        <div class="notice-remarks error-remarks">
                            <strong><i class="fas fa-comment"></i> Admin Remarks:</strong>
                            <p><?php echo htmlspecialchars($enrollment_remarks); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="notice-actions" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="profile.php" class="action-btn action-btn-primary">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                        <a href="enrollment.php?retry=1" class="action-btn action-btn-success">
                            <i class="fas fa-redo"></i> Try Again
                        </a>
                    </div>
                </div>
            </div>

        <!-- 5. NOT ENROLLED (SHOW WIZARD FORM) -->
        <?php else: ?>
            <div class="wizard">
                <!-- Progress -->
                <div class="wizard-progress">
                    <div class="progress-item active" id="progress-1"></div>
                    <div class="progress-item" id="progress-2"></div>
                    <div class="progress-item" id="progress-3"></div>
                    <div class="progress-item" id="progress-4"></div>
                </div>

                <form method="POST" action="start_enrollment.php" enctype="multipart/form-data" id="enrollmentForm">

                    <!-- STEP 1 -->
                    <div class="wizard-step active" id="step-1">
                        <h3 class="step-title">Step 1 — Student Information</h3>
                        <p class="step-description">Confirm your personal information before continuing.</p>

                        <div class="field">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" readonly>
                        </div>

                        <div class="field">
                            <label>Student ID / Admission No.</label>
                            <input type="text" name="student_id" value="<?php echo htmlspecialchars($admission_number); ?>" readonly>
                        </div>

                        <!-- ADD DATE OF BIRTH FIELD -->
                        <div class="field">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>" readonly>
                        </div>

                        <div class="field">
                            <label>Address</label>
                            <textarea name="address" required><?php echo htmlspecialchars($student_address); ?></textarea>
                        </div>

                        <div class="field">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?php echo htmlspecialchars($contact_number); ?>" required>
                        </div>

                        <div class="field">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <div class="wizard-actions">
                            <span></span>
                            <button type="button" class="action-btn" onclick="nextStep(2)">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2 -->
                    <div class="wizard-step" id="step-2">
                        <h3 class="step-title">Step 2 — Enrollment Information</h3>
                        <p class="step-description">Provide the academic information for this enrollment.</p>

                        <div class="field">
                            <label>Program / Course</label>
                            <select name="program" required>
                                <option value="">Select Program / Course</option>
                                <option value="BSIT" <?php echo $program === 'BSIT' ? 'selected' : ''; ?>>BS Information Technology</option>
                                <option value="BSCS" <?php echo $program === 'BSCS' ? 'selected' : ''; ?>>BS Computer Science</option>
                                <option value="BSBA" <?php echo $program === 'BSBA' ? 'selected' : ''; ?>>BS Business Administration</option>
                                <option value="BSED" <?php echo $program === 'BSED' ? 'selected' : ''; ?>>Bachelor of Secondary Education</option>
                            </select>
                        </div>

                        <div class="field">
                            <label>Year Level</label>
                            <select name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1st Year" <?php echo $year === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2nd Year" <?php echo $year === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3rd Year" <?php echo $year === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4th Year" <?php echo $year === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>

                        <div class="field">
                            <label>Semester</label>
                            <select name="semester" required>
                                <option value="">Select Semester</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                            </select>
                        </div>

                        <div class="field">
                            <label>Academic Year</label>
                            <select name="academic_year" required>
                                <option value="">Select Academic Year</option>
                                <option value="2026-2027">2026-2027</option>
                                <option value="2027-2028">2027-2028</option>
                            </select>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn-back" onclick="nextStep(1)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="action-btn" onclick="nextStep(3)">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3 -->
                    <div class="wizard-step" id="step-3">
                        <h3 class="step-title">Step 3 — Documents / Requirements</h3>
                        <p class="step-description">Upload the required documents for verification.</p>

                        <div class="document-item">
                            <label>Birth Certificate</label>
                            <small>Upload your birth certificate (JPG, PNG, or PDF, max 5MB).</small>
                            <input type="file" name="birth_certificate" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>

                        <div class="document-item">
                            <label>Valid ID / School ID</label>
                            <small>Upload a valid government ID or school ID (JPG, PNG, or PDF, max 5MB).</small>
                            <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>

                        <div class="document-item">
                            <label>2×2 ID Picture</label>
                            <small>Upload your recent 2×2 ID picture (JPG or PNG, max 5MB).</small>
                            <input type="file" name="id_picture" accept=".jpg,.jpeg,.png" required>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="btn-back" onclick="nextStep(2)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="action-btn" onclick="prepareReview()">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 4 -->
                    <div class="wizard-step" id="step-4">
                        <h3 class="step-title">Step 4 — Review & Submit</h3>
                        <p class="step-description">Review your information and documents before submitting.</p>

                        <div class="review-box">
                            <h4><i class="fas fa-user"></i> Student Information</h4>
                            <div class="review-row"><span class="review-label">Full Name</span><span class="review-value" id="reviewName">—</span></div>
                            <div class="review-row"><span class="review-label">Student ID</span><span class="review-value" id="reviewStudentId">—</span></div>
                            <div class="review-row"><span class="review-label">Address</span><span class="review-value" id="reviewAddress">—</span></div>
                            <div class="review-row"><span class="review-label">Contact Number</span><span class="review-value" id="reviewContact">—</span></div>
                            <div class="review-row"><span class="review-label">Email</span><span class="review-value" id="reviewEmail">—</span></div>
                        </div>

                        <div class="review-box">
                            <h4><i class="fas fa-graduation-cap"></i> Enrollment Information</h4>
                            <div class="review-row"><span class="review-label">Program / Course</span><span class="review-value" id="reviewProgram">—</span></div>
                            <div class="review-row"><span class="review-label">Year Level</span><span class="review-value" id="reviewYear">—</span></div>
                            <div class="review-row"><span class="review-label">Semester</span><span class="review-value" id="reviewSemester">—</span></div>
                            <div class="review-row"><span class="review-label">Academic Year</span><span class="review-value" id="reviewAcademicYear">—</span></div>
                        </div>

                        <div class="review-box">
                            <h4><i class="fas fa-folder-open"></i> Documents</h4>
                            <div class="review-row"><span class="review-label">Birth Certificate</span><span class="review-value" id="reviewBirth">—</span></div>
                            <div class="review-row"><span class="review-label">Valid ID / School ID</span><span class="review-value" id="reviewValidId">—</span></div>
                            <div class="review-row"><span class="review-label">2×2 ID Picture</span><span class="review-value" id="reviewPicture">—</span></div>
                        </div>

                        <label class="confirmation">
                            <input type="checkbox" id="confirmInformation" required>
                            <span>I confirm that all information and documents submitted are correct and complete.</span>
                        </label>

                        <div class="wizard-actions">
                            <button type="button" class="btn-back" onclick="nextStep(3)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="action-btn action-btn-success">
                                <i class="fas fa-paper-plane"></i> Submit Enrollment
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        <?php endif; ?>

    </div>

</main>

<script>
function nextStep(step) {
    document.querySelectorAll('.wizard-step').forEach(function(element) {
        element.classList.remove('active');
    });
    document.getElementById('step-' + step).classList.add('active');

    for (let i = 1; i <= 4; i++) {
        const progress = document.getElementById('progress-' + i);
        progress.classList.remove('active', 'done');
        if (i < step) progress.classList.add('done');
        if (i === step) progress.classList.add('active');
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prepareReview() {
    const birth = document.querySelector('[name="birth_certificate"]');
    const validId = document.querySelector('[name="valid_id"]');
    const picture = document.querySelector('[name="id_picture"]');

    if (!birth.files.length || !validId.files.length || !picture.files.length) {
        alert('Please upload all required documents.');
        return;
    }

    document.getElementById('reviewName').textContent = document.querySelector('[name="full_name"]').value;
    document.getElementById('reviewStudentId').textContent = document.querySelector('[name="student_id"]').value;
    document.getElementById('reviewAddress').textContent = document.querySelector('[name="address"]').value;
    document.getElementById('reviewContact').textContent = document.querySelector('[name="contact_number"]').value;
    document.getElementById('reviewEmail').textContent = document.querySelector('[name="email"]').value;
    document.getElementById('reviewProgram').textContent = document.querySelector('[name="program"]').value;
    document.getElementById('reviewYear').textContent = document.querySelector('[name="year_level"]').value;
    document.getElementById('reviewSemester').textContent = document.querySelector('[name="semester"]').value;
    document.getElementById('reviewAcademicYear').textContent = document.querySelector('[name="academic_year"]').value;
    document.getElementById('reviewBirth').textContent = birth.files[0].name;
    document.getElementById('reviewValidId').textContent = validId.files[0].name;
    document.getElementById('reviewPicture').textContent = picture.files[0].name;

    nextStep(4);
}

document.getElementById('enrollmentForm')?.addEventListener('submit', function(event) {
    const confirmation = document.getElementById('confirmInformation');
    if (!confirmation.checked) {
        event.preventDefault();
        alert('Please confirm that all information and documents are correct.');
    }
});
</script>

</body>
</html>
