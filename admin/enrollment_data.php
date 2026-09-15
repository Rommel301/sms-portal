
<?php
/*
|--------------------------------------------------------------------------
| ADMIN ENROLLMENT DATA
|--------------------------------------------------------------------------
| Functions used by admin/enrollments.php to list, inspect, and update 
| student enrollment submissions.
|
| Usage:
|   require_once 'config/admin_db.php';
|   require_once 'enrollment_data.php';
|
|   $enrollments = get_all_enrollments($db, 'pending');
|   $enrollment  = get_enrollment_by_id($db, $enrollment_id);
|   $documents   = get_enrollment_documents($db, $enrollment_id);
|   update_enrollment_status($db, $enrollment_id, 'active', $remarks);
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Get all enrollments, optionally filtered by status
|--------------------------------------------------------------------------
| $status = null returns everything. Pass 'pending', 'active', or
| 'rejected' to filter.
*/
function get_all_enrollments(PDO $db, $status = null)
{
    try {
        if ($status !== null) {
            $stmt = $db->prepare("
                SELECT * FROM enrollments
                WHERE status = :status
                ORDER BY submitted_at DESC
            ");
            $stmt->execute([':status' => $status]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM enrollments
                ORDER BY submitted_at DESC
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_all_enrollments error: " . $e->getMessage());
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Get a single enrollment by its id
|--------------------------------------------------------------------------
*/
function get_enrollment_by_id(PDO $db, $enrollment_id)
{
    try {
        $stmt = $db->prepare("
            SELECT * FROM enrollments
            WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $enrollment_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $enrollment ?: null;
    } catch (PDOException $e) {
        error_log("get_enrollment_by_id error: " . $e->getMessage());
        return null;
    }
}

/*
|--------------------------------------------------------------------------
| Get the uploaded documents attached to an enrollment
|--------------------------------------------------------------------------
*/
function get_enrollment_documents(PDO $db, $enrollment_id)
{
    try {
        $stmt = $db->prepare("
            SELECT * FROM enrollment_documents
            WHERE enrollment_id = :enrollment_id
            ORDER BY document_type ASC
        ");
        $stmt->execute([':enrollment_id' => $enrollment_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_enrollment_documents error: " . $e->getMessage());
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| Update a single document's status (approve / reject)
|--------------------------------------------------------------------------
*/
function update_document_status(PDO $db, $document_id, $status)
{
    $allowed_statuses = ['approved', 'rejected', 'pending'];
    if (!in_array($status, $allowed_statuses)) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            UPDATE enrollment_documents
            SET status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            ':status' => $status,
            ':id'     => $document_id
        ]);
    } catch (PDOException $e) {
        error_log("update_document_status error: " . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Update an enrollment's status (approve / reject)
|--------------------------------------------------------------------------
| $status must be 'active' or 'rejected'. $remarks is optional.
*/
function update_enrollment_status(PDO $db, $enrollment_id, $status, $remarks = null)
{
    $allowed_statuses = ['active', 'rejected'];
    if (!in_array($status, $allowed_statuses)) {
        return false;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE enrollments
            SET status = :status,
                remarks = :remarks,
                reviewed_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status'  => $status,
            ':remarks' => $remarks,
            ':id'      => $enrollment_id
        ]);

        $document_stmt = $db->prepare("
            UPDATE enrollment_documents
            SET status = :status
            WHERE enrollment_id = :enrollment_id
        ");
        $document_stmt->execute([
            ':status'        => $status === 'active' ? 'approved' : 'rejected',
            ':enrollment_id' => $enrollment_id
        ]);

        $db->commit();
        return true;

    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("update_enrollment_status error: " . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Count enrollments by status — used for dashboard badges/tiles
|--------------------------------------------------------------------------
*/
function count_enrollments_by_status(PDO $db, $status)
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) AS count
            FROM enrollments
            WHERE status = :status
        ");
        $stmt->execute([':status' => $status]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("count_enrollments_by_status error: " . $e->getMessage());
        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Get all enrolled (approved) students — for subject/grade/schedule admin
|--------------------------------------------------------------------------
| Pulls straight from the enrollments table since that's where
| full_name/program/year_level already live for active enrollments.
*/
function get_enrolled_students(PDO $db)
{
    try {
        $stmt = $db->prepare("
            SELECT student_id, full_name, program, year_level
            FROM enrollments
            WHERE status = 'active'
            ORDER BY full_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_enrolled_students error: " . $e->getMessage());
        return [];
    }
}

/*
|--------------------------------------------------------------------------
| SUBJECTS
|--------------------------------------------------------------------------
*/
function get_subjects_for_student(PDO $db, $student_id)
{
    try {
        $stmt = $db->prepare("
            SELECT * FROM student_subjects
            WHERE student_id = :student_id
            ORDER BY subject_code ASC
        ");
        $stmt->execute([':student_id' => $student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_subjects_for_student error: " . $e->getMessage());
        return [];
    }
}

function add_subject_for_student(PDO $db, $student_id, $data)
{
    try {
        $stmt = $db->prepare("
            INSERT INTO student_subjects
                (student_id, subject_code, subject_name, units, instructor, schedule)
            VALUES
                (:student_id, :subject_code, :subject_name, :units, :instructor, :schedule)
        ");
        return $stmt->execute([
            ':student_id'    => $student_id,
            ':subject_code'  => $data['subject_code'],
            ':subject_name'  => $data['subject_name'],
            ':units'         => $data['units'],
            ':instructor'    => $data['instructor'],
            ':schedule'      => $data['schedule'],
        ]);
    } catch (PDOException $e) {
        error_log("add_subject_for_student error: " . $e->getMessage());
        return false;
    }
}

function delete_subject(PDO $db, $subject_row_id)
{
    try {
        $stmt = $db->prepare("DELETE FROM student_subjects WHERE id = :id");
        return $stmt->execute([':id' => $subject_row_id]);
    } catch (PDOException $e) {
        error_log("delete_subject error: " . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| GRADES
|--------------------------------------------------------------------------
*/
function get_grades_for_student(PDO $db, $student_id)
{
    try {
        $stmt = $db->prepare("
            SELECT * FROM student_grades
            WHERE student_id = :student_id
            ORDER BY subject_code ASC
        ");
        $stmt->execute([':student_id' => $student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_grades_for_student error: " . $e->getMessage());
        return [];
    }
}

function upsert_grade_for_student(PDO $db, $student_id, $data)
{
    try {
        $stmt = $db->prepare("
            SELECT id FROM student_grades
            WHERE student_id = :student_id AND subject_code = :subject_code
            LIMIT 1
        ");
        $stmt->execute([
            ':student_id'   => $student_id,
            ':subject_code' => $data['subject_code'],
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE student_grades
                SET subject_name = :subject_name,
                    midterm_grade = :midterm_grade,
                    final_grade = :final_grade,
                    remarks = :remarks
                WHERE id = :id
            ");
            return $stmt->execute([
                ':subject_name'  => $data['subject_name'],
                ':midterm_grade' => $data['midterm_grade'],
                ':final_grade'   => $data['final_grade'],
                ':remarks'       => $data['remarks'],
                ':id'            => $existing['id'],
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO student_grades
                    (student_id, subject_code, subject_name, midterm_grade, final_grade, remarks)
                VALUES
                    (:student_id, :subject_code, :subject_name, :midterm_grade, :final_grade, :remarks)
            ");
            return $stmt->execute([
                ':student_id'    => $student_id,
                ':subject_code'  => $data['subject_code'],
                ':subject_name'  => $data['subject_name'],
                ':midterm_grade' => $data['midterm_grade'],
                ':final_grade'   => $data['final_grade'],
                ':remarks'       => $data['remarks'],
            ]);
        }
    } catch (PDOException $e) {
        error_log("upsert_grade_for_student error: " . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| SCHEDULE
|--------------------------------------------------------------------------
*/
function get_schedule_for_student(PDO $db, $student_id)
{
    try {
        $stmt = $db->prepare("
            SELECT * FROM student_schedule
            WHERE student_id = :student_id
            ORDER BY FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), time ASC
        ");
        $stmt->execute([':student_id' => $student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_schedule_for_student error: " . $e->getMessage());
        return [];
    }
}

function add_schedule_entry(PDO $db, $student_id, $data)
{
    try {
        $stmt = $db->prepare("
            INSERT INTO student_schedule
                (student_id, day, time, subject, room)
            VALUES
                (:student_id, :day, :time, :subject, :room)
        ");
        return $stmt->execute([
            ':student_id' => $student_id,
            ':day'        => $data['day'],
            ':time'       => $data['time'],
            ':subject'    => $data['subject'],
            ':room'       => $data['room'],
        ]);
    } catch (PDOException $e) {
        error_log("add_schedule_entry error: " . $e->getMessage());
        return false;
    }
}

function delete_schedule_entry(PDO $db, $entry_id)
{
    try {
        $stmt = $db->prepare("DELETE FROM student_schedule WHERE id = :id");
        return $stmt->execute([':id' => $entry_id]);
    } catch (PDOException $e) {
        error_log("delete_schedule_entry error: " . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| DOCUMENT REQUIREMENTS CHECKING
|--------------------------------------------------------------------------
*/

function get_required_documents() {
    return [
        '2x2_id_picture' => '2x2 ID Picture',
        'birth_certificate' => 'Birth Certificate',
        'valid_id' => 'Valid ID / School ID'
    ];
}

function is_enrollment_documents_complete(PDO $db, $enrollment_id) {
    $required_documents = array_keys(get_required_documents());
    $documents = get_enrollment_documents($db, $enrollment_id);
    
    $approved_docs = [];
    foreach ($documents as $doc) {
        if ($doc['status'] === 'approved') {
            $approved_docs[] = $doc['document_type'];
        }
    }
    
    // Check if all required documents are approved
    foreach ($required_documents as $required) {
        if (!in_array($required, $approved_docs)) {
            return false;
        }
    }
    
    return true;
}

function get_missing_documents(PDO $db, $enrollment_id) {
    $required_documents = get_required_documents();
    $documents = get_enrollment_documents($db, $enrollment_id);
    
    $approved_docs = [];
    foreach ($documents as $doc) {
        if ($doc['status'] === 'approved') {
            $approved_docs[] = $doc['document_type'];
        }
    }
    
    $missing = [];
    foreach ($required_documents as $key => $label) {
        if (!in_array($key, $approved_docs)) {
            $missing[$key] = $label;
        }
    }
    
    return $missing;
}

function get_approved_documents(PDO $db, $enrollment_id) {
    $documents = get_enrollment_documents($db, $enrollment_id);
    $approved = [];
    
    foreach ($documents as $doc) {
        if ($doc['status'] === 'approved') {
            $approved[] = $doc['document_type'];
        }
    }
    
    return $approved;
}
