
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
$student_name = $_SESSION['student_name'] ?? 'Student';

// Get enrollment data for status
$data = get_enrollment_data($db, $student_id);
$student = $data['student'];
$enrollment_status = $data['enrollment_status'];

// Get student information
$first_name = $student['first_name'] ?? '';
$middle_name = $student['middle_name'] ?? '';
$last_name = $student['last_name'] ?? '';
$email = $student['email'] ?? '';
$phone = $student['phone'] ?? '';
$address = $student['address'] ?? '';
$date_of_birth = $student['date_of_birth'] ?? '';
$gender = $student['gender'] ?? '';
$program = $student['program'] ?? '';
$year_level = $student['year_level'] ?? '';

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    
    // Validate
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First Name, Last Name, and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Update student profile
            $stmt = $db->prepare("
                UPDATE students 
                SET first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    program = :program,
                    year_level = :year_level
                WHERE student_id = :student_id
            ");
            
            $stmt->execute([
                ':first_name' => $first_name,
                ':middle_name' => $middle_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':date_of_birth' => $date_of_birth,
                ':gender' => $gender,
                ':program' => $program,
                ':year_level' => $year_level,
                ':student_id' => $student_id
            ]);
            
            // Update session name
            $_SESSION['student_name'] = $first_name . ' ' . $last_name;
            
            $success_message = "✅ Your profile has been updated successfully!";
            
            // Refresh student data
            $data = get_enrollment_data($db, $student_id);
            $student = $data['student'];
            
            // Refresh variables
            $first_name = $student['first_name'] ?? '';
            $middle_name = $student['middle_name'] ?? '';
            $last_name = $student['last_name'] ?? '';
            $email = $student['email'] ?? '';
            $phone = $student['phone'] ?? '';
            $address = $student['address'] ?? '';
            $date_of_birth = $student['date_of_birth'] ?? '';
            $gender = $student['gender'] ?? '';
            $program = $student['program'] ?? '';
            $year_level = $student['year_level'] ?? '';
            
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error_message = "An error occurred while updating your profile. Please try again.";
        }
    }
}

