
<?php
// includes/PasswordReset.php

class PasswordReset {
    private $db;
    private $table = 'password_resets';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Find student by email
     */
    public function findStudentByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT student_id, first_name, last_name, email, admission_number 
            FROM students 
            WHERE email = :email AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Generate a secure reset token
     */
    public function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Save reset token to database
     */
    public function saveResetToken($student_id, $email, $token) {
        // Clean up expired tokens
        $this->cleanExpiredTokens();
        
        // Check rate limiting
        if (!$this->checkRateLimit($student_id, 3, 3600)) {
            return ['error' => 'Too many reset attempts. Please try again later.'];
        }
        
        // Use MySQL NOW() + INTERVAL 1 HOUR para tama ang timezone
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} 
            (student_id, email, token, expires_at) 
            VALUES (:student_id, :email, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ");
        
        $result = $stmt->execute([
            ':student_id' => $student_id,
            ':email' => $email,
            ':token' => trim($token)  // I-TRIM ANG TOKEN!
        ]);
        
        if ($result) {
            return ['success' => true, 'token' => $token];
        }
        
        return ['error' => 'Failed to save reset token.'];
    }
    
    /**
     * Validate reset token - FIXED with trim
     */
    public function validateToken($token) {
        // I-TRIM ANG TOKEN PARA MAWALA ANG SPACES AT LINE BREAKS!
        $token = trim($token);
        
        error_log("Validating token (trimmed): " . $token);
        
        $stmt = $this->db->prepare("
            SELECT pr.*, s.first_name, s.last_name, s.email, s.admission_number 
            FROM {$this->table} pr
            INNER JOIN students s ON pr.student_id = s.student_id
            WHERE pr.token = :token 
              AND pr.expires_at > NOW() 
              AND pr.used = 0
              AND s.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch();
        
        error_log("Validate result: " . ($result ? 'FOUND' : 'NOT FOUND'));
        
        if ($result) {
            return ['valid' => true, 'data' => $result];
        }
        
        return ['valid' => false, 'error' => 'Invalid or expired token.'];
    }
    
    /**
     * Update student password
     */
    public function updatePassword($student_id, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE students 
            SET password = :password 
            WHERE student_id = :student_id
        ");
        
        return $stmt->execute([
            ':password' => $hashed_password,
            ':student_id' => $student_id
        ]);
    }
    
    /**
     * Invalidate token after use
     */
    public function invalidateToken($token) {
        $token = trim($token);  // I-TRIM ANG TOKEN!
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET used = 1 
            WHERE token = :token
        ");
        return $stmt->execute([':token' => $token]);
    }
    
    /**
     * Clean expired tokens
     */
    public function cleanExpiredTokens() {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table} 
            WHERE expires_at < NOW() OR used = 1
        ");
        return $stmt->execute();
    }
    
    /**
     * Check rate limiting
     */
    public function checkRateLimit($student_id, $max_requests = 3, $time_window = 3600) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM {$this->table} 
            WHERE student_id = :student_id 
              AND created_at > DATE_SUB(NOW(), INTERVAL :time_window SECOND)
        ");
        
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':time_window', $time_window, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['count'] < $max_requests;
    }
}
?>
