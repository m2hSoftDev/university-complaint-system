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
        "SELECT a.*, c.title, c.description, c.priority, c.image as complaint_image, c.created_at as complaint_created, 
                cc.category_name, b.building_name, cs.status_name, u.name as student_name
         FROM assignments a
         JOIN complaints c ON a.complaint_id = c.complaint_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN students s ON c.student_id = s.student_id
         JOIN users u ON s.user_id = u.user_id
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
                                        <button onclick='viewTask(<?= json_encode($task) ?>)' class="btn btn-outline btn-sm btn-icon" title="View details"><i class="fas fa-eye"></i></button>
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

<!-- Task Details Modal Overlay for Technician -->
<div class="modal-overlay" id="task-modal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Assigned Job Specifications</h3>
            <button class="modal-close" onclick="Modal.close('task-modal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="complaint-detail">
                <div class="detail-main" style="grid-column: span 2;">
                    <div class="detail-section">
                        <div class="detail-row">
                            <div class="detail-label">Job Title</div>
                            <div class="detail-value text-primary font-bold" id="vt-title">Title</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Student Name</div>
                            <div class="detail-value" id="vt-student">Student</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Building / Location</div>
                            <div class="detail-value" id="vt-location">Location</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Category</div>
                            <div class="detail-value" id="vt-category">Category</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Submitted On</div>
                            <div class="detail-value" id="vt-date">Date</div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Student Log Description</h4>
                        <p id="vt-description" style="color:var(--text-secondary); padding:16px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:var(--radius-md); font-size:13.5px; line-height:1.6; white-space:pre-wrap;"></p>
                    </div>

                    <div class="detail-section text-center" id="vt-image-container" style="display:none;">
                        <h4 style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; text-align:left;">Attachment Reference Image</h4>
                        <img id="vt-image" src="#" alt="Complaint photo preview" class="complaint-image" style="max-height: 250px; display:inline-block; width:auto; border-radius:var(--radius-md); cursor:pointer;" onclick="window.open(this.src)">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" id="vt-modal-footer">
            <button class="btn btn-outline" onclick="Modal.close('task-modal')">Close Window</button>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script>
let currentTask = null;

function viewTask(task) {
    currentTask = task;
    document.getElementById('vt-title').textContent = task.title;
    document.getElementById('vt-student').textContent = task.student_name || '—';
    document.getElementById('vt-location').textContent = task.building_name;
    document.getElementById('vt-category').textContent = task.category_name;
    document.getElementById('vt-date').textContent = task.complaint_created || task.assigned_date;
    document.getElementById('vt-description').textContent = task.description;

    const imgContainer = document.getElementById('vt-image-container');
    const imgElement = document.getElementById('vt-image');
    
    if (task.complaint_image) {
        imgElement.src = '" . BACKEND_URL . "/uploads/complaints/' + task.complaint_image;
        imgContainer.style.display = 'block';
    } else {
        imgElement.src = '#';
        imgContainer.style.display = 'none';
    }

    const footer = document.getElementById('vt-modal-footer');
    if (task.assignment_status === 'Assigned') {
        footer.innerHTML = `
            <button class=\"btn btn-outline\" onclick=\"Modal.close('task-modal')\">Close</button>
            <button onclick=\"handleStatusFromModal('Rejected')\" class=\"btn btn-danger\">Reject Job</button>
            <button onclick=\"handleStatusFromModal('Accepted')\" class=\"btn btn-success\">Accept Job</button>
        `;
    } else if (task.assignment_status === 'Accepted') {
        footer.innerHTML = `
            <button class=\"btn btn-outline\" onclick=\"Modal.close('task-modal')\">Close</button>
            <a href=\"" . FRONTEND_URL . "/staff/update_progress.php?id=\${task.assignment_id}\" class=\"btn btn-primary\">Update Progress</a>
        `;
    } else {
        footer.innerHTML = `<button class=\"btn btn-outline\" onclick=\"Modal.close('task-modal')\">Close Window</button>`;
    }

    Modal.open('task-modal');
}

function handleStatusFromModal(newStatus) {
    if (currentTask) {
        Modal.close('task-modal');
        handleStatus(currentTask.assignment_id, newStatus);
    }
}

async function handleStatus(id, newStatus) {
    const isAccept = newStatus.toLowerCase().startsWith('accept');
    const actionKey = isAccept ? 'accept' : 'reject';
    const actionTitle = isAccept ? 'Accept' : 'Reject';

    confirmAction(
        actionTitle + ' Job Dispatch', 
        'Are you sure you want to ' + actionKey + ' this assignment?',
        async () => {
            const formData = new FormData();
            formData.append('action', actionKey);
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
