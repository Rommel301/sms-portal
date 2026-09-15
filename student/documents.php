
<?php
// student/documents.php

session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/config/student_db.php';
require_once __DIR__ . '/../includes/enrollment_data.php';

$database = new StudentDatabase();
$db = $database->getConnection();

$student_id = $_SESSION['student_id'];

$enrollment_check = get_enrollment_data($db, $student_id);

if ($enrollment_check['enrollment_status'] !== 'Enrolled') {
    header("Location: enrollment.php");
    exit();
}

$student           = $enrollment_check['student'];
$enrollment_status = $enrollment_check['enrollment_status'];

$student_name =
    $_SESSION['student_name']
    ?? $student['name']
    ?? $student['student_name']
    ?? $student['username']
    ?? 'Student';

function format_doc_name($type)
{
    $map = [
        'birth_certificate' => 'Birth Certificate',
        'valid_id'          => 'Valid ID / School ID',
        'id_picture'        => '2×2 ID Picture',
        'report_card'       => 'Report Card / Form 138',
        'good_moral'        => 'Good Moral Certificate',
        'transcript'        => 'Transcript of Records',
    ];
    return $map[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function fetch_approved_docs($student_id)
{
    try {
        $admin_db = new PDO("mysql:host=localhost;dbname=admin_db", "root", "");
        $admin_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $admin_db->prepare("
            SELECT ed.id, ed.document_type, ed.file_name, ed.status, ed.uploaded_at
            FROM enrollment_documents ed
            INNER JOIN (
                SELECT document_type, MAX(id) AS max_id
                FROM enrollment_documents
                WHERE student_id = :student_id
                  AND LOWER(status) = 'approved'
                GROUP BY document_type
            ) latest ON ed.id = latest.max_id
            ORDER BY ed.id ASC
        ");
        $stmt->execute([':student_id' => $student_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $valid_docs = [];
        foreach ($rows as $doc) {
            // Files live OUTSIDE webroot
            $check_path = dirname(__DIR__) . '/private_uploads/enrollment/' . $student_id . '/' . $doc['file_name'];

            if (is_file($check_path)) {
                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                $doc['ext']            = strtoupper($ext);
                $doc['icon']           = ($ext === 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
                $doc['display_name']   = format_doc_name($doc['document_type']);
                $doc['formatted_date'] = $doc['uploaded_at'] ? date('M Y', strtotime($doc['uploaded_at'])) : '';
                $valid_docs[]          = $doc;
            }
        }
        return $valid_docs;
    } catch (PDOException $e) {
        error_log("documents.php error: " . $e->getMessage());
        return [];
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    $approved_docs = fetch_approved_docs($student_id);
    echo json_encode([
        'status' => 'success',
        'count'  => count($approved_docs),
        'docs'   => $approved_docs,
    ]);
    exit();
}

$documents = fetch_approved_docs($student_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents — Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/student.css">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <h2><i class="fas fa-user-graduate"></i><span>Student Portal</span></h2>
    <nav class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-house"></i><span>Dashboard</span></a>
        <a href="profile.php"><i class="fas fa-user"></i><span>My Profile</span></a>
        <a href="subjects.php"><i class="fas fa-book-open"></i><span>My Subjects</span></a>
        <a href="grades.php"><i class="fas fa-chart-column"></i><span>My Grades</span></a>
        <a href="schedule.php"><i class="fas fa-calendar-days"></i><span>My Schedule</span></a>
        <a href="documents.php" class="active"><i class="fas fa-folder-open"></i><span>My Documents</span></a>
        <a href="announcements.php"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
        <a href="notifications.php"><i class="fas fa-bell"></i><span>Notifications</span></a>
    </nav>
    <div class="logout-section">
        <a href="logout.php" class="logout-btn"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <h1><i class="fas fa-folder-open"></i> My Documents</h1>
        <a href="profile.php" class="topbar-profile">
            <div class="avatar"><i class="fas fa-user"></i></div>
            <span><?php echo htmlspecialchars($student_name); ?></span>
        </a>
    </div>

    <div class="info-card">
        <h3><i class="fas fa-check-circle" style="color:#255F38;"></i> Verified Documents</h3>
        <p style="color:#718096; margin:0;">
            Below are your verified and approved official documents. You can download copies anytime.
        </p>
    </div>

    <div class="documents-wrapper">
        <div class="doc-list" id="documentList">
            <?php if (empty($documents)): ?>
                <div class="empty-docs">
                    <i class="fas fa-file-circle-check"></i>
                    <p style="margin:0; font-weight:600;">No approved documents available yet.</p>
                    <small style="color:#a0aec0;">Your submitted requirements will appear here once approved by the administrator.</small>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="doc-row approved" data-id="<?php echo $doc['id']; ?>">
                        <div class="doc-left">
                            <div class="doc-icon"><i class="fas <?php echo $doc['icon']; ?>"></i></div>
                            <div>
                                <div class="doc-name">
                                    <?php echo htmlspecialchars($doc['display_name']); ?>
                                    <span class="approved-badge">
                                        <i class="fas fa-check-circle"></i> Approved
                                    </span>
                                </div>
                                <div class="doc-meta">
                                    <?php echo htmlspecialchars($doc['formatted_date']); ?> · <?php echo htmlspecialchars($doc['ext']); ?>
                                </div>
                            </div>
                        </div>
                        <a href="download.php?id=<?php echo urlencode($doc['id']); ?>" class="action-btn download-btn">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
let lastDocIds = <?php echo json_encode(array_column($documents, 'id')); ?>;

function checkLiveDocuments() {
    if (document.visibilityState !== 'visible') return;

    fetch('documents.php?ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
        if (data.status !== 'success') return;
        const currentIds = data.docs.map(d => d.id);
        if (JSON.stringify(currentIds) === JSON.stringify(lastDocIds)) return;

        lastDocIds = currentIds;
        const container = document.getElementById('documentList');

        if (data.docs.length === 0) {
            container.innerHTML = `
                <div class="empty-docs">
                    <i class="fas fa-file-circle-check"></i>
                    <p style="margin:0; font-weight:600;">No approved documents available yet.</p>
                    <small style="color:#a0aec0;">Your submitted requirements will appear here once approved by the administrator.</small>
                </div>`;
            return;
        }

        container.innerHTML = data.docs.map(doc => `
            <div class="doc-row approved" data-id="${doc.id}">
                <div class="doc-left">
                    <div class="doc-icon"><i class="fas ${doc.icon}"></i></div>
                    <div>
                        <div class="doc-name">
                            ${doc.display_name}
                            <span class="approved-badge"><i class="fas fa-check-circle"></i> Approved</span>
                        </div>
                        <div class="doc-meta">${doc.formatted_date} · ${doc.ext}</div>
                    </div>
                </div>
                <a href="download.php?id=${encodeURIComponent(doc.id)}" class="action-btn download-btn">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>`).join('');
    })
    .catch(() => {});
}

setInterval(checkLiveDocuments, 5000);
</script>

</body>
</html>
