
<?php
// admin/config/student_db.php

class StudentDatabase
{
    private $host     = 'localhost';
    private $dbname   = 'student_db';
    private $username = 'root';
    private $password = '';

    private $connection = null;

    public function getConnection()
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $this->connection;
        } catch (PDOException $e) {
            error_log("StudentDatabase connection failed: " . $e->getMessage());
            return null;
        }
    }
}
