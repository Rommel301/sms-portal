
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/student_db.php';

$studentDatabase = new StudentDatabase();
$student_db = $studentDatabase->getConnection();

$student_id = $_GET['id'] ?? ($_POST['student_id'] ?? null);

if (!$student_id || !ctype_digit((string) $student_id)) {
    header("Location: students.php");
    exit();
}

$student = null;
$error = '';
$success = '';

// Fetch student
try {
    $stmt = $student_db->prepare("SELECT * FROM students WHERE student_id = :id LIMIT 1");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: students.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("student_edit.php fetch error: " . $e->getMessage());
    $error = "Unable to load student information.";
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $status     = $_POST['status'] ?? 'active';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "First Name, Last Name, and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = "Invalid status value.";
    } else {
        try {
            $update = $student_db->prepare("
                UPDATE students
                SET first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    status = :status
                WHERE student_id = :student_id
            ");
            $update->execute([
                ':first_name' => $first_name,
                ':last_name'  => $last_name,
                ':email'      => $email,
                ':phone'      => $phone,
                ':address'    => $address,
                ':status'     => $status,
                ':student_id' => $student_id
            ]);

            $success = "✅ Student information updated successfully.";

            // Refresh data
            $stmt = $student_db->prepare("SELECT * FROM students WHERE student_id = :id LIMIT 1");
            $stmt->execute([':id' => $student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("student_edit.php update error: " . $e->getMessage());
            $error = "An error occurred while updating. Please try again.";
        }
    }
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Student - Admin Portal</title>
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
        <h1><i class="fas fa-user-edit"></i> Edit Student</h1>
        <span><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
    </div>

    <div class="edit-container">

        <a href="student_view.php?id=<?php echo htmlspecialchars($student_id); ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Student Details
        </a>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="edit-card">
            <form method="POST">
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Admission Number</label>
                        <input type="text" class="readonly-field" value="<?php echo htmlspecialchars($student['admission_number'] ?? ''); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo ($student['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($student['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="student_view.php?id=<?php echo htmlspecialchars($student_id); ?>" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" name="update_student" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>

</body>
</html>
