
<?php
session_start();

// Only destroy admin session
if(isset($_SESSION['admin_id'])) {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
}

session_write_close();
header("Location: index.php");
exit();
?>
