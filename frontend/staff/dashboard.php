<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('staff');

$staffId = $_SESSION['staff_id'];
$user = getCurrentUser();

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM assignments 
         WHERE staff_id = ? AND assignment_status != 'Rejected'"
    );
    $stmt->execute([$staffId]);
    $assignedTasks = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM assignments 
         WHERE staff_id = ? AND assignment_status = 'Completed' 
         AND DATE(completed_date) = CURDATE()"
    );
    $stmt->execute([$staffId]);
    $completedToday = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM assignments 
         WHERE staff_id = ? AND assignment_status = 'Assigned'"
    );
    $stmt->execute([$staffId]);
    $pendingTasks = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM assignments 
         WHERE staff_id = ? AND assignment_status = 'Accepted'"
    );
    $stmt->execute([$staffId]);
    $inProgressTasks = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT a.*, c.title, c.description, c.priority, cc.category_name, b.building_name, cs.status_name
         FROM assignments a
         JOIN complaints c ON a.complaint_id = c.complaint_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         JOIN complaint_status cs ON c.status_id = cs.status_id
         WHERE a.staff_id = ? AND a.assignment_status IN ('Assigned', 'Accepted')
         ORDER BY c.priority = 'High' DESC, a.assigned_date DESC"
    );
    $stmt->execute([$staffId]);
    $activeTasks = $stmt->fetchAll();

} catch (Exception $e) {
    $assignedTasks = $completedToday = $pendingTasks = $inProgressTasks = 0;
    $activeTasks = [];
}

$pageTitle = "Staff Dashboard";
$currentPage = "dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid stagger-in">
    <div class="stat-card stat-primary">
        <div class="stat-card-top">
            <div class="stat-label">Assigned Tasks</div>
            <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $assignedTasks ?>">0</div>
        <div class="stat-change">Active or finished items</div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-card-top">
            <div class="stat-label">New Offers</div>
            <div class="stat-icon"><i class="fas fa-bell"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $pendingTasks ?>">0</div>
        <div class="stat-change">Awaiting your response</div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-card-top">
            <div class="stat-label">Working On</div>
            <div class="stat-icon"><i class="fas fa-tools fa-spin-slow"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $inProgressTasks ?>">0</div>
        <div class="stat-change">Accepted jobs in progress</div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-card-top">
            <div class="stat-label">Done Today</div>
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $completedToday ?>">0</div>
        <div class="stat-change">Successfully closed today</div>
    </div>
</div>

<div class="card stagger-in mt-lg">
    <div class="card-header">
        <h3><i class="fas fa-bolt text-gradient"></i> Job Dispatches & Queue</h3>
        <a href="<?= FRONTEND_URL ?>/staff/assigned_tasks.php" class="btn btn-outline btn-sm">Dispatch board</a>
    </div>
    
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Ref</th>
                        <th>Complaint Details</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Assignment Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeTasks)): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                <i class="fas fa-clipboard-check"></i>
                                <p>Relax! No pending dispatches at this moment.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeTasks as $task): ?>
                            <tr>
                                <td>#JOB-<?= str_pad($task['assignment_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="font-semibold text-primary"><?= sanitize($task['title']) ?></div>
                                    <div class="text-sm text-muted text-truncate" style="max-width: 250px;">
                                        <?= sanitize($task['description']) ?>
                                    </div>
                                </td>
                                <td><?= sanitize($task['category_name']) ?></td>
                                <td><?= sanitize($task['building_name']) ?></td>
                                <td><?= getPriorityBadge($task['priority']) ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'badge-warning';
                                        if ($task['assignment_status'] === 'Accepted') $badgeClass = 'badge-primary';
                                        if ($task['assignment_status'] === 'Completed') $badgeClass = 'badge-success';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= sanitize($task['assignment_status']) ?></span>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <?php if ($task['assignment_status'] === 'Assigned'): ?>
                                            <button onclick="handleStatus(<?= $task['assignment_id'] ?>, 'Accepted')" class="btn btn-success btn-sm">Accept</button>
                                            <button onclick="handleStatus(<?= $task['assignment_id'] ?>, 'Rejected')" class="btn btn-danger btn-sm">Reject</button>
                                        <?php elseif ($task['assignment_status'] === 'Accepted'): ?>
                                            <a href="<?= FRONTEND_URL ?>/staff/update_progress.php?id=<?= $task['assignment_id'] ?>" class="btn btn-primary btn-sm">
                                                Update Progress
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script>
async function handleStatus(id, newStatus) {
    const actionText = newStatus === 'Accepted' ? 'accept' : 'reject';
    confirmAction(
        newStatus + ' Job Disptach', 
        'Are you sure you want to ' + actionText + ' this assignment?',
        async () => {
            const formData = new FormData();
            formData.append('action', newStatus.toLowerCase());
            formData.append('assignment_id', id);
            
            const res = await ajaxRequest('" . BACKEND_URL . "/staff/ajax/task_actions.php', 'POST', formData);
            if (res.success) {
                Toast.success('Success', res.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error('Failure', res.message);
            }
        }
    );
}
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
