
<?php
// student/forgot_password.php

session_start();

require_once __DIR__ . '/config/student_db.php';
require_once __DIR__ . '/../includes/EmailHelper.php';
require_once __DIR__ . '/../includes/PasswordReset.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$show_form = true;

// Initialize database
$database = new StudentDatabase();
$db = $database->getConnection();
$passwordReset = new PasswordReset($db);

// Cleanup expired tokens periodically
if (rand(1, 100) <= 10) {
    $passwordReset->cleanExpiredTokens();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reset'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $student = $passwordReset->findStudentByEmail($email);
        
        if (!$student) {
            // Security: Don't reveal if email exists
            $error = "If an account exists with this email, you will receive a reset link.";
            $show_form = false;
        } else {
            // Generate token
            $token = $passwordReset->generateToken();
            
            // Save token
            $result = $passwordReset->saveResetToken($student['student_id'], $email, $token);
            
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                // Send email
                $name = $student['first_name'] . ' ' . $student['last_name'];
                $email_sent = EmailHelper::sendResetEmail($email, $name, $token);
                
                if ($email_sent) {
                    $success = "Password reset instructions have been sent to your email address.";
                    $show_form = false;
                } else {
                    $error = "Failed to send reset email. Please try again later.";
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
    <title>Forgot Password - Student Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1b5e20;  /* Dark Green */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
        }
        .header-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 10px;
        }
        h2 {
            color: #1b5e20;
            text-align: center;
            margin-bottom: 5px;
            font-size: 24px;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #1b5e20;
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.15);
        }
        button {
            width: 100%;
            padding: 14px;
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
        }
        button:hover {
            background: #2e7d32;
        }
        button:active {
            transform: scale(0.98);
        }
        .error {
            background: #fde8e8;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
            font-size: 14px;
        }
        .success {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #1b5e20;
            font-size: 14px;
        }
        .links {
            margin-top: 20px;
            text-align: center;
        }
        .links a {
            color: #1b5e20;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }
        .links a:hover {
            color: #2e7d32;
            text-decoration: underline;
        }
        .info-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #e0e0e0;
        }
        .info-box p {
            font-size: 14px;
            color: #666;
        }
        .info-box .small-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .divider {
            border: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, #e0e0e0, transparent);
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-icon">🔐</div>
        <h2>Forgot Password</h2>
        <p class="subtitle">Enter your email to receive a reset link</p>
        
        <?php if (!empty($error)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
            <div class="info-box">
                <p>📨 Check your email for the reset link.</p>
                <p class="small-text">Didn't receive it? Check your spam folder.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($show_form && empty($success)): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your registered email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                        autofocus
                    >
                </div>
                <button type="submit" name="send_reset">Send Reset Link</button>
            </form>
        <?php endif; ?>
        
        <hr class="divider">
        
        <div class="links">
            <a href="index.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>
