
<?php
// admin/config/admin_db.php

// Auto-load sibling StudentDatabase class so any file that includes
// admin_db.php also gets StudentDatabase available.
require_once __DIR__ . '/student_db.php';

class AdminDatabase
{
    private $host     = 'localhost';
    private $db_name  = 'admin_db';
    private $username = 'root';
    private $password = '';

    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Admin DB connection error: ' . $e->getMessage());
            die('A database error occurred. Please contact support.');
        }
        return $this->conn;
    }
}

// Create instance and get connection
$adminDb = new AdminDatabase();
$db = $adminDb->getConnection();

if (!$db) {
    die("Failed to connect to admin database.");
}
