
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/admin_db.php';

if (!isset($db) || !($db instanceof PDO)) {
    die("Database connection failed. Please check your configuration.");
}

require_once 'enrollment_data.php';

$enrollments = get_all_enrollments($db, null);
$selected_enrollment_id = $_GET['enrollment_id'] ?? ($enrollments[0]['id'] ?? null);
$documents = $selected_enrollment_id ? get_enrollment_documents($db, $selected_enrollment_id) : [];

$selected_enrollment = null;
foreach ($enrollments as $e) {
    if ($e['id'] == $selected_enrollment_id) {
        $selected_enrollment = $e;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $enrollment_id = (int) $_POST['enrollment_id'];
    $document_id   = (int) $_POST['document_id'];
    $new_status    = $_POST['new_status'];

    update_document_status($db, $document_id, $new_status);

    try {
        $doc_stmt = $db->prepare("
            SELECT document_type, student_id
            FROM enrollment_documents
            WHERE id = :id
        ");
        $doc_stmt->execute([':id' => $document_id]);
        $doc_data = $doc_stmt->fetch(PDO::FETCH_ASSOC);

        if ($doc_data) {
            $student_id = $doc_data['student_id'];
            $doc_type = ucwords(str_replace('_', ' ', $doc_data['document_type']));

            $db->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id INT NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'general',
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_student_id (student_id),
                    INDEX idx_is_read (is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            if ($new_status === 'approved') {
                $message = "✅ Your $doc_type has been APPROVED by the admin.";
            } elseif ($new_status === 'rejected') {
                $message = "❌ Your $doc_type has been REJECTED. Please upload a new version.";
            } else {
                $message = "📄 Your $doc_type status has been updated to: $new_status";
            }

            $notif_stmt = $db->prepare("
                INSERT INTO notifications (student_id, message, type, created_at, is_read)
                VALUES (:student_id, :message, 'document', NOW(), 0)
            ");
            $notif_stmt->execute([
                ':student_id' => $student_id,
                ':message' => $message
            ]);
        }
    } catch (PDOException $e) {
        error_log("Document notification error: " . $e->getMessage());
    }

    header("Location: documents.php?enrollment_id=" . $enrollment_id);
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

/* -----------------------------------------------------------
   FIXED: Files live in private_uploads/ (outside webroot).
   $file_path_base = absolute filesystem path for file_exists/filesize.
   Preview/download goes through view_document.php proxy.
----------------------------------------------------------- */
$file_path_base = dirname(__DIR__) . '/private_uploads/enrollment/';
if ($selected_enrollment) {
    $file_path_base .= $selected_enrollment['student_id'] . '/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Modal — ensure it's above everything */
        #imageModal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            overflow: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        #imageModal.active { display: block; }

        #imageModal .modal-content {
            display: block;
            margin: 40px auto 20px;
            max-width: 90vw;
            max-height: 75vh;
            border-radius: 8px;
            background: #fff;
            object-fit: contain;
        }

        #imageModal .modal-close {
            position: fixed;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 42px;
            font-weight: 700;
            cursor: pointer;
            line-height: 1;
            z-index: 100000;
            transition: color 0.2s;
        }
        #imageModal .modal-close:hover { color: #fc8181; }

        #imageModal .modal-caption {
            text-align: center;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        #imageModal .modal-actions {
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            padding-bottom: 20px;
        }

        #imageModal .btn-download,
        #imageModal .btn-newtab {
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            color: #fff;
        }
        #imageModal .btn-download { background: #255F38; }
        #imageModal .btn-download:hover { background: #1a472a; }
        #imageModal .btn-newtab { background: #4a5568; }
        #imageModal .btn-newtab:hover { background: #2d3748; }
    </style>
</head>
<body class="admin-page">

<div class="sidebar">
    <h2><i class="fas fa-tools"></i> <span>Admin Panel</span></h2>
    <div class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="students.php"><i class="fas fa-users"></i> <span>Students</span></a>
        <a href="enrollments.php"><i class="fas fa-clipboard-list"></i> <span>Enrollments</span></a>
        <a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a>
        <a href="schedule.php"><i class="fas fa-calendar-alt"></i> <span>Schedule</span></a>
        <a href="grades.php"><i class="fas fa-chart-line"></i> <span>Grades</span></a>
        <a href="documents.php" class="active"><i class="fas fa-file-alt"></i> <span>Documents</span></a>
    </div>
    <div class="logout-section">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <h1><i class="fas fa-file-alt"></i> Documents</h1>
        <span><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
    </div>

    <div class="enrollments-container">

        <?php if (empty($enrollments)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No enrollment submissions yet.</p>
            </div>
        <?php else: ?>

            <form method="GET" class="enrollment-selector-form">
                <select name="enrollment_id" onchange="this.form.submit()" class="enrollment-selector">
                    <?php foreach ($enrollments as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($e['id'] == $selected_enrollment_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['full_name'] . ' — ' . $e['program'] . ' (' . ucfirst($e['status']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_enrollment): ?>
                <div class="info-card">
                    <h3><i class="fas fa-user"></i> <?php echo htmlspecialchars($selected_enrollment['full_name']); ?></h3>
                    <p class="student-program">
                        <?php echo htmlspecialchars($selected_enrollment['program']); ?> —
                        <?php echo htmlspecialchars($selected_enrollment['year_level']); ?> ·
                        <?php echo htmlspecialchars($selected_enrollment['semester']); ?> ·
                        <?php echo htmlspecialchars($selected_enrollment['academic_year']); ?>
                        &nbsp;|&nbsp;
                        <span class="status-pill status-<?php echo htmlspecialchars($selected_enrollment['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($selected_enrollment['status'])); ?>
                        </span>
                    </p>
                    <p class="student-id">
                        <i class="fas fa-folder"></i> Student ID: <?php echo htmlspecialchars($selected_enrollment['student_id']); ?>
                    </p>
                    <div class="path-display">
                        <i class="fas fa-folder-open"></i> File path: <?php echo htmlspecialchars($file_path_base); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No documents uploaded for this enrollment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="enrollment-table">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>File</th>
                                <th>Uploaded</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $doc):
                            $full_path = $file_path_base . $doc['file_name'];
                            $file_exists = is_file($full_path);
                            $file_size = $file_exists ? filesize($full_path) : 0;

                            $doc_type_display = ucwords(str_replace('_', ' ', $doc['document_type']));

                            // URL to view through proxy
                            $view_url = 'view_document.php?id=' . (int)$doc['id'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($doc_type_display); ?></strong></td>
                                <td>
                                    <?php if ($file_exists): ?>
                                        <a href="javascript:void(0);"
                                           class="file-link"
                                           onclick="openModal('<?php echo htmlspecialchars($view_url, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($doc_type_display, ENT_QUOTES); ?>'); return false;">
                                            <i class="fas fa-eye"></i>
                                            <span class="file-name"><?php echo htmlspecialchars($doc['file_name']); ?></span>
                                            <span class="file-size">(<?php echo number_format($file_size / 1024, 1); ?> KB)</span>
                                            <span class="file-status-badge exists"><i class="fas fa-check-circle"></i> Exists</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="file-missing">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span class="file-name"><?php echo htmlspecialchars($doc['file_name']); ?></span>
                                            <span class="file-status-badge missing"><i class="fas fa-times-circle"></i> Missing</span>
                                        </span>
                                        <span class="file-path-hint">
                                            Expected at: <?php echo htmlspecialchars($full_path); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($doc['uploaded_at']); ?></td>
                                <td>
                                    <span class="status-pill status-<?php echo htmlspecialchars($doc['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($doc['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($doc['status'] !== 'approved'): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="enrollment_id" value="<?php echo (int) $selected_enrollment_id; ?>">
                                            <input type="hidden" name="document_id" value="<?php echo (int) $doc['id']; ?>">
                                            <input type="hidden" name="new_status" value="approved">
                                            <button type="submit" name="update_status" class="btn-approve" title="Approve document" <?php echo !$file_exists ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-approve" disabled title="Already approved">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($doc['status'] !== 'rejected'): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="enrollment_id" value="<?php echo (int) $selected_enrollment_id; ?>">
                                            <input type="hidden" name="document_id" value="<?php echo (int) $doc['id']; ?>">
                                            <input type="hidden" name="new_status" value="rejected">
                                            <button type="submit" name="update_status" class="btn-reject" title="Reject document">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-reject" disabled title="Already rejected">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!$file_exists): ?>
                                        <div class="file-missing-hint">
                                            <i class="fas fa-info-circle"></i> File missing - ask student to re-upload
                                        </div>
                                    <?php endif; ?>
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

<!-- ============ IMAGE / PDF MODAL ============ -->
<div id="imageModal" class="modal">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="modalImage" alt="Document preview" style="display:none;">
    <iframe id="modalPdf" style="display:none; width:90vw; height:75vh; border:none; background:#fff; border-radius:8px; margin:40px auto 20px; display:none;"></iframe>
    <div id="modalCaption" class="modal-caption"></div>
    <div class="modal-actions">
        <button class="btn-download" onclick="downloadDocument()">
            <i class="fas fa-download"></i> Download
        </button>
        <button class="btn-newtab" onclick="openInNewTab()">
            <i class="fas fa-external-link-alt"></i> Open in New Tab
        </button>
    </div>
</div>

<script>
var currentDocUrl = '';
var currentIsPdf = false;

function openModal(url, caption) {
    currentDocUrl = url;

    var modal  = document.getElementById('imageModal');
    var img    = document.getElementById('modalImage');
    var pdf    = document.getElementById('modalPdf');
    var cap    = document.getElementById('modalCaption');

    cap.textContent = caption || 'Document Preview';

    // Detect PDF by extension of the URL? We don't have filename here.
    // We'll load with an <img> first; if it errors, we'll switch to <iframe>.
    img.style.display = 'none';
    pdf.style.display = 'none';
    img.src = '';

    // Show modal first so user sees immediate feedback
    modal.classList.add('active');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Try loading as image
    var testImg = new Image();
    testImg.onload = function() {
        img.src = url;
        img.style.display = 'block';
        currentIsPdf = false;
    };
    testImg.onerror = function() {
        // Not an image — try PDF in iframe
        pdf.src = url;
        pdf.style.display = 'block';
        currentIsPdf = true;
    };
    testImg.src = url;
}

function closeModal() {
    var modal = document.getElementById('imageModal');
    var img   = document.getElementById('modalImage');
    var pdf   = document.getElementById('modalPdf');

    modal.classList.remove('active');
    modal.style.display = 'none';
    img.src = '';
    pdf.src = '';
    document.body.style.overflow = 'auto';
}

function downloadDocument() {
    if (!currentDocUrl) return;
    var url = currentDocUrl + (currentDocUrl.indexOf('?') > -1 ? '&' : '?') + 'download=1';
    window.location.href = url;
}

function openInNewTab() {
    if (currentDocUrl) window.open(currentDocUrl, '_blank');
}

// Click outside closes
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ESC closes
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>
