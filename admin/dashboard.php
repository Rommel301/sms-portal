
<?php

// ============================================================
// FIX: Set session cookie parameters BEFORE session_start()
// ============================================================
session_set_cookie_params([
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict'
]);



// ============================================================
// ADMIN SESSION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/admin_db.php';
require_once 'config/student_db.php';

$database = new AdminDatabase();
$db = $database->getConnection();

$error = '';
$warning = '';


// ============================================================
// CONNECT TO STUDENT DATABASE
// ============================================================

$studentDatabase = new StudentDatabase();
$student_db = $studentDatabase->getConnection();

if ($student_db) {

    try {

        // Total students
        $student_count = $student_db
            ->query("SELECT COUNT(*) FROM students")
            ->fetchColumn();


        // Active students
        $active_students = $student_db
            ->query("
                SELECT COUNT(*)
                FROM students
                WHERE status = 'active'
            ")
            ->fetchColumn();

    } catch (PDOException $e) {

        error_log("Student DB query failed: " . $e->getMessage());

        $student_count = 0;
        $active_students = 0;

        $warning = "Student database connection issue. Some stats may be unavailable.";

    }

} else {

    $student_count = 0;
    $active_students = 0;

    $warning = "Student database connection issue. Some stats may be unavailable.";

}


// ============================================================
// ENROLLMENT STATISTICS & ANALYTICS
// ============================================================

$total_enrollments = 0;
$pending_enrollments = 0;
$total_subjects = 0;
$subject_averages = [];
$leaderboard = [];

if (!$db) {
    error_log("Admin DB connection failed in dashboard.php");
    $error = "Unable to load dashboard database connection.";
} else {

    // 1. Total Active Enrollments
    try {
        $total_enrollments = (int) $db
            ->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")
            ->fetchColumn();
    } catch (PDOException $e) {
        error_log("Active enrollments query failed: " . $e->getMessage());
        $total_enrollments = 0;
    }

    // 2. Pending Enrollments
    try {
        $pending_enrollments = (int) $db
            ->query("SELECT COUNT(*) FROM enrollments WHERE status = 'pending'")
            ->fetchColumn();
    } catch (PDOException $e) {
        error_log("Pending enrollments query failed: " . $e->getMessage());
        $pending_enrollments = 0;
    }

    // 3. Total Subjects (Check student_subjects or courses)
    try {
        $total_subjects = (int) $db
            ->query("SELECT COUNT(DISTINCT subject_code) FROM student_subjects")
            ->fetchColumn();
    } catch (PDOException $e) {
        try {
            $total_subjects = (int) $db
                ->query("SELECT COUNT(*) FROM courses")
                ->fetchColumn();
        } catch (PDOException $e2) {
            $total_subjects = 0;
        }
    }

    // 4. Average Grade by Subject (Bar Graph Data)
    try {
        $subject_avg_stmt = $db->query("
            SELECT subject_code, subject_name,
                   ROUND(AVG(
                       CASE 
                           WHEN midterm_grade IS NOT NULL AND midterm_grade != '' AND final_grade IS NOT NULL AND final_grade != '' 
                               THEN (CAST(midterm_grade AS DECIMAL(5,2)) + CAST(final_grade AS DECIMAL(5,2))) / 2.0
                           WHEN midterm_grade IS NOT NULL AND midterm_grade != '' 
                               THEN CAST(midterm_grade AS DECIMAL(5,2))
                           WHEN final_grade IS NOT NULL AND final_grade != '' 
                               THEN CAST(final_grade AS DECIMAL(5,2))
                           ELSE NULL
                       END
                   ), 2) AS avg_grade
            FROM student_grades
            GROUP BY subject_code, subject_name
            HAVING avg_grade IS NOT NULL
            ORDER BY avg_grade DESC
        ");
        $subject_averages = $subject_avg_stmt ? $subject_avg_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("Subject averages query failed: " . $e->getMessage());
        $subject_averages = [];
    }

    // 5. Student Leaderboard (Sorted by Average Grade)
    try {
        $leaderboard_stmt = $db->query("
            SELECT sg.student_id, 
                   COALESCE(e.full_name, CONCAT('Student #', sg.student_id)) AS student_name,
                   e.program,
                   ROUND(AVG(
                       CASE 
                           WHEN sg.midterm_grade IS NOT NULL AND sg.midterm_grade != '' AND sg.final_grade IS NOT NULL AND sg.final_grade != '' 
                               THEN (CAST(sg.midterm_grade AS DECIMAL(5,2)) + CAST(sg.final_grade AS DECIMAL(5,2))) / 2.0
                           WHEN sg.midterm_grade IS NOT NULL AND sg.midterm_grade != '' 
                               THEN CAST(sg.midterm_grade AS DECIMAL(5,2))
                           WHEN sg.final_grade IS NOT NULL AND sg.final_grade != '' 
                               THEN CAST(sg.final_grade AS DECIMAL(5,2))
                           ELSE NULL
                       END
                   ), 2) AS overall_avg
            FROM student_grades sg
            LEFT JOIN enrollments e ON sg.student_id = e.student_id
            GROUP BY sg.student_id, student_name, e.program
            HAVING overall_avg IS NOT NULL
            ORDER BY overall_avg DESC
            LIMIT 10
        ");
        $leaderboard = $leaderboard_stmt ? $leaderboard_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("Leaderboard query failed: " . $e->getMessage());
        $leaderboard = [];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="dashboard-page">

<!-- =========================================================
     SIDEBAR - GREEN THEME
========================================================= -->

<div class="sidebar">

    <h2>
        <i class="fas fa-tools"></i>
        <span>Admin Panel</span>
    </h2>

    <div class="sidebar-nav">

        <a href="dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>

        <a href="students.php">
            <i class="fas fa-users"></i>
            <span>Students</span>
        </a>

        <a href="enrollments.php">
            <i class="fas fa-clipboard-list"></i>
            <span>Enrollments</span>
        </a>

        <a href="subjects.php">
            <i class="fas fa-book"></i>
            <span>Subjects</span>
        </a>

        <a href="schedule.php">
            <i class="fas fa-calendar-alt"></i>
            <span>Schedule</span>
        </a>

        <a href="grades.php">
            <i class="fas fa-chart-line"></i>
            <span>Grades</span>
        </a>

        <a href="documents.php">
            <i class="fas fa-file-alt"></i>
            <span>Documents</span>
        </a>

    </div>

    <!-- LOGOUT - BLACK & WHITE -->
    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</div>

<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h1>
            <i class="fas fa-chart-pie"></i>
            Dashboard Overview
        </h1>

        <span>
            <i class="fas fa-user-circle"></i>
            Welcome,
            <?php
            echo htmlspecialchars(
                $_SESSION['admin_name'] ?? 'Admin'
            );
            ?>
        </span>
    </div>

    <!-- =====================================================
         ERROR MESSAGE
    ====================================================== -->

    <?php if (!empty($error)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         WARNING MESSAGE
    ====================================================== -->

    <?php if (!empty($warning)): ?>
        <div class="message warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($warning); ?>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         STATISTICS CARDS
    ====================================================== -->

    <div class="stats-grid">

        <!-- TOTAL STUDENTS -->
        <div class="stat-card blue">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>
                        <i class="fas fa-user-graduate"></i>
                        Total Students
                    </h3>
                    <div class="stat-number">
                        <?php echo number_format($student_count); ?>
                        <?php if ($student_count > 0): ?>
                            <span class="trend up">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i>
                        <?php echo number_format($active_students); ?> active
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="students.php">
                    <i class="fas fa-graduation-cap"></i>
                    View all students
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
            </div>
        </div>

        <!-- ACTIVE ENROLLMENTS -->
        <div class="stat-card green">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>
                        <i class="fas fa-clipboard-check"></i>
                        Active Enrollments
                    </h3>
                    <div class="stat-number">
                        <?php echo number_format($total_enrollments); ?>
                        <?php if ($total_enrollments > 0): ?>
                            <span class="trend up">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i>
                        Approved enrollments
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="enrollments.php?filter=active">
                    <i class="fas fa-chalkboard"></i>
                    View enrolled students
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
            </div>
        </div>

        <!-- PENDING ENROLLMENTS -->
        <div class="stat-card orange">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>
                        <i class="fas fa-hourglass-half"></i>
                        Pending Enrollments
                    </h3>
                    <div class="stat-number">
                        <?php echo number_format($pending_enrollments); ?>
                        <?php if ($pending_enrollments > 0): ?>
                            <span class="trend up">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">
                        <i class="fas fa-clock"></i>
                        Awaiting review
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="enrollments.php?filter=pending">
                    <i class="fas fa-arrow-right"></i>
                    Review submissions
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
            </div>
        </div>

        <!-- TOTAL SUBJECTS -->
        <div class="stat-card purple">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>
                        <i class="fas fa-book"></i>
                        Total Subjects
                    </h3>
                    <div class="stat-number">
                        <?php echo number_format($total_subjects); ?>
                        <?php if ($total_subjects > 0): ?>
                            <span class="trend up">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">
                        <i class="fas fa-layer-group"></i>
                        Offered this term
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="subjects.php">
                    <i class="fas fa-plus"></i>
                    Manage subjects
                    <i class="fas fa-chevron-right arrow"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- =====================================================
         ANALYTICS & LEADERBOARD SECTION
    ====================================================== -->

    <div class="analytics-grid">

        <!-- BAR GRAPH CARD: AVERAGE GRADE BY SUBJECT -->
        <div class="analytics-card green-theme">
            <div class="analytics-card-header">
                <h2>
                    <i class="fas fa-chart-bar"></i>
                    Average Grade by Subject
                </h2>
                <span class="analytics-badge">Performance Breakdown</span>
            </div>
            <div class="analytics-card-body">
                <?php if (empty($subject_averages)): ?>
                    <div class="empty-analytics">
                        <i class="fas fa-chart-bar empty-icon"></i>
                        <p class="empty-title">No Subject Grade Data Available</p>
                        <p class="empty-desc">Enter midterm & final grades for enrolled students to view subject performance analytics.</p>
                        <a href="grades.php" class="empty-action-btn">
                            <i class="fas fa-plus-circle"></i> Add Student Grades
                        </a>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="subjectBarChart"></canvas>
                    </div>

                    <!-- DETAILED SUBJECT BAR LIST -->
                    <div class="subject-bar-list">
                        <?php foreach ($subject_averages as $subj): ?>
                            <?php $perc = min(100, max(0, floatval($subj['avg_grade']))); ?>
                            <div class="subject-bar-item">
                                <div class="subject-bar-info">
                                    <span class="subject-code"><?php echo htmlspecialchars($subj['subject_code']); ?></span>
                                    <span class="subject-title"><?php echo htmlspecialchars($subj['subject_name']); ?></span>
                                    <span class="subject-score"><?php echo number_format($subj['avg_grade'], 2); ?>%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo $perc; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- LEADERBOARD CARD: TOP STUDENTS -->
        <div class="analytics-card gold-theme">
            <div class="analytics-card-header">
                <h2>
                    <i class="fas fa-trophy"></i>
                    Student Leaderboard
                </h2>
                <span class="analytics-badge gold">Top Performers</span>
            </div>
            <div class="analytics-card-body">
                <?php if (empty($leaderboard)): ?>
                    <div class="empty-analytics">
                        <i class="fas fa-award empty-icon"></i>
                        <p class="empty-title">No Leaderboard Ranking Yet</p>
                        <p class="empty-desc">Once student grades are recorded, top performing students will appear on this leaderboard.</p>
                        <a href="grades.php" class="empty-action-btn gold">
                            <i class="fas fa-user-edit"></i> Manage Grades
                        </a>
                    </div>
                <?php else: ?>
                    <div class="leaderboard-list">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student</th>
                                    <th>Program</th>
                                    <th>Average Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($leaderboard as $student): 
                                    $rank_class = '';
                                    $badge_icon = '';
                                    if ($rank === 1) {
                                        $rank_class = 'rank-1';
                                        $badge_icon = '🥇';
                                    } elseif ($rank === 2) {
                                        $rank_class = 'rank-2';
                                        $badge_icon = '🥈';
                                    } elseif ($rank === 3) {
                                        $rank_class = 'rank-3';
                                        $badge_icon = '🥉';
                                    } else {
                                        $badge_icon = '#' . $rank;
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <span class="rank-pill <?php echo $rank_class; ?>">
                                                <?php echo $badge_icon; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="leaderboard-student">
                                                <i class="fas fa-user-circle student-avatar"></i>
                                                <div class="student-details">
                                                    <span class="name"><?php echo htmlspecialchars($student['student_name']); ?></span>
                                                    <span class="id">ID: <?php echo htmlspecialchars($student['student_id']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="program-tag"><?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="score-badge">
                                                <?php echo number_format($student['overall_avg'], 2); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- =========================================================
     AUTO HIDE MESSAGES & CHART.JS INITIALIZATION
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    setTimeout(function () {
        var messages = document.querySelectorAll('.message');
        messages.forEach(function (message) {
            if (message) {
                message.style.display = 'none';
            }
        });
    }, 5000);

    <?php if (!empty($subject_averages)): ?>
    const subjectLabels = <?php echo json_encode(array_column($subject_averages, 'subject_code')); ?>;
    const subjectFullNames = <?php echo json_encode(array_column($subject_averages, 'subject_name')); ?>;
    const subjectScores = <?php echo json_encode(array_map('floatval', array_column($subject_averages, 'avg_grade'))); ?>;

    const ctx = document.getElementById('subjectBarChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Average Grade (%)',
                    data: subjectScores,
                    backgroundColor: [
                        'rgba(37, 95, 56, 0.85)',
                        'rgba(74, 144, 217, 0.85)',
                        'rgba(243, 156, 18, 0.85)',
                        'rgba(142, 68, 173, 0.85)',
                        'rgba(39, 174, 96, 0.85)'
                    ],
                    borderColor: [
                        '#255F38',
                        '#4A90D9',
                        '#F39C12',
                        '#8E44AD',
                        '#27AE60'
                    ],
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return subjectLabels[index] + ' - ' + subjectFullNames[index];
                            },
                            label: function(context) {
                                return ' Average Grade: ' + context.raw + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            callback: function(val) { return val + '%'; }
                        },
                        grid: { color: '#e2e8f0' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
    <?php endif; ?>
</script>

</body>
</html>
