
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/admin_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    die("Database connection failed. Please check your configuration.");
}

require_once 'enrollment_data.php';


/* =========================================================
   DELETE GRADE
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_grade'])
) {

    $student_id = (int) ($_POST['student_id'] ?? 0);
    $grade_id   = (int) ($_POST['grade_id'] ?? 0);

    if ($student_id > 0 && $grade_id > 0) {

        try {

            $stmt = $db->prepare("
                DELETE FROM student_grades
                WHERE id = :id
                AND student_id = :student_id
            ");

            $stmt->execute([
                ':id' => $grade_id,
                ':student_id' => $student_id
            ]);

        } catch (PDOException $e) {

            error_log(
                "delete_grade error: " .
                $e->getMessage()
            );
        }
    }

    header(
        "Location: grades.php?student_id=" .
        urlencode($student_id)
    );

    exit;
}


/* =========================================================
   GET ENROLLED STUDENTS
   ========================================================= */

$students = get_enrolled_students($db);

$selected_student_id =
    $_GET['student_id']
    ?? ($students[0]['student_id'] ?? null);


/* =========================================================
   GET SELECTED STUDENT GRADES
   ========================================================= */

$grades = $selected_student_id
    ? get_grades_for_student($db, $selected_student_id)
    : [];


/* =========================================================
   SAVE GRADE
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_grade'])
) {

    $student_id = (int) ($_POST['student_id'] ?? 0);

    $subject_code =
        trim($_POST['subject_code'] ?? '');

    $subject_name =
        trim($_POST['subject_name'] ?? '');

    $midterm_grade =
        trim($_POST['midterm_grade'] ?? '');

    $final_grade =
        trim($_POST['final_grade'] ?? '');

    $remarks =
        trim($_POST['remarks'] ?? '');


    if (
        $student_id > 0
        && $subject_code !== ''
        && $subject_name !== ''
    ) {

        // Auto-calculate remarks if not provided
        if ($remarks === '' && $midterm_grade !== '' && $final_grade !== '') {
            $average = ((float)$midterm_grade + (float)$final_grade) / 2;
            if ($average >= 75) {
                $remarks = 'Passed';
            } else {
                $remarks = 'Failed';
            }
        }

        // If final grade is provided but midterm is not, set remarks to INC
        if ($remarks === '' && $final_grade !== '' && $midterm_grade === '') {
            $remarks = 'INC';
        }

        upsert_grade_for_student(
            $db,
            $student_id,
            [
                'subject_code'  => $subject_code,
                'subject_name'  => $subject_name,
                'midterm_grade' =>
                    $midterm_grade !== ''
                    ? $midterm_grade
                    : null,
                'final_grade'   =>
                    $final_grade !== ''
                    ? $final_grade
                    : null,
                'remarks'       =>
                    $remarks !== ''
                    ? $remarks
                    : null,
            ]
        );
    }

    header(
        "Location: grades.php?student_id=" .
        urlencode($student_id)
    );

    exit;
}


/* =========================================================
   ADMIN NAME
   ========================================================= */

$admin_name =
    $_SESSION['admin_name'] ?? 'Admin';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Grades - Admin Portal</title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >
    <link rel="stylesheet" href="../assets/css/admin.css">

</head>


<body class="admin-page">


<!-- =========================================================
     SIDEBAR
     ========================================================= -->

<div class="sidebar">

    <h2>

        <i class="fas fa-tools"></i>

        <span>Admin Panel</span>

    </h2>


    <div class="sidebar-nav">


        <!-- DASHBOARD -->

        <a href="dashboard.php">

            <i class="fas fa-tachometer-alt"></i>

            <span>Dashboard</span>

        </a>


        <!-- STUDENTS -->

        <a href="students.php">

            <i class="fas fa-users"></i>

            <span>Students</span>

        </a>


        <!-- ENROLLMENTS -->

        <a href="enrollments.php">

            <i class="fas fa-clipboard-list"></i>

            <span>Enrollments</span>

        </a>


        <!-- SUBJECTS -->

        <a href="subjects.php">

            <i class="fas fa-book"></i>

            <span>Subjects</span>

        </a>


        <!-- SCHEDULE -->

        <a href="schedule.php">

            <i class="fas fa-calendar-alt"></i>

            <span>Schedule</span>

        </a>


        <!-- GRADES -->

        <a
            href="grades.php"
            class="active"
        >

            <i class="fas fa-chart-line"></i>

            <span>Grades</span>

        </a>


        <!-- DOCUMENTS -->

        <a href="documents.php">

            <i class="fas fa-file-alt"></i>

            <span>Documents</span>

        </a>


    </div>


    <!-- LOGOUT -->

    <div class="logout-section">

        <a
            href="logout.php"
            class="logout-btn"
        >

            <i class="fas fa-sign-out-alt"></i>

            <span>Logout</span>

        </a>

    </div>