$current_page = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: #4299e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 48px;
            color: white;
        }
        
        .profile-header h2 {
            margin: 0 0 5px 0;
            color: #2d3748;
        }
        
        .profile-header .student-id {
            color: #718096;
            font-size: 14px;
        }
        
        .profile-header .status-badge {
            display: inline-block;
            padding: 4px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .status-enrolled {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background: #fefcbf;
            color: #975a16;
        }
        
        .status-rejected {
            background: #fed7d7;
            color: #9b2c2c;
        }
        
        .status-notenrolled {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .profile-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .profile-form .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .profile-form .form-group {
            margin-bottom: 20px;
        }
        
        .profile-form .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .profile-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #4a5568;
            font-size: 14px;
        }
        
        .profile-form label .required {
            color: #e53e3e;
        }
        
        .profile-form input,
        .profile-form select,
        .profile-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .profile-form input:focus,
        .profile-form select:focus,
        .profile-form textarea:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .profile-form textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .profile-form .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-save {
            background: #4299e1;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-save:hover {
            background: #3182ce;
        }
        
        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-cancel {
            background: #e2e8f0;
            color: #4a5568;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background: #cbd5e0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #9b2c2c;
            border-left: 4px solid #fc8181;
        }
        
        .alert-info {
            background: #bee3f8;
            color: #2a69ac;
            border-left: 4px solid #4299e1;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        .profile-completion {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .profile-completion .completion-bar {
            background: #e2e8f0;
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .profile-completion .completion-bar .fill {
            background: #48bb78;
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        .profile-completion .completion-text {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #4a5568;
        }
        
        .readonly-field {
            background: #f7fafc;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .profile-form .form-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-form .form-group.full-width {
                grid-column: 1;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
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
        
        <a href="profile.php" class="active">
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

<!-- MAIN CONTENT -->
<div class="main">
    <div class="topbar">
        <h1>
            <i class="fas fa-user"></i>
            My Profile
        </h1>
        <a href="dashboard.php" class="topbar-profile">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <span><?php echo htmlspecialchars(trim($first_name . ' ' . $last_name) ?: $student_name); ?></span>
        </a>
    </div>
    
    <div class="profile-container">
        
        <!-- PROFILE HEADER -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h2><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h2>
            <div class="student-id">
                <i class="fas fa-id-card"></i> Student ID: <?php echo htmlspecialchars($student_id); ?>
            </div>
            <?php
            $status_class = 'status-notenrolled';
            $status_text = 'Not Enrolled';
            if ($enrollment_status === 'Enrolled') {
                $status_class = 'status-enrolled';
                $status_text = '✅ Enrolled';
            } elseif ($enrollment_status === 'Pending') {
                $status_class = 'status-pending';
                $status_text = '⏳ Pending';
            } elseif ($enrollment_status === 'Rejected') {
                $status_class = 'status-rejected';
                $status_text = '❌ Rejected';
            }
            ?>
            <span class="status-badge <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </span>
        </div>
        
        <!-- PROFILE COMPLETION -->
        <?php
        // Calculate profile completion
        $fields = [
            'first_name' => !empty($first_name),
            'last_name' => !empty($last_name),
            'email' => !empty($email),
            'phone' => !empty($phone),
            'address' => !empty($address),
            'date_of_birth' => !empty($date_of_birth),  // FIXED: changed from birth_date to date_of_birth
            'gender' => !empty($gender),
            'program' => !empty($program),
            'year_level' => !empty($year_level)
        ];
        $total_fields = count($fields);
        $completed_fields = count(array_filter($fields));
        $completion_percentage = round(($completed_fields / $total_fields) * 100);
        ?>
        <div class="profile-completion">
            <div class="completion-text">
                <span><strong>Profile Completion</strong></span>
                <span><?php echo $completion_percentage; ?>% (<?php echo $completed_fields; ?>/<?php echo $total_fields; ?> fields)</span>
            </div>
            <div class="completion-bar">
                <div class="fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
            </div>
            <?php if ($completion_percentage < 100): ?>
                <p style="color:#718096; font-size:13px; margin-top:10px;">
                    <i class="fas fa-info-circle"></i> 
                    Complete all fields to proceed with enrollment.
                </p>
            <?php endif; ?>
        </div>
        
        <!-- SUCCESS/ERROR MESSAGES -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($enrollment_status === 'Rejected'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Your enrollment was rejected.</strong> Please update your profile information and try again.
            </div>
        <?php endif; ?>
        
        <!-- PROFILE FORM -->
        <form method="POST" class="profile-form">
            <h3 style="margin-top:0; color:#2d3748;">
                <i class="fas fa-edit"></i> Personal Information
            </h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($middle_name); ?>">
                </div>
                
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth); ?>">
                </div>
                
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="09XX-XXX-XXXX">
                </div>
                
                <div class="form-group">
                    <label>Program / Course</label>
                    <select name="program">
                        <option value="">Select Program</option>
                        <option value="BSIT" <?php echo $program === 'BSIT' ? 'selected' : ''; ?>>BS Information Technology</option>
                        <option value="BSCS" <?php echo $program === 'BSCS' ? 'selected' : ''; ?>>BS Computer Science</option>
                        <option value="BSBA" <?php echo $program === 'BSBA' ? 'selected' : ''; ?>>BS Business Administration</option>
                        <option value="BSED" <?php echo $program === 'BSED' ? 'selected' : ''; ?>>Bachelor of Secondary Education</option>
                        <option value="BSCE" <?php echo $program === 'BSCE' ? 'selected' : ''; ?>>BS Civil Engineering</option>
                        <option value="BSN" <?php echo $program === 'BSN' ? 'selected' : ''; ?>>BS Nursing</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year_level">
                        <option value="">Select Year Level</option>
                        <option value="1st Year" <?php echo $year_level === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo $year_level === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo $year_level === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4th Year" <?php echo $year_level === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                        <option value="5th Year" <?php echo $year_level === '5th Year' ? 'selected' : ''; ?>>5th Year</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label>Address</label>
                    <textarea name="address" placeholder="Enter your complete address"><?php echo htmlspecialchars($address); ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="dashboard.php" class="btn-cancel">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </div>
        </form>
        
        <!-- ADDITIONAL INFO -->
        <div style="background:white; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-top:20px;">
            <h4 style="margin:0 0 10px 0; color:#2d3748;">
                <i class="fas fa-info-circle"></i> Account Information
            </h4>
            <table style="width:100%; font-size:14px;">
                <tr>
                    <td style="padding:5px 0; color:#718096; width:150px;"><strong>Student ID</strong></td>
                    <td style="padding:5px 0;"><?php echo htmlspecialchars($student_id); ?></td>
                </tr>
                <tr>
                    <td style="padding:5px 0; color:#718096;"><strong>Account Status</strong></td>
                    <td style="padding:5px 0;">
                        <span style="color:#48bb78;">✅ Active</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:5px 0; color:#718096;"><strong>Enrollment Status</strong></td>
                    <td style="padding:5px 0;">
                        <span class="status-badge <?php echo $status_class; ?>" style="padding:2px 12px; font-size:12px;">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($student['created_at'])): ?>
                <tr>
                    <td style="padding:5px 0; color:#718096;"><strong>Account Created</strong></td>
                    <td style="padding:5px 0;"><?php echo date('F d, Y', strtotime($student['created_at'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
    </div>
</div>

<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>

</body>
</html>
