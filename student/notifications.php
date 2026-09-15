
<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/student_db.php';

$database = new StudentDatabase();
$db = $database->getConnection();

$student_id = $_SESSION['student_id'];

$error = null;
$notifications = [];
$unread_count = 0;

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$student = [];
$student_name = 'Student';
$student_first_name = 'Student';
$enrollment_status = 'Not Enrolled';

/*
|--------------------------------------------------------------------------
| CONNECT TO ADMIN DATABASE (For Enrollment Status & Notifications)
|--------------------------------------------------------------------------
*/

$admin_db = null;

try {
    $admin_db = new PDO(
        "mysql:host=localhost;dbname=admin_db",
        "root",
        ""
    );
    $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $admin_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin database connection error: " . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| GET STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

try {
    $describe_query = "DESCRIBE students";
    $describe_stmt = $db->prepare($describe_query);
    $describe_stmt->execute();
    $columns = $describe_stmt->fetchAll(PDO::FETCH_ASSOC);

    $primary_key = 'id';
    foreach ($columns as $column) {
        if (isset($column['Key']) && $column['Key'] === 'PRI') {
            $primary_key = $column['Field'];
            break;
        }
    }

    $student_query = "
        SELECT *
        FROM students
        WHERE `$primary_key` = :student_id
        LIMIT 1
    ";

    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':student_id', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        session_destroy();
        header("Location: index.php?error=invalid_account");
        exit();
    }
} catch (PDOException $e) {
    error_log("Student database error: " . $e->getMessage());
    $student = [];
    $error = "Unable to load your student information.";
}

/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$student_name = 'Student';
$student_first_name = 'Student';

if (!empty($_SESSION['student_name'])) {
    $student_name = $_SESSION['student_name'];
} elseif (!empty($student['name'])) {
    $student_name = $student['name'];
} elseif (!empty($student['first_name']) || !empty($student['last_name'])) {
    $first = $student['first_name'] ?? '';
    $middle = $student['middle_name'] ?? '';
    $last = $student['last_name'] ?? '';
    $student_name = trim($first . ' ' . $middle . ' ' . $last);
    if (empty($student_name)) {
        $student_name = $student['username'] ?? 'Student';
    }
} elseif (!empty($student['student_name'])) {
    $student_name = $student['student_name'];
} elseif (!empty($student['username'])) {
    $student_name = $student['username'];
}

if (!empty($student['first_name'])) {
    $student_first_name = $student['first_name'];
} elseif (!empty($_SESSION['student_name'])) {
    $name_parts = explode(' ', trim($_SESSION['student_name']));
    $student_first_name = $name_parts[0] ?? 'Student';
} else {
    $student_first_name = 'Student';
}

$_SESSION['student_name'] = $student_name;

/*
|--------------------------------------------------------------------------
| GET ENROLLMENT STATUS (For Sidebar Links)
|--------------------------------------------------------------------------
*/

