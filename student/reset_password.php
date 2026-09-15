
<?php
// student/reset_password.php

session_start();

require_once __DIR__ . '/config/student_db.php';
require_once __DIR__ . '/../includes/PasswordReset.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
// Trim the token to remove spaces and line breaks
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$student_data = null;

// Initialize database and password reset
$database = new StudentDatabase();
$db = $database->getConnection();
$passwordReset = new PasswordReset($db);

// Verify that the token is valid and has not expired
if (!empty($token)) {
    $result = $passwordReset->validateToken($token);
    
    if ($result['valid']) {
        $valid_token = true;
        $student_data = $result['data'];
    } else {
        $error = $result['error'] ?? "Invalid or expired token.";
    }
} else {
    $error = "No reset token provided.";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    
    // Validate password
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please enter both password fields.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Re-validate token
        $result = $passwordReset->validateToken($token);
        
        if ($result['valid']) {
            $student = $result['data'];
            
            // Update the password in the database
            $updated = $passwordReset->updatePassword($student['student_id'], $new_password);
            
            if ($updated) {
                // Invalidate the reset token
                $passwordReset->invalidateToken($token);
                
                // Shortened success message for better UI
                $success = "Password reset successful!";
                $valid_token = false;
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = $result['error'] ?? "Invalid or expired token.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Student Management System</title>
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
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #1b5e20;
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.15);
        }
        .password-hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        button[type="submit"] {
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
        button[type="submit"]:hover {
            background: #2e7d32;
        }
        button[type="submit"]:active {
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
            text-align: center;
        }
        
        /* NEW STYLE FOR THE LOGIN BUTTON */
        .btn-login {
            display: inline-block;
            width: 100%;
            padding: 14px;
            background: #1b5e20;
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            transition: background 0.3s, transform 0.1s;
            margin-top: 10px;
        }
        .btn-login:hover {
            background: #2e7d32;
        }
        .btn-login:active {
            transform: scale(0.98);
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
            margin-bottom: 20px;
            font-size: 14px;
            color: #333;
            border: 1px solid #e0e0e0;
        }
        .info-box strong {
            color: #1b5e20;
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
        <h2>Reset Password</h2>
        <p class="subtitle">Create your new password</p>
        
        <?php if (!empty($error)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
            <div style="text-align: center;">
                <a href="index.php" class="btn-login">Proceed to Login</a>
            </div>
        <?php endif; ?>
        
        <?php if ($valid_token && empty($success)): ?>
            <?php if ($student_data): ?>
                <div class="info-box">
                    <strong>👤 Student:</strong> <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?><br>
                    <strong>📧 Email:</strong> <?php echo htmlspecialchars($student_data['email']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        placeholder="Enter new password"
                        required
                        minlength="8"
                    >
                    <div class="password-hint">🔑 Must be at least 8 characters long</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Confirm new password"
                        required
                    >
                </div>
                
                <button type="submit" name="reset_password">Reset Password</button>
            </form>
        <?php endif; ?>
        
        <?php if (empty($token) && empty($success)): ?>
            <div style="text-align: center; padding: 20px 0;">
                <p style="color: #666;">No reset token provided.</p>
                <div class="links" style="margin-top: 15px;">
                    <a href="forgot_password.php">← Request a new reset link</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Removed the divider and debug info section -->
        
    </div>
</body>
</html>
