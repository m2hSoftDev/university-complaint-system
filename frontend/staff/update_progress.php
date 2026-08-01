<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('staff');

$staffId = $_SESSION['staff_id'];
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.title, c.description, c.priority, c.image as complaint_image, 
                cc.category_name, b.building_name, cs.status_name, c.student_id, c.created_at as complaint_created
         FROM assignments a
         JOIN complaints c ON a.complaint_id = c.complaint_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         JOIN complaint_status cs ON c.status_id = cs.status_id
         WHERE a.assignment_id = ? AND a.staff_id = ?"
    );
    $stmt->execute([$assignmentId, $staffId]);
    $job = $stmt->fetch();
} catch (Exception $e) {
    $job = false;
}

if (!$job) {
    header('Location: ' . FRONTEND_URL . '/staff/assigned_tasks.php');
    exit();
}

if ($job['assignment_status'] !== 'Accepted') {
    $_SESSION['task_error'] = 'Progress updates are only allowed on accepted tasks.';
    header('Location: ' . FRONTEND_URL . '/staff/assigned_tasks.php');
    exit();
}

try {
    $statuses = getStatuses($pdo);
} catch (Exception $e) {
    $statuses = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status_id = (int)($_POST['status_id'] ?? 0);
    $progress_note = sanitize($_POST['progress_note'] ?? '');
    $mark_resolved = isset($_POST['mark_resolved']) && $_POST['mark_resolved'] === '1';

    if ($status_id <= 0 || empty($progress_note)) {
        $error = 'Please fill in all required fields.';
    } else {
        $imageName = null;
        $uploadOk = true;

        if (isset($_FILES['repair_image']) && $_FILES['repair_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadImage($_FILES['repair_image'], REPAIR_IMG_PATH);
            if ($uploadResult['success']) {
                $imageName = $uploadResult['filename'];
            } else {
                $error = 'Repair image upload failed: ' . $uploadResult['error'];
                $uploadOk = false;
            }
        }

        if ($uploadOk) {
            try {
                $pdo->beginTransaction();

                $stmtUpdate = $pdo->prepare(
                    "INSERT INTO complaint_updates (complaint_id, staff_id, status_id, progress_note, progress_image) 
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmtUpdate->execute([
                    $job['complaint_id'],
                    $staffId,
                    $status_id,
                    $progress_note,
                    $imageName
                ]);

                if ($mark_resolved) {
                    $statusResolvedId = getStatusIdByName($pdo, 'Resolved');
                    if ($statusResolvedId) $status_id = $statusResolvedId;

                    $stmtComplaint = $pdo->prepare(
                        "UPDATE complaints SET status_id = ?, resolved_at = NOW() WHERE complaint_id = ?"
                    );
                    $stmtComplaint->execute([$status_id, $job['complaint_id']]);

                    $stmtAssign = $pdo->prepare(
                        "UPDATE assignments 
                         SET assignment_status = 'Completed', completed_date = NOW(), repair_notes = ?, repair_image = ? 
                         WHERE assignment_id = ?"
                    );
                    $stmtAssign->execute([$progress_note, $imageName, $assignmentId]);

                    $stmtStudUser = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
                    $stmtStudUser->execute([$job['student_id']]);
                    $studentUserId = $stmtStudUser->fetchColumn();

                    if ($studentUserId) {
                        createNotification(
                            $pdo,
                            $studentUserId,
                            "Complaint Resolved! 🎉",
                            "Your complaint #CMP-" . str_pad($job['complaint_id'], 4, '0', STR_PAD_LEFT) . " has been resolved. Please review and provide feedback."
                        );
                    }

                    $stmtStaff = $pdo->prepare("UPDATE maintenance_staff SET availability = 'available' WHERE staff_id = ?");
                    $stmtStaff->execute([$staffId]);

                } else {
                    $stmtComplaint = $pdo->prepare(
                        "UPDATE complaints SET status_id = ? WHERE complaint_id = ?"
                    );
                    $stmtComplaint->execute([$status_id, $job['complaint_id']]);
                }

                $pdo->commit();

                $_SESSION['task_success'] = $mark_resolved ? 'Job marked as resolved.' : 'Progress logged successfully.';
                header('Location: ' . FRONTEND_URL . '/staff/assigned_tasks.php');
                exit();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Database error: ' . $e->getMessage();
                if ($imageName && file_exists(REPAIR_IMG_PATH . $imageName)) {
                    unlink(REPAIR_IMG_PATH . $imageName);
                }
            }
        }
    }
}

$pageTitle = "Update Job Progress";
$currentPage = "assigned";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="complaint-detail stagger-in">
    <div class="detail-main">
        <div class="card mb-lg">
            <div class="card-header">
                <h3><i class="fas fa-tools text-gradient"></i> Report Progress & Actions</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form action="<?= FRONTEND_URL ?>/staff/update_progress.php?id=<?= $assignmentId ?>" method="POST" enctype="multipart/form-data" id="progress-form" onsubmit="return validateForm('progress-form')">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status_id" class="form-label">Progress Status <span class="required">*</span></label>
                            <div class="input-group">
                                <select name="status_id" id="status_id" class="form-control" required>
                                    <option value="">-- Choose Status --</option>
                                    <?php foreach ($statuses as $st): ?>
                                        <?php if (!in_array($st['status_name'], ['Pending', 'Assigned', 'Rejected', 'Closed'])): ?>
                                            <option value="<?= $st['status_id'] ?>" <?= $st['status_name'] === 'In Progress' ? 'selected' : '' ?>>
                                                <?= sanitize($st['status_name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-tasks input-group-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Job Completion</label>
                            <div class="checkbox-option">
                                <input type="checkbox" name="mark_resolved" id="mark_resolved" value="1">
                                <label for="mark_resolved"><i class="fas fa-check-circle"></i> Mark Job as Resolved</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="progress_note" class="form-label">Repair Actions & Notes <span class="required">*</span></label>
                        <textarea name="progress_note" id="progress_note" class="form-control" placeholder="Write description of your actions e.g. Replaced capacitor, patched water pipe leak..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Attach Repair Reference Image (Optional)</label>
                        <div class="upload-zone" id="upload_zone">
                            <i class="fas fa-camera"></i>
                            <div class="upload-text">Upload photo showing resolution reference</div>
                            <input type="file" name="repair_image" id="repair_image" accept="image/*">
                        </div>
                        <img id="image_preview" class="image-preview" src="#" alt="Repair Reference Photo">
                    </div>

                    <div class="flex justify-between items-center" style="margin-top: 32px;">
                        <a href="<?= FRONTEND_URL ?>/staff/assigned_tasks.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save & Log Progress
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="detail-sidebar">
        <div class="card mb-lg">
            <div class="card-header">
                <h3><i class="fas fa-info-circle text-gradient"></i> Ticket Reference</h3>
            </div>
            <div class="card-body">
                <h4 style="font-size: 14px; margin-bottom: 12px; color: var(--text-primary);">
                    <?= sanitize($job['title']) ?>
                </h4>

                <div class="detail-section mb-0">
                    <div class="detail-row" style="padding: 6px 0; font-size: 13px;">
                        <div class="detail-label" style="width: 90px; font-size: 11px;">Category</div>
                        <div class="detail-value"><?= sanitize($job['category_name']) ?></div>
                    </div>
                    <div class="detail-row" style="padding: 6px 0; font-size: 13px;">
                        <div class="detail-label" style="width: 90px; font-size: 11px;">Location</div>
                        <div class="detail-value"><?= sanitize($job['building_name']) ?></div>
                    </div>
                    <div class="detail-row" style="padding: 6px 0; font-size: 13px;">
                        <div class="detail-label" style="width: 90px; font-size: 11px;">Priority</div>
                        <div class="detail-value"><?= getPriorityBadge($job['priority']) ?></div>
                    </div>
                    <div class="detail-row" style="padding: 6px 0; font-size: 13px;">
                        <div class="detail-label" style="width: 90px; font-size: 11px;">Logged</div>
                        <div class="detail-value"><?= formatDateShort($job['complaint_created']) ?></div>
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 6px;">
                        Description Details
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.4; background: rgba(0,0,0,0.1); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <?= sanitize($job['description']) ?>
                    </p>
                </div>

                <?php if (!empty($job['complaint_image'])): ?>
                    <div style="margin-top: 14px;">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 6px;">
                            Attachment Reference
                        </div>
                        <img src="<?= BACKEND_URL ?>/uploads/complaints/<?= sanitize($job['complaint_image']) ?>" alt="Reference preview" style="width: 100%; border-radius: var(--radius-md); max-height: 150px; object-fit: cover; border: 1px solid var(--border);" onclick="window.open(this.src)">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script>
document.getElementById('mark_resolved').addEventListener('change', function() {
    const statusSelect = document.getElementById('status_id');
    if (this.checked) {
        for (let i = 0; i < statusSelect.options.length; i++) {
            if (statusSelect.options[i].text === 'Resolved') {
                statusSelect.selectedIndex = i;
                break;
            }
        }
        statusSelect.disabled = true;
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'status_id';
        hiddenInput.id = 'status_id_hidden';
        hiddenInput.value = statusSelect.value;
        statusSelect.form.appendChild(hiddenInput);
    } else {
        statusSelect.disabled = false;
        const hiddenInput = document.getElementById('status_id_hidden');
        if (hiddenInput) hiddenInput.remove();
    }
});
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
