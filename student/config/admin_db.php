
<?php
// student/config/admin_db.php
// NOTE: Class name is StudentAdminDatabase to avoid collision with
// admin/config/admin_db.php's AdminDatabase class.

class StudentAdminDatabase
{
    private $host     = 'localhost';
    private $dbname   = 'admin_db';
    private $username = 'root';
    private $password = '';

    private $conn;

    public function __construct()
    {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Admin DB connection failed: ' . $e->getMessage());
            die('A database error occurred. Please contact support.');
        }
    }

    public function getConnection()
    {
        return $this->conn;
    }
}