</div>


<!-- =========================================================
     MAIN
     ========================================================= -->

<div class="main">


    <!-- =====================================================
         TOPBAR
         ===================================================== -->

    <div class="topbar">

        <h1>

            <i class="fas fa-chart-line"></i>

            Grades

        </h1>


        <span>

            <i class="fas fa-user-circle"></i>

            Welcome,

            <?php
            echo htmlspecialchars($admin_name);
            ?>

        </span>

    </div>


    <!-- =====================================================
         CONTENT
         ===================================================== -->

    <div class="enrollments-container">


        <?php if (empty($students)): ?>


            <!-- =================================================
                 NO ENROLLED STUDENTS
                 ================================================= -->

            <div class="empty-state">

                <i class="fas fa-folder-open"></i>

                <p>
                    No enrolled students yet.
                </p>

            </div>


        <?php else: ?>


            <!-- =================================================
                 STUDENT SELECTOR
                 ================================================= -->

            <form
                method="GET"
                class="student-select-form"
            >

                <label
                    for="student_id"
                    class="student-label"
                >

                    <i class="fas fa-user-graduate"></i>

                    Select Student

                </label>


                <select
                    name="student_id"
                    id="student_id"
                    class="student-select"
                    onchange="this.form.submit()"
                >


                    <?php foreach ($students as $s): ?>


                        <?php

                        $last_name =
                            $s['last_name'] ?? '';

                        $first_name =
                            $s['first_name'] ?? '';

                        $program =
                            $s['program'] ?? '';

                        $year_level =
                            $s['year_level'] ?? '';

                        $full_name =
                            $s['full_name'] ?? '';


                        /*
                         * If first and last name are empty,
                         * get full_name from active enrollment.
                         */

                        if (
                            empty($last_name)
                            && empty($first_name)
                            && !empty($full_name)
                        ) {

                            $display_name =
                                $full_name
                                . ' — '
                                . $program
                                . ' ('
                                . $year_level
                                . ')';

                        } elseif (
                            !empty($last_name)
                            || !empty($first_name)
                        ) {

                            $display_name =
                                trim($last_name . ', ' . $first_name)
                                . ' — '
                                . $program
                                . ' ('
                                . $year_level
                                . ')';

                        } else {

                            $display_name =
                                'Student #'
                                . $s['student_id']
                                . ' — '
                                . $program
                                . ' ('
                                . $year_level
                                . ')';
                        }

                        ?>


                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    $s['student_id']
                                );
                            ?>"
                            <?php
                            echo (
                                $s['student_id']
                                == $selected_student_id
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $display_name
                            );
                            ?>

                        </option>


                    <?php endforeach; ?>


                </select>

            </form>


            <!-- =================================================
                 ADD GRADE FORM
                 ================================================= -->

            <form
                method="POST"
                class="grades-form"
            >


                <!-- STUDENT ID -->

                <input
                    type="hidden"
                    name="student_id"
                    value="<?php
                        echo htmlspecialchars(
                            $selected_student_id
                        );
                    ?>"
                >


                <!-- =================================================
                     SUBJECT CODE
                     ================================================= -->

                <div class="grade-field">

                    <label for="subject_code">

                        Subject Code

                    </label>


                    <select
                        name="subject_code"
                        id="subject_code"
                        required
                    >

                        <option value="">

                            Select Subject Code

                        </option>


                        <option
                            value="CC101"
                        >

                            CC101

                        </option>


                        <option
                            value="CC102"
                        >

                            CC102

                        </option>


                        <option
                            value="CC103"
                        >

                            CC103

                        </option>


                        <option
                            value="CC104"
                        >

                            CC104

                        </option>


                        <option
                            value="CC105"
                        >

                            CC105

                        </option>


                    </select>

                </div>


                <!-- =================================================
                     SUBJECT NAME
                     ================================================= -->

                <div class="grade-field">

                    <label for="subject_name">

                        Subject Name

                    </label>


                    <select
                        name="subject_name"
                        id="subject_name"
                        required
                    >

                        <option value="">

                            Select Subject Name

                        </option>


                        <option
                            value="Introduction to Computing"
                        >

                            Introduction to Computing

                        </option>


                        <option
                            value="Computer Programming"
                        >

                            Computer Programming

                        </option>


                        <option
                            value="Database Management"
                        >

                            Database Management

                        </option>


                        <option
                            value="Web Development"
                        >

                            Web Development

                        </option>


                        <option
                            value="Information Management"
                        >

                            Information Management

                        </option>


                    </select>

                </div>


                <!-- =================================================
                     MIDTERM GRADE
                     ================================================= -->

                <div class="grade-field grade-midterm">

                    <label for="midterm_grade">

                        Midterm

                    </label>


                    <input
                        type="number"
                        name="midterm_grade"
                        id="midterm_grade"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="e.g. 85"
                        value=""
                    >

                </div>


                <!-- =================================================
                     FINAL GRADE
                     ================================================= -->

                <div class="grade-field grade-final">

                    <label for="final_grade">

                        Final

                    </label>


                    <input
                        type="number"
                        name="final_grade"
                        id="final_grade"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="e.g. 90"
                        value=""
                    >

                </div>


                <!-- =================================================
                     REMARKS
                     ================================================= -->

                <div class="grade-field">

                    <label for="remarks">

                        Remarks

                    </label>


                    <select
                        name="remarks"
                        id="remarks"
                    >

                        <option value="">

                            Auto-calculate

                        </option>


                        <option
                            value="Passed"
                        >

                            Passed

                        </option>


                        <option
                            value="Failed"
                        >

                            Failed

                        </option>


                        <option
                            value="INC"
                        >

                            INC

                        </option>


                    </select>

                </div>


                <!-- =================================================
                     SAVE BUTTON
                     ================================================= -->

                <button
                    type="submit"
                    name="save_grade"
                    class="grade-save-btn"
                >

                    <i class="fas fa-save"></i>

                    Save Grade

                </button>


            </form>


            <!-- =================================================
                 TIP
                 ================================================= -->

            <p class="grades-tip">

                <i class="fas fa-info-circle"></i>

                Enter both Midterm and Final grades to calculate the average. 
                Remarks will be auto-calculated if left as "Auto-calculate".

            </p>


            <!-- =================================================
                 GRADES TABLE
                 ================================================= -->

            <?php if (empty($grades)): ?>


                <div class="empty-state">

                    <i class="fas fa-chart-line"></i>

                    <p>

                        No grades posted for this student yet.

                    </p>

                </div>


            <?php else: ?>


                <div class="grades-table-wrapper">

                    <table class="enrollment-table">


                        <thead>

                            <tr>

                                <th>
                                    Code
                                </th>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Midterm
                                </th>

                                <th>
                                    Final
                                </th>

                                <th>
                                    Average
                                </th>

                                <th>
                                    Remarks
                                </th>

                                <th class="action-column">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($grades as $g): ?>


                            <?php
                            
                            // Calculate average
                            $midterm = $g['midterm_grade'] ?? null;
                            $final = $g['final_grade'] ?? null;
                            
                            if (is_numeric($midterm) && is_numeric($final)) {
                                $average = round(((float)$midterm + (float)$final) / 2, 1);
                                $average_display = $average;
                            } else {
                                $average_display = '—';
                            }
                            
                            ?>


                            <tr>


                                <!-- SUBJECT CODE -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $g['subject_code']
                                        ?? ''
                                    );
                                    ?>

                                </td>


                                <!-- SUBJECT NAME -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $g['subject_name']
                                        ?? ''
                                    );
                                    ?>

                                </td>


                                <!-- MIDTERM -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $g['midterm_grade']
                                        ?? '—'
                                    );
                                    ?>

                                </td>


                                <!-- FINAL -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $g['final_grade']
                                        ?? '—'
                                    );
                                    ?>

                                </td>


                                <!-- AVERAGE -->

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            (string)$average_display
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- REMARKS -->
                                    <td>
                                        <?php
                                        $remarks = $g['remarks'] ?? '—';
                                        if ($remarks === 'Passed') {
                                            echo '<span class="remarks-passed"><i class="fas fa-check-circle"></i> <span>Passed</span></span>';
                                        } elseif ($remarks === 'Failed') {
                                            echo '<span class="remarks-failed"><i class="fas fa-times-circle"></i> <span>Failed</span></span>';
                                        } elseif ($remarks === 'INC') {
                                            echo '<span class="remarks-inc"><i class="fas fa-clock"></i> <span>INC</span></span>';
                                        } else {
                                            echo htmlspecialchars($remarks);
                                        }
                                        ?>
                                    </td>
                                <!-- ACTION -->
                                    <td class="grade-actions">
                                        <!-- REMOVE BUTTON -->
                                        <form
                                            method="POST"
                                            class="delete-grade-form"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to remove this grade?'
                                                );
                                            "
                                        >
                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value="<?php
                                                    echo htmlspecialchars(
                                                        $selected_student_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="grade_id"
                                                value="<?php
                                                    echo htmlspecialchars(
                                                        $g['id']
                                                    );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_grade"
                                                class="grade-action-delete"
                                                title="Remove Grade"
                                            >
                                                <i class="fas fa-trash"></i>
                                                <span>Remove</span>
                                            </button>
                                        </form>
                                    </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>

                </div>


            <?php endif; ?>


        <?php endif; ?>


    </div>

</div>


</body>

</html>
