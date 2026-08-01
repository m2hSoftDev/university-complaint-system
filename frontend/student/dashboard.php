<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];
$user = getCurrentUser();

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $totalComplaints = $stmt->fetchColumn();

    $statusPendingId = getStatusIdByName($pdo, 'Pending');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE student_id = ? AND status_id = ?");
    $stmt->execute([$studentId, $statusPendingId]);
    $pendingComplaints = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         WHERE c.student_id = ? AND cs.status_name IN ('Assigned', 'Accepted', 'In Progress')"
    );
    $stmt->execute([$studentId]);
    $inProgressComplaints = $stmt->fetchColumn();

    $statusResolvedId = getStatusIdByName($pdo, 'Resolved');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE student_id = ? AND status_id = ?");
    $stmt->execute([$studentId, $statusResolvedId]);
    $resolvedComplaints = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT c.*, cs.status_name, cc.category_name, b.building_name 
         FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         WHERE c.student_id = ? 
         ORDER BY c.created_at DESC 
         LIMIT 5"
    );
    $stmt->execute([$studentId]);
    $recentComplaints = $stmt->fetchAll();
} catch (Exception $e) {
    $totalComplaints = $pendingComplaints = $inProgressComplaints = $resolvedComplaints = 0;
    $recentComplaints = [];
}

$pageTitle = "Student Dashboard";
$currentPage = "dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid stagger-in">
    <div class="stat-card stat-primary">
        <div class="stat-card-top">
            <div class="stat-label">Total Complaints</div>
            <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $totalComplaints ?>">0</div>
        <div class="stat-change">All submitted tickets</div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-card-top">
            <div class="stat-label">Pending Review</div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $pendingComplaints ?>">0</div>
        <div class="stat-change">Awaiting administration</div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-card-top">
            <div class="stat-label">In Progress</div>
            <div class="stat-icon"><i class="fas fa-spinner fa-spin-slow"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $inProgressComplaints ?>">0</div>
        <div class="stat-change">Technician assigned</div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-card-top">
            <div class="stat-label">Resolved</div>
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $resolvedComplaints ?>">0</div>
        <div class="stat-change">Successfully closed</div>
    </div>
</div>

<div class="grid grid-3" style="grid-template-columns: 2fr 1fr; margin-top: 24px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-history text-accent"></i> Recent Submissions</div>
            <a href="<?= FRONTEND_URL ?>/student/my_complaints.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentComplaints)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 30px; color: var(--text-muted);">
                                    <i class="fas fa-clipboard" style="font-size: 28px; margin-bottom: 8px;"></i>
                                    <p>You haven't submitted any complaints yet.</p>
                                    <a href="<?= FRONTEND_URL ?>/student/submit_complaint.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">File Complaint</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentComplaints as $c): ?>
                                <tr>
                                    <td>#CMP-<?= str_pad($c['complaint_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td><strong><?= sanitize($c['title']) ?></strong></td>
                                    <td><?= sanitize($c['category_name']) ?></td>
                                    <td><?= getPriorityBadge($c['priority']) ?></td>
                                    <td><?= getStatusBadge($c['status_name']) ?></td>
                                    <td><?= formatDateShort($c['created_at']) ?></td>
                                    <td>
                                        <a href="<?= FRONTEND_URL ?>/student/view_complaint.php?id=<?= $c['complaint_id'] ?>" class="btn btn-secondary btn-sm" title="Track">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-magic text-accent"></i> Quick Actions</div>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; gap: 16px;">
            <a href="<?= FRONTEND_URL ?>/student/submit_complaint.php" class="btn btn-primary btn-lg w-full">
                <i class="fas fa-plus-circle"></i> File a Complaint
            </a>
            
            <div style="border-top: 1px solid var(--border); padding-top: 16px; margin-top: 8px;">
                <h4 style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px;">
                    Portal Guide
                </h4>
                <ul style="display: flex; flex-direction: column; gap: 12px; list-style: none;">
                    <li style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: var(--text-secondary);">
                        <i class="fas fa-info-circle text-info" style="margin-top: 3px;"></i>
                        <span>Complaints can be edited or deleted while in <strong>Pending</strong> status.</span>
                    </li>
                    <li style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: var(--text-secondary);">
                        <i class="fas fa-check-circle text-success" style="margin-top: 3px;"></i>
                        <span>Rate and provide feedback on resolved complaints.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
