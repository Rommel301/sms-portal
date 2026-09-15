
<?php
/*
|--------------------------------------------------------------------------
| STUDENT ENROLLMENT DATA
|--------------------------------------------------------------------------
*/

function get_enrollment_data(PDO $db, $student_id) {

    $result = [
        'student'            => [],
        'profile_percentage' => 0,
        'profile_complete'   => false,
        'enrollment_status'  => 'Not Enrolled',
        'course_count'       => 0,
        'error'              => null,
    ];

    /* ---- Admin DB connection ---- */
    try {
        $admin_db = new PDO(
            "mysql:host=localhost;dbname=admin_db",
            "root",
            ""
        );
        $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("Admin DB connection failed: " . $e->getMessage());
        $admin_db = null;
        $result['error'] = "Some dashboard features are currently unavailable.";
    }

    /* ---- Find primary key of students table ---- */
    $primary_key = 'student_id';
    try {
        $column_stmt = $db->prepare("DESCRIBE students");
        $column_stmt->execute();
        foreach ($column_stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ($column['Key'] === 'PRI') {
                $primary_key = $column['Field'];
                break;
            }
        }
    } catch (PDOException $e) {
        error_log("Error describing students table: " . $e->getMessage());
    }

    /* ---- Fetch student from student_db ---- */
    try {
        $student_stmt = $db->prepare("
            SELECT * FROM students
            WHERE $primary_key = :student_id
            LIMIT 1
        ");
        $student_stmt->bindParam(':student_id', $student_id);
        $student_stmt->execute();
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            error_log("Student ID {$student_id} not found.");
            session_destroy();
            header("Location: index.php?error=invalid_account");
            exit();
        }

        $result['student'] = $student;

    } catch (PDOException $e) {
        error_log("Error fetching student data: " . $e->getMessage());
        $result['error'] = "Unable to load your student information.";
        return $result;
    }

    /* ---- Profile completeness - FIXED: Added all fields including date_of_birth and gender ---- */
    $phone_value = $student['phone'] ?? $student['contact_number'] ?? $student['contact_no'] ?? '';
    $profile_fields = [
        'first_name' => $student['first_name'] ?? '',
        'last_name'  => $student['last_name'] ?? '',
        'email'      => $student['email'] ?? '',
        'phone'      => $phone_value,
        'address'    => $student['address'] ?? '',
        'date_of_birth' => $student['date_of_birth'] ?? '', // ADDED BACK
        'gender' => $student['gender'] ?? '', // ADDED
        'program' => $student['program'] ?? '', // ADDED
        'year_level' => $student['year_level'] ?? '', // ADDED
    ];

    $completed = 0;
    $total = count($profile_fields);
    foreach ($profile_fields as $field_key => $field_val) {
        if (!empty($field_val) && trim((string)$field_val) !== '') {
            $completed++;
        }
    }

    $result['profile_percentage'] = $total > 0 ? round(($completed / $total) * 100) : 0;
    $result['profile_complete']   = $result['profile_percentage'] >= 100;

    /* ---- Enrollment status from admin_db ---- */
    if ($admin_db) {
        try {
            // Check for active enrollment
            $active_stmt = $admin_db->prepare("
                SELECT COUNT(*) AS count FROM enrollments
                WHERE student_id = :student_id AND status = 'active'
            ");
            $active_stmt->bindParam(':student_id', $student_id);
            $active_stmt->execute();
            $course_count = (int) $active_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Check for pending enrollment
            $pending_stmt = $admin_db->prepare("
                SELECT COUNT(*) AS count FROM enrollments
                WHERE student_id = :student_id AND status = 'pending'
            ");
            $pending_stmt->bindParam(':student_id', $student_id);
            $pending_stmt->execute();
            $pending_count = (int) $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Check for rejected enrollment
            $rejected_stmt = $admin_db->prepare("
                SELECT COUNT(*) AS count FROM enrollments
                WHERE student_id = :student_id AND status = 'rejected'
            ");
            $rejected_stmt->bindParam(':student_id', $student_id);
            $rejected_stmt->execute();
            $rejected_count = (int) $rejected_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $result['course_count'] = $course_count;

            if ($course_count > 0) {
                $result['enrollment_status'] = 'Enrolled';
            } elseif ($pending_count > 0) {
                $result['enrollment_status'] = 'Pending';
            } elseif ($rejected_count > 0) {
                $result['enrollment_status'] = 'Rejected';
            } else {
                $result['enrollment_status'] = 'Not Enrolled';
            }

        } catch (PDOException $e) {
            error_log("Enrollment error: " . $e->getMessage());
        }
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Next-step content, derived from the same status — used on dashboard.php
|--------------------------------------------------------------------------
*/
function get_next_action($profile_complete, $enrollment_status) {

    if (!$profile_complete) {
        return [
            'title'  => 'Complete Your Profile',
            'text'   => 'Please complete your student information before enrollment.',
            'link'   => 'profile.php',
            'button' => 'Complete Profile',
        ];
    }

    if ($enrollment_status === 'Not Enrolled') {
        return [
            'title'  => 'Start Your Enrollment',
            'text'   => 'Your profile is complete. You can now proceed with enrollment.',
            'link'   => 'enrollment.php',
            'button' => 'Start Enrollment',
        ];
    }

    if ($enrollment_status === 'Pending') {
        return [
            'title'  => 'Enrollment Pending',
            'text'   => 'Your enrollment is waiting for administrator approval.',
            'link'   => 'enrollment.php',
            'button' => 'View Enrollment',
        ];
    }

    if ($enrollment_status === 'Rejected') {
        return [
            'title'  => 'Enrollment Rejected',
            'text'   => 'Your enrollment was rejected. Please contact the administrator.',
            'link'   => 'enrollment.php',
            'button' => 'View Details',
        ];
    }

    return [
        'title'  => "You're Enrolled!",
        'text'   => 'Your enrollment is active. You can now view your subjects and schedule.',
        'link'   => 'subjects.php',
        'button' => 'View My Subjects',
    ];
}
?>
