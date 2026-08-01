<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

if (isset($_SESSION['feedback_success'])) {
    $success = $_SESSION['feedback_success'];
    unset($_SESSION['feedback_success']);
}

try {
    $stmt = $pdo->prepare(
        "SELECT c.*, cs.status_name, cc.category_name, b.building_name 
         FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         WHERE c.complaint_id = ? AND c.student_id = ?"
    );
    $stmt->execute([$complaintId, $studentId]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
        exit();
    }

    $stmt = $pdo->prepare(
        "SELECT a.*, u.name as staff_name, u.email as staff_email, ms.employee_id, ms.designation 
         FROM assignments a
         JOIN maintenance_staff ms ON a.staff_id = ms.staff_id
         JOIN users u ON ms.user_id = u.user_id
         WHERE a.complaint_id = ? 
         ORDER BY a.assigned_date DESC 
         LIMIT 1"
    );
    $stmt->execute([$complaintId]);
    $assignment = $stmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT cu.*, cs.status_name, u.name as staff_name 
         FROM complaint_updates cu
         JOIN complaint_status cs ON cu.status_id = cs.status_id
         JOIN maintenance_staff ms ON cu.staff_id = ms.staff_id
         JOIN users u ON ms.user_id = u.user_id
         WHERE cu.complaint_id = ? 
         ORDER BY cu.created_at DESC"
    );
    $stmt->execute([$complaintId]);
    $updates = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
    $stmt->execute([$complaintId]);
    $feedback = $stmt->fetch();

} catch (Exception $e) {
    $error = 'Failed to load complaint data: ' . $e->getMessage();
}

$pageTitle = "Track Complaint";
$currentPage = "complaints";
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= $success ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <a href="<?= FRONTEND_URL ?>/student/my_complaints.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Complaints
    </a>
</div>

<div class="grid grid-3" style="grid-template-columns: 2fr 1fr;">    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-alt text-accent"></i> Complaint Details (#CMP-<?= str_pad($complaint['complaint_id'], 4, '0', STR_PAD_LEFT) ?>)</div>
                <div>
                    <?= getStatusBadge($complaint['status_name']) ?>
                    <?= getPriorityBadge($complaint['priority']) ?>
                </div>
            </div>
            
            <div class="card-body">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: var(--text-primary);">
                    <?= sanitize($complaint['title']) ?>
                </h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; background: var(--bg-secondary); padding: 16px; border-radius: var(--radius-md);">
                    <div><strong>Category:</strong> <?= sanitize($complaint['category_name']) ?></div>
                    <div><strong>Location:</strong> <?= sanitize($complaint['building_name']) ?></div>
                    <div><strong>Submitted:</strong> <?= formatDate($complaint['created_at']) ?></div>
                    <?php if ($complaint['resolved_at']): ?>
                        <div class="text-success"><strong>Resolved At:</strong> <?= formatDate($complaint['resolved_at']) ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">
                        Description
                    </h4>
                    <p style="color: var(--text-secondary); line-height: 1.6; white-space: pre-wrap; font-size: 14px; background: var(--bg-secondary); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <?= sanitize($complaint['description']) ?>
                    </p>
                </div>

                <?php if (!empty($complaint['image'])): ?>
                    <div style="margin-bottom: 20px;">
                        <h4 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">
                            Attachment Reference
                        </h4>
                        <img src="<?= BACKEND_URL ?>/uploads/complaints/<?= sanitize($complaint['image']) ?>" alt="Reference image" style="max-width: 100%; max-height: 350px; border-radius: var(--radius-md); border: 1px solid var(--border); cursor: pointer;" onclick="window.open(this.src)">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($complaint['status_name'] === 'Resolved' || $complaint['status_name'] === 'Closed'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-star text-warning"></i> Resolution Feedback</div>
                </div>
                <div class="card-body">
                    <?php if ($feedback): ?>
                        <div style="background: var(--success-bg); border: 1px solid #6ee7b7; padding: 18px; border-radius: var(--radius-md);">
                            <div style="font-weight: 700; color: var(--success); margin-bottom: 4px;">Rating: <?= $feedback['rating'] ?> / 5 Stars</div>
                            <p style="font-size: 14px; color: var(--text-primary); font-style: italic;">
                                "<?= sanitize($feedback['comments']) ?>"
                            </p>
                        </div>
                    <?php else: ?>
                        <form action="<?= FRONTEND_URL ?>/student/feedback.php" method="POST">
                            <input type="hidden" name="complaint_id" value="<?= $complaintId ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Rating (1 to 5 Stars)</label>
                                <select name="rating" class="form-control" required style="max-width: 200px;">
                                    <option value="5">5 — Excellent</option>
                                    <option value="4">4 — Good</option>
                                    <option value="3">3 — Satisfactory</option>
                                    <option value="2">2 — Poor</option>
                                    <option value="1">1 — Very Bad</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="comments" class="form-label">Review / Comments</label>
                                <textarea name="comments" id="comments" class="form-control" placeholder="Share your experience..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Submit Feedback
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($assignment): ?>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-cog text-accent"></i> Assigned Technician</div>
                </div>
                <div class="card-body">
                    <div style="font-weight: 700; font-size: 15px; color: var(--text-primary);"><?= sanitize($assignment['staff_name']) ?></div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;"><?= sanitize($assignment['designation'] ?: 'Technician') ?> (ID: <?= sanitize($assignment['employee_id']) ?>)</div>

                    <?php if (!empty($assignment['repair_notes'])): ?>
                        <div style="padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); margin-top: 12px;">
                            <strong style="font-size: 12px; text-transform: uppercase; color: var(--text-accent);">Repair Notes:</strong>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;"><?= sanitize($assignment['repair_notes']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($assignment['repair_image'])): ?>
                        <div style="margin-top: 12px;">
                            <strong style="font-size: 12px; text-transform: uppercase; color: var(--text-accent);">Repair Photo:</strong>
                            <img src="<?= BACKEND_URL ?>/uploads/repairs/<?= sanitize($assignment['repair_image']) ?>" alt="Repair photo" style="width: 100%; border-radius: var(--radius-md); max-height: 180px; object-fit: cover; border: 1px solid var(--border); margin-top: 6px; cursor: pointer;" onclick="window.open(this.src)">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history text-accent"></i> Updates Timeline</div>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="border-left: 2px solid var(--accent-indigo); padding-left: 12px;">
                        <div style="font-weight: 700; font-size: 13px;">Complaint Filed</div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= formatDate($complaint['created_at']) ?></div>
                    </div>
                    <?php foreach ($updates as $u): ?>
                        <div style="border-left: 2px solid var(--info); padding-left: 12px;">
                            <div style="font-weight: 700; font-size: 13px;"><?= sanitize($u['status_name']) ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary);"><?= sanitize($u['progress_note']) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= formatDate($u['created_at']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
