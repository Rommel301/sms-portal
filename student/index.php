
<?php
// student/index.php

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

// ============================================================
// Prevent browser caching of login page
// This forces the back button to re-check the server,
// so logged-in users get redirected to dashboard.
// ============================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'config/student_db.php';

$error   = "";
$success = "";

if (!empty($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['error']) && $_GET['error'] === 'invalid_account') {
    $error = "Your session is no longer valid. Please log in again.";
}

if (isset($_GET['registered'])) {
    $success = "Registration successful! You can now log in.";
}

$prefillAdmission = '';
if (isset($_GET['admission'])) {
    $prefillAdmission = preg_replace('/[^0-9\-]/', '', $_GET['admission']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please refresh and try again.";
    } else {

        $admission_no = trim($_POST['admission_no'] ?? '');
        $password     = $_POST['password'] ?? '';

        if ($admission_no === '' || $password === '') {
            $error = "Please enter both your admission number and password.";
        } elseif (strlen($admission_no) > 50 || strlen($password) > 200) {
            $error = "Invalid admission number or password.";
        } else {

            $database = new StudentDatabase();
            $db = $database->getConnection();

            $rate_stmt = $db->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE admission_number = :adm
                  AND attempted_at > (NOW() - INTERVAL 15 MINUTE)
            ");
            $rate_stmt->execute([':adm' => $admission_no]);
            $failed = (int) $rate_stmt->fetchColumn();

            if ($failed >= 5) {
                $error = "Too many failed attempts. Please try again in 15 minutes.";
            } else {

                $stmt = $db->prepare("
                    SELECT student_id, password, first_name, last_name
                    FROM students
                    WHERE admission_number = :adm
                      AND status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([':adm' => $admission_no]);
                $student = $stmt->fetch();

                if ($student && password_verify($password, $student['password'])) {

                    session_regenerate_id(true);

                    $_SESSION['student_id']   = (int) $student['student_id'];
                    $_SESSION['student_name'] = trim(
                        ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')
                    ) ?: 'Student';

                    if (password_needs_rehash($student['password'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $db->prepare("UPDATE students SET password = ? WHERE student_id = ?")
                           ->execute([$newHash, $student['student_id']]);
                    }

                    $db->prepare("DELETE FROM login_attempts WHERE admission_number = ?")
                       ->execute([$admission_no]);

                    header("Location: dashboard.php");
                    exit();

                } else {
                    $db->prepare("
                        INSERT INTO login_attempts (admission_number, ip_address)
                        VALUES (?, ?)
                    ")->execute([
                        $admission_no,
                        $_SERVER['REMOTE_ADDR'] ?? 'local'
                    ]);

                    $error = "Invalid admission number or password.";
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
    <title>Student Login - Student Management System</title>
    <link rel="stylesheet" href="../assets/css/student.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-left">
                <h1>Student Portal</h1>
                <p class="subhead">Secure access to your academic dashboard, grades, and resources.</p>
                <div class="feature-grid">
                    <span class="feature-item"><span class="emoji">📚</span> Subjects</span>
                    <span class="feature-item"><span class="emoji">📊</span> Grades</span>
                    <span class="feature-item"><span class="emoji">📅</span> Schedule</span>
                    <span class="feature-item"><span class="emoji">📄</span> Documents</span>
                </div>
            </div>

            <div class="login-card">
                <div class="login-header">
                    <h2>Welcome back</h2>
                    <p>Sign in to continue your academic journey</p>
                </div>

                <div class="login-body">

                    <?php if (!empty($error)): ?>
                        <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token"
                               value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-group">
                            <label for="admission_no">Admission Number</label>
                            <input
                                type="text"
                                id="admission_no"
                                name="admission_no"
                                placeholder="e.g., 2026-0001"
                                autocomplete="username"
                                value="<?php echo htmlspecialchars($prefillAdmission ?? ($_POST['admission_no'] ?? '')); ?>"
                                required
                                autofocus
                                maxlength="50"
                            >
                            <div class="example">
                                Enter the Admission Number you received during registration
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                    maxlength="200"
                                >
                                <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="login">
                            Login to Dashboard
                        </button>
                    </form>

                    <div class="links">
                        <a href="register.php">New Student? Register Here</a>
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.getElementById(this.dataset.target);
                if (target) {
                    const isPassword = target.getAttribute('type') === 'password';
                    target.setAttribute('type', isPassword ? 'text' : 'password');

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
