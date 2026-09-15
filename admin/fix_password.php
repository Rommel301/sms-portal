
<?php
require_once 'config/admin_db.php';

$database = new AdminDatabase();
$db = $database->getConnection();

$username = 'admin';
$password = 'admin123';

// Generate fresh hash
$new_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>🔐 Password Fix Tool</h2>";
echo "Username: <strong>{$username}</strong><br>";
echo "Password: <strong>{$password}</strong><br>";
echo "New Hash: <code>{$new_hash}</code><br><br>";

try {
    // Update the password
    $query = "UPDATE admins SET password = :password, status = 'active' WHERE username = :username";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        ':password' => $new_hash,
        ':username' => $username
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo "✅ <span style='color:green;font-weight:bold;'>Password updated successfully!</span><br><br>";
        
        // Verify the password works
        $check = $db->prepare("SELECT password FROM admins WHERE username = :username");
        $check->execute([':username' => $username]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $row['password'])) {
            echo "✅ <span style='color:green;font-weight:bold;'>Password verification successful!</span><br>";
            echo "You can now login with:<br>";
            echo "Username: <strong>{$username}</strong><br>";
            echo "Password: <strong>{$password}</strong><br><br>";
            echo "<a href='login.php' style='display:inline-block;padding:10px 20px;background:#4CAF50;color:white;text-decoration:none;border-radius:4px;'>🔐 Go to Login</a>";
        } else {
            echo "❌ Password verification failed!";
        }
    } else {
        echo "❌ Admin not found or no changes made.";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
