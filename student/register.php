
<?php
// student/register.php

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require_once 'config/student_db.php';
require_once '../includes/mail_config.php';

$database = new StudentDatabase();
$db = $database->getConnection();

$success = false;
$error = '';
$generated_admission = '';
$email_sent = false;

// --- CSRF token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['register'])) {

    // --- CSRF check ---
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please refresh and try again.";
    } else {

        // Get form data
        $first_name       = trim($_POST['first_name'] ?? '');
        $last_name        = trim($_POST['last_name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone            = trim($_POST['phone'] ?? '');
        $date_of_birth    = $_POST['date_of_birth'] ?? '';
        $address          = trim($_POST['address'] ?? '');

        // =========================
        // VALIDATION
        // =========================

        if (
            empty($first_name) ||
            empty($last_name) ||
            empty($email) ||
            empty($password) ||
            empty($confirm_password) ||
            empty($phone) ||
            empty($date_of_birth) ||
            empty($address)
        ) {
            $error = "All fields are required!";

        } elseif (strlen($first_name) > 50 || strlen($last_name) > 50) {
            $error = "First and last names must be 50 characters or less.";

        } elseif (strlen($email) > 100) {
            $error = "Email is too long.";

        } elseif (strlen($phone) > 20) {
            $error = "Phone number is too long.";

        } elseif (strlen($address) > 500) {
            $error = "Address is too long.";

        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";

        } elseif (
            strlen($password) < 10 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password)
        ) {
            $error = "Password must be at least 10 characters and include an uppercase letter, a lowercase letter, and a number.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";

        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
            $error = "Invalid date of birth.";

        } else {

            // =========================
            // CHECK EMAIL
            // =========================
            $check_query = "SELECT student_id FROM students WHERE email = :email LIMIT 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([':email' => $email]);

            if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = "Email already registered!";
            } else {

                $year = date('Y');

                try {
                    $db->beginTransaction();

                    // =========================
                    // GENERATE SEQUENTIAL ADMISSION NUMBER
                    // (with FOR UPDATE to prevent race conditions)
                    // =========================
                    $seq_stmt = $db->prepare("
                        SELECT admission_number
                        FROM students
                        WHERE admission_number LIKE :year_prefix
                        ORDER BY admission_number DESC
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $seq_stmt->execute([':year_prefix' => $year . '-%']);
                    $last_record = $seq_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($last_record && !empty($last_record['admission_number'])) {
                        $parts    = explode('-', $last_record['admission_number']);
                        $last_num = (int) end($parts);
                        $next_num = $last_num + 1;
                    } else {
                        $next_num = 1;
                    }

                    $admission_number    = sprintf('%s-%04d', $year, $next_num);
                    $generated_admission = $admission_number;

                    // =========================
                    // HASH PASSWORD
                    // =========================
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // =========================
                    // INSERT STUDENT
                    // =========================
                    $insert_query = "
                        INSERT INTO students (
                            admission_number,
                            first_name,
                            last_name,
                            email,
                            password,
                            phone,
                            date_of_birth,
                            address,
                            enrollment_date,
                            status
                        )
                        VALUES (
                            :admission,
                            :first_name,
                            :last_name,
                            :email,
                            :password,
                            :phone,
                            :dob,
                            :address,
                            CURDATE(),
                            'active'
                        )
                    ";

                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->execute([
                        ':admission'  => $admission_number,
                        ':first_name' => $first_name,
                        ':last_name'  => $last_name,
                        ':email'      => $email,
                        ':password'   => $hashed_password,
                        ':phone'      => $phone,
                        ':dob'        => $date_of_birth,
                        ':address'    => $address
                    ]);

                    $db->commit();
                    $success = true;

                    // =====================================================
                    // 🚀 SEND WELCOME EMAIL
                    // =====================================================
                    $emailResult = sendWelcomeEmail($email, $first_name, $last_name, $admission_number);

                    if (!empty($emailResult['success'])) {
                        $email_sent = true;
                    } else {
                        error_log('Welcome email failed for ' . $email . ': ' . ($emailResult['error'] ?? 'unknown'));
                    }

                } catch (PDOException $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('Registration error: ' . $e->getMessage());

                    if ($e->getCode() === '23000') {
                        $error = "A momentary conflict occurred. Please submit the form again.";
                    } else {
                        $error = "Registration failed! Please try again.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link rel="stylesheet" href="../assets/css/student.css">
</head>
<body>

<div class="register-page">
    <div class="container split-container">

        <!-- LEFT PANEL - BRANDING (Green Side) -->
        <div class="register-left">
            <h1>Student Registration</h1>
            <p class="subhead">
                Create your academic account to access grades, resources, and secure portals.
            </p>
            <div class="feature-grid">
                <span class="feature-item"><span class="emoji">📂</span> Secure Login</span>
                <span class="feature-item"><span class="emoji">📄</span> Admission Number</span>
                <span class="feature-item"><span class="emoji">📄</span> Official Records</span>
                <span class="feature-item"><span class="emoji">📄</span> Student Support</span>
            </div>
        </div>

        <!-- RIGHT PANEL - COMPACT REGISTRATION FORM -->
        <div class="register-card">

            <?php if ($success === true): ?>

                <!-- ORIGINAL SUCCESS VIEW -->
                <div class="success-box">
                    <div class="success-header">
                        <h3>✅ Registration Successful!</h3>
                        <p>Welcome to the Student Management System</p>
                    </div>

                    <div class="email-status <?php echo $email_sent ? 'success-email' : 'error-email'; ?>">
                        <p>
                            <?php if ($email_sent): ?>
                                ✅ <strong>Welcome email sent!</strong> Check your Gmail inbox (and spam folder).
                            <?php else: ?>
                                ⚠️ <strong>Email not sent.</strong> Please check your email address.
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="admission-box">
                        <h4>Your Admission Number is:</h4>
                        <div class="admission-number">
                            <?php echo htmlspecialchars($generated_admission); ?>
                        </div>
                        <p class="admission-description">
                            🔑 This is your unique identifier for logging in.
                        </p>
                    </div>

                    <div class="warning-box">
                        <strong>⚠️ IMPORTANT:</strong>
                        Please save this admission number immediately.
                        You will need it to access your account.
                    </div>

                    <div class="success-action">
                        <a href="index.php?admission=<?php echo urlencode($generated_admission); ?>&registered=1"
                           class="btn-login">🔑 Go to Login Page</a>
                    </div>
                </div>

            <?php else: ?>

                <!-- COMPACT FORM -->
                <div class="register-card-inner">

                    <h2 class="form-title">Create Your Account</h2>

                    <div class="register-body">
                        <div class="info-box">
                            <strong>ℹ️ Note:</strong>
                            After registration, you will receive a unique Admission Number via email.
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <!-- CSRF token -->
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">👤 First Name</label>
                                    <input type="text" id="first_name" name="first_name"
                                           placeholder="Enter your first name" required maxlength="50"
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="last_name">👤 Last Name</label>
                                    <input type="text" id="last_name" name="last_name"
                                           placeholder="Enter your last name" required maxlength="50"
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">📧 Email Address</label>
                                <input type="email" id="email" name="email"
                                       placeholder="your.email@example.com" required maxlength="100"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">📞 Phone Number</label>
                                    <input type="tel" id="phone" name="phone"
                                           placeholder="Enter your phone number" required maxlength="20"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="date_of_birth">🎂 Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" required
                                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">🏠 Complete Address</label>
                                <textarea id="address" name="address" rows="3" maxlength="500"
                                          placeholder="Enter your complete address" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">🔒 Password (min. 10 characters)</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="password" name="password"
                                               placeholder="Create a strong password" required minlength="10" maxlength="200">
                                        <span class="toggle-password" data-target="password">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">✓ Confirm Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="confirm_password" name="confirm_password"
                                               placeholder="Confirm your password" required minlength="10" maxlength="200">
                                        <span class="toggle-password" data-target="confirm_password">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="register">📝 Register Now</button>

                            <div class="links">
                                <a href="index.php">← Back to Login</a>
                            </div>
                        </form>

                    </div>
                </div>

            <?php endif; ?>

        </div>

    </div>
</div>

<script>
    document.querySelectorAll('.toggle-password').forEach(function(el) {
        el.addEventListener('click', function() {
            const target = document.getElementById(this.dataset.target);
            if (target) {
                const isPassword = target.getAttribute('type') === 'password';
                target.setAttribute('type', isPassword ? 'text' : 'password');

                // Update the eye icon
                const svg = this.querySelector('svg');
                if (isPassword) {
                    svg.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                    `;
                } else {
                    svg.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    `;
                }
            }
        });
    });
</script>

</body>
</html>
