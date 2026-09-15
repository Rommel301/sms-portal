
<?php

session_start();

require_once 'config/admin_db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$database = new AdminDatabase();
$db = $database->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = "Please enter your username and password.";

    } else {

        try {

            $query = "
                SELECT
                    admin_id,
                    username,
                    password,
                    email,
                    full_name,
                    role,
                    status
                FROM admins
                WHERE username = :username
                LIMIT 1
            ";

            $stmt = $db->prepare($query);

            $stmt->execute([
                ':username' => $username
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Username does not exist
            if (!$row) {

                $error = "Invalid username or password.";

            // Password is incorrect
            } elseif (!password_verify($password, $row['password'])) {

                $error = "Invalid username or password.";

            // Admin account is inactive
            } elseif ($row['status'] !== 'active') {

                $error = "Your admin account is inactive.";

            } else {

                // =========================================
                // LOGIN SUCCESS
                // =========================================

                session_regenerate_id(true);

                $_SESSION['admin_id']   = $row['admin_id'];
                $_SESSION['admin_name'] = $row['full_name'];
                $_SESSION['admin_role'] = $row['role'];

                // Update last login
                $update = $db->prepare("
                    UPDATE admins
                    SET last_login = NOW()
                    WHERE admin_id = :id
                ");

                $update->execute([
                    ':id' => $row['admin_id']
                ]);

                // Go to dashboard
                header("Location: dashboard.php");
                exit();
            }

        } catch (PDOException $e) {

            error_log("Admin login error: " . $e->getMessage());

            $error = "Database error. Please check your database connection.";
        }
    }
}
?>


<!DOCTYPE html> 
<html lang="en"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Admin Login - Student Management System</title> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
   <link rel="stylesheet" href="../assets/css/admin.css">
</head> 
<body class="admin-login-page"> 
    <div class="admin-login-container"> 
        <div class="admin-login-card"> 
            <div class="admin-login-header"> 
                <h2><i class="fas fa-user-shield"></i> Admin Login</h2> 
                <p>Access the administration panel</p> 
            </div> 
             
            <div class="admin-login-body"> 
                <?php if ($error): ?> 
                    <div class="admin-login-error"> 
                        <i class="fas fa-exclamation-circle"></i> 
                        <?php echo htmlspecialchars($error); ?> 
                    </div> 
                <?php endif; ?> 
                 
                <form method="POST"> 
                    <div class="admin-login-form-group"> 
                        <label><i class="fas fa-user"></i> Username</label> 
                        <input type="text" name="username" placeholder="Enter your username" required autofocus> 
                    </div> 
                     
                    <div class="admin-login-form-group"> 
                        <label><i class="fas fa-lock"></i> Password</label> 
                        <input type="password" name="password" placeholder="Enter your password" required> 
                    </div> 
                     
                    <button type="submit" name="login" class="admin-login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </button> 
                </form> 
            </div> 
        </div> 
    </div> 
</body> 
</html>