if ($admin_db !== null) {
    try {
        $enrollment_query = "
            SELECT status
            FROM admin_db.enrollments
            WHERE student_id = :student_id
            ORDER BY id DESC
            LIMIT 1
        ";

        $enrollment_stmt = $admin_db->prepare($enrollment_query);
        $enrollment_stmt->bindParam(':student_id', $student_id);
        $enrollment_stmt->execute();
        $latest_enrollment = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);

        if ($latest_enrollment) {
            $raw_status = strtolower(trim($latest_enrollment['status'] ?? ''));
            if (in_array($raw_status, ['active', 'approved'], true)) {
                $enrollment_status = 'Enrolled';
            } elseif ($raw_status === 'pending') {
                $enrollment_status = 'Pending';
            } elseif ($raw_status === 'rejected') {
                $enrollment_status = 'Rejected';
            } else {
                $enrollment_status = 'Not Enrolled';
            }
        }
    } catch (PDOException $e) {
        error_log("Enrollment error: " . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| GET NOTIFICATIONS FROM DATABASE
|--------------------------------------------------------------------------
*/

if ($admin_db !== null) {
    try {
        // Get all notifications for this student
        $notif_query = "
            SELECT id, message, type, created_at, is_read
            FROM admin_db.notifications
            WHERE student_id = :student_id
            ORDER BY created_at DESC
        ";

        $notif_stmt = $admin_db->prepare($notif_query);
        $notif_stmt->bindParam(':student_id', $student_id);
        $notif_stmt->execute();
        $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get unread count
        $unread_query = "
            SELECT COUNT(*) as count
            FROM admin_db.notifications
            WHERE student_id = :student_id AND is_read = 0
        ";
        $unread_stmt = $admin_db->prepare($unread_query);
        $unread_stmt->bindParam(':student_id', $student_id);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->fetch(PDO::FETCH_ASSOC);
        $unread_count = $unread_result['count'] ?? 0;

    } catch (PDOException $e) {
        error_log("Notifications error: " . $e->getMessage());
        $notifications = [];
    }
}

$current_page = 'notifications.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Student Portal</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    
    <style>
        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #6c757d;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .notification-item.unread {
            border-left-color: #007bff;
            background: #f0f7ff;
        }
        
        .notification-item .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .notification-item .notification-title {
            font-weight: 600;
            font-size: 15px;
            color: #333;
        }
        
        .notification-item .notification-date {
            font-size: 12px;
            color: #999;
        }
        
        .notification-item .notification-text {
            color: #555;
            line-height: 1.6;
            margin: 0;
        }
        
        .notification-item .notification-type {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-right: 10px;
        }
        
        .notification-type.general {
            background: #e9ecef;
            color: #495057;
        }
        
        .notification-type.academic {
            background: #cce5ff;
            color: #004085;
        }
        
        .notification-type.enrollment {
            background: #d4edda;
            color: #155724;
        }
        
        .notification-type.document {
            background: #fff3cd;
            color: #856404;
        }
        
        .notification-type.important {
            background: #f8d7da;
            color: #721c24;
        }
        
        .notification-item .unread-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 8px;
        }
        
        .no-notifications {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .no-notifications i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .no-notifications p {
            color: #6c757d;
            font-size: 16px;
        }
        
        .notification-stats {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .notification-stats .stats-text {
            color: #495057;
            font-size: 14px;
        }
        
        .notification-stats .stats-text strong {
            color: #007bff;
        }
        
        .btn-mark-all-read {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s ease;
        }
        
        .btn-mark-all-read:hover {
            background: #0056b3;
        }
        
        .btn-mark-all-read:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <h2>
        <i class="fas fa-user-graduate"></i>
        Student Portal
    </h2>

    <div class="sidebar-nav">

        <a href="dashboard.php">
            <i class="fas fa-house"></i>
            Dashboard
        </a>

        <a href="profile.php">
            <i class="fas fa-user"></i>
            My Profile
        </a>

        <?php if ($enrollment_status === 'Enrolled'): ?>

            <a href="subjects.php">
                <i class="fas fa-book-open"></i>
                My Subjects
            </a>

            <a href="grades.php">
                <i class="fas fa-chart-column"></i>
                My Grades
            </a>

            <a href="schedule.php">
                <i class="fas fa-calendar-days"></i>
                My Schedule
            </a>

            <a href="documents.php">
                <i class="fas fa-folder-open"></i>
                My Documents
            </a>

        <?php else: ?>

            <a href="enrollment.php">
                <i class="fas fa-clipboard-list"></i>
                Enrollment
            </a>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Subjects
            </div>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Grades
            </div>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Schedule
            </div>

            <div class="sidebar-disabled" title="Available after enrollment approval">
                <i class="fas fa-lock"></i>
                My Documents
            </div>

        <?php endif; ?>

        <a href="announcements.php">
            <i class="fas fa-bullhorn"></i>
            Announcements
        </a>

        <a href="notifications.php" class="active">
            <i class="fas fa-bell"></i>
            Notifications
            <?php if ($unread_count > 0): ?>
                <span class="badge" style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 5px;">
                    <?php echo $unread_count; ?>
                </span>
            <?php endif; ?>
        </a>

    </div>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-right-from-bracket"></i>
            Logout
        </a>
    </div>

</div>

<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main">

    <div class="topbar">
        <!-- Mobile Menu Button -->
        <div class="menu-btn-container">
            <button onclick="toggleSidebar()" id="menu-toggle">
                ☰
            </button>
            
            <h1>
                <i class="fas fa-bell"></i>
                Notifications
            </h1>
        </div>

        <a href="profile.php" class="topbar-profile">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <span>
                <?php echo htmlspecialchars($student_name); ?>
            </span>
        </a>
    </div>

    <!-- ERROR MESSAGE -->
    <?php if ($error): ?>
        <div class="message warning">
            <i class="fas fa-triangle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         NOTIFICATIONS LIST
    ===================================================== -->
    
    <div class="announcements-wrapper">
        
        <!-- Notification Stats -->
        <div class="notification-stats">
            <span class="stats-text">
                <i class="fas fa-envelope"></i>
                Total: <strong><?php echo count($notifications); ?></strong> notifications
                <?php if ($unread_count > 0): ?>
                    · <span style="color: #007bff;"><?php echo $unread_count; ?> unread</span>
                <?php endif; ?>
            </span>
            <?php if (!empty($notifications)): ?>
                <button class="btn-mark-all-read" id="markAllReadBtn">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="no-notifications">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications yet.</p>
                <p style="font-size: 13px; color: #999;">You'll see notifications here when admin sends you updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <?php
                    $is_unread = $notification['is_read'] == 0;
                    $type = $notification['type'] ?? 'general';
                    
                    // Get icon based on type
                    $icon = 'fa-bell';
                    if ($type === 'academic') $icon = 'fa-graduation-cap';
                    if ($type === 'enrollment') $icon = 'fa-clipboard-list';
                    if ($type === 'document') $icon = 'fa-file-alt';
                    if ($type === 'important') $icon = 'fa-exclamation-triangle';
                    
                    // Format date
                    $created_at = new DateTime($notification['created_at']);
                    $now = new DateTime();
                    $interval = $now->diff($created_at);
                    
                    if ($interval->days > 7) {
                        $date_display = $created_at->format('M d, Y');
                    } elseif ($interval->days > 0) {
                        $date_display = $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->h > 0) {
                        $date_display = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->i > 0) {
                        $date_display = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                    } else {
                        $date_display = 'Just now';
                    }
                ?>
                <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" data-id="<?php echo $notification['id']; ?>">
                    <div class="notification-header">
                        <span class="notification-title">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <span class="notification-type <?php echo $type; ?>"><?php echo ucfirst($type); ?></span>
                            <?php echo htmlspecialchars($notification['message']); ?>
                            <?php if ($is_unread): ?>
                                <span class="unread-badge">NEW</span>
                            <?php endif; ?>
                        </span>
                        <span class="notification-date"><?php echo $date_display; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

</div>

<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    }

    function checkScreenSize() {
        const menuBtn = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (window.innerWidth <= 576) {
            if(menuBtn) menuBtn.style.display = 'block';
        } else {
            if(menuBtn) menuBtn.style.display = 'none';
            if(sidebar) sidebar.classList.remove('open');
        }
    }

    window.addEventListener('resize', checkScreenSize);
    window.addEventListener('load', checkScreenSize);

    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const menuBtn = document.getElementById('menu-toggle');
        if (window.innerWidth <= 576 && 
            sidebar && !sidebar.contains(event.target) && 
            menuBtn && !menuBtn.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    });

    // Mark All as Read functionality
    document.getElementById('markAllReadBtn')?.addEventListener('click', function() {
        if (confirm('Mark all notifications as read?')) {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            fetch('mark_all_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to update UI
                    location.reload();
                } else {
                    alert('Failed to mark all as read. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-double"></i> Mark All as Read';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-double"></i> Mark All as Read';
            });
        }
    });

    // Individual notification click to mark as read
    document.querySelectorAll('.notification-item.unread').forEach(item => {
        item.addEventListener('click', function() {
            const id = this.dataset.id;
            const badge = this.querySelector('.unread-badge');
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.remove('unread');
                    if (badge) badge.remove();
                    
                    // Update unread count in sidebar
                    const sidebarBadge = document.querySelector('.sidebar-nav .badge');
                    if (sidebarBadge) {
                        const current = parseInt(sidebarBadge.textContent);
                        if (current > 0) {
                            sidebarBadge.textContent = current - 1;
                            if (sidebarBadge.textContent == '0') {
                                sidebarBadge.remove();
                            }
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
</script>

</body>
</html>
