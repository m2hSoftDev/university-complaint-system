<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

try {
    $totalComplaints = $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
    
    $statusPendingId = getStatusIdByName($pdo, 'Pending') ?: 1;
    $pendingComplaints = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = $statusPendingId")->fetchColumn();
    
    $inProgressCount = $pdo->query(
        "SELECT COUNT(*) FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         WHERE cs.status_name IN ('Assigned', 'Accepted', 'In Progress')"
    )->fetchColumn();
    
    $statusResolvedId = getStatusIdByName($pdo, 'Resolved') ?: 5;
    $resolvedComplaints = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = $statusResolvedId")->fetchColumn();

    $categoryData = $pdo->query(
        "SELECT cc.category_name, COUNT(c.complaint_id) as count 
         FROM complaint_categories cc
         LEFT JOIN complaints c ON cc.category_id = c.category_id
         GROUP BY cc.category_id 
         ORDER BY count DESC"
    )->fetchAll();

    $buildingData = $pdo->query(
        "SELECT b.building_name, COUNT(c.complaint_id) as count 
         FROM buildings b
         LEFT JOIN complaints c ON b.building_id = c.building_id
         GROUP BY b.building_id 
         ORDER BY count DESC"
    )->fetchAll();

    $staffPerf = $pdo->query(
        "SELECT u.name, ms.employee_id, ms.specialization,
                COUNT(CASE WHEN a.assignment_status = 'Completed' THEN 1 END) as completed_tasks,
                ROUND(AVG(f.rating), 1) as avg_rating
         FROM maintenance_staff ms
         JOIN users u ON ms.user_id = u.user_id
         LEFT JOIN assignments a ON ms.staff_id = a.staff_id
         LEFT JOIN feedback f ON a.complaint_id = f.complaint_id
         WHERE u.status = 'active'
         GROUP BY ms.staff_id
         ORDER BY completed_tasks DESC, avg_rating DESC
         LIMIT 5"
    )->fetchAll();

    $recentComplaints = $pdo->query(
        "SELECT c.*, cs.status_name, cc.category_name, b.building_name, u.name as student_name,
                a.assignment_id, a.assignment_status, tu.name as assigned_staff_name
         FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         JOIN students s ON c.student_id = s.student_id
         JOIN users u ON s.user_id = u.user_id
         LEFT JOIN assignments a ON c.complaint_id = a.complaint_id AND a.assignment_status != 'Rejected'
         LEFT JOIN maintenance_staff ms ON a.staff_id = ms.staff_id
         LEFT JOIN users tu ON ms.user_id = tu.user_id
         ORDER BY c.created_at DESC
         LIMIT 6"
    )->fetchAll();

    $staffList = getAvailableStaff($pdo);

} catch (Exception $e) {
    $totalComplaints = $pendingComplaints = $inProgressCount = $resolvedComplaints = 0;
    $categoryData = $buildingData = $staffPerf = $recentComplaints = $staffList = [];
}

$chartCategories = [];
$chartCategoryCounts = [];
foreach ($categoryData as $cat) {
    $chartCategories[] = $cat['category_name'];
    $chartCategoryCounts[] = (int)$cat['count'];
}

$chartBuildings = [];
$chartBuildingCounts = [];
foreach ($buildingData as $bld) {
    $chartBuildings[] = $bld['building_name'];
    $chartBuildingCounts[] = (int)$bld['count'];
}

$pageTitle = "Admin Dashboard";
$currentPage = "dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid stagger-in">
    <div class="stat-card stat-primary">
        <div class="stat-card-top">
            <div class="stat-label">Total Logged</div>
            <div class="stat-icon"><i class="fas fa-folder"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $totalComplaints ?>">0</div>
        <div class="stat-change">Total tickets in system</div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-card-top">
            <div class="stat-label">Pending Reviews</div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $pendingComplaints ?>">0</div>
        <div class="stat-change">Need assignments</div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-card-top">
            <div class="stat-label">Active Repairs</div>
            <div class="stat-icon"><i class="fas fa-spinner fa-spin-slow"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $inProgressCount ?>">0</div>
        <div class="stat-change">Technicians working</div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-card-top">
            <div class="stat-label">Resolved Tickets</div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $resolvedComplaints ?>">0</div>
        <div class="stat-change">Completed & closed</div>
    </div>
</div>

<div class="charts-grid stagger-in mt-lg">
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie text-gradient"></i> By Complaint Category</h3>
        </div>
        <div class="chart-wrapper">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar text-gradient"></i> By Campus Location</h3>
        </div>
        <div class="chart-wrapper">
            <canvas id="buildingChart"></canvas>
        </div>
    </div>
</div>

<div class="grid-3 stagger-in mt-lg" style="grid-template-columns: 2fr 1fr;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-shield text-gradient"></i> Staff Performance Rating</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff Representative</th>
                            <th>ID Code</th>
                            <th>Specialization</th>
                            <th>Resolved Jobs</th>
                            <th>Customer Satisfaction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffPerf)): ?>
                            <tr>
                                <td colspan="5" class="table-empty">
                                    <i class="fas fa-users-slash"></i>
                                    <p>No active technicians logged in directory.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffPerf as $staff): ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-primary"><?= sanitize($staff['name']) ?></div>
                                    </td>
                                    <td><?= sanitize($staff['employee_id']) ?></td>
                                    <td><?= sanitize($staff['specialization'] ?: 'General') ?></td>
                                    <td><strong class="text-success"><?= $staff['completed_tasks'] ?></strong></td>
                                    <td>
                                        <?php if ($staff['avg_rating']): ?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div class="stars-display">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star star <?= $i <= round($staff['avg_rating']) ? 'filled' : '' ?>" style="font-size: 10px;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="text-sm font-bold text-warning"><?= $staff['avg_rating'] ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted text-sm">No satisfaction index</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="card stagger-in mt-lg">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-list text-gradient"></i> Recent Complaints & Dispatch</h3>
        <a href="<?= FRONTEND_URL ?>/admin/complaints.php" class="btn btn-outline btn-sm">Dispatch Center <i class="fas fa-arrow-right" style="margin-left: 4px;"></i></a>
    </div>
    
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Complaint Ref</th>
                        <th>Student Name</th>
                        <th>Complaint details</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Staff Assigned</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentComplaints)): ?>
                        <tr>
                            <td colspan="9" class="table-empty">
                                <i class="fas fa-clipboard-list"></i>
                                <p>No campus complaint dispatches logged in system.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentComplaints as $c): ?>
                            <tr>
                                <td>#CMP-<?= str_pad($c['complaint_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= sanitize($c['student_name']) ?></td>
                                <td>
                                    <div class="font-semibold text-primary"><?= sanitize($c['title']) ?></div>
                                    <div class="text-sm text-muted text-truncate" style="max-width: 200px;">
                                        <?= sanitize($c['description']) ?>
                                    </div>
                                </td>
                                <td><?= sanitize($c['category_name']) ?></td>
                                <td><?= sanitize($c['building_name']) ?></td>
                                <td><?= getPriorityBadge($c['priority']) ?></td>
                                <td><?= getStatusBadge($c['status_name']) ?></td>
                                <td>
                                    <?php if ($c['assigned_staff_name']): ?>
                                        <span class="text-primary font-semibold">
                                            <i class="fas fa-user-cog"></i> <?= sanitize($c['assigned_staff_name']) ?>
                                        </span>
                                        <div class="text-sm text-muted">(<?= sanitize($c['assignment_status']) ?>)</div>
                                    <?php elseif (!in_array($c['status_name'], ['Resolved', 'Closed', 'Rejected'])): ?>
                                        <button onclick="openDispatchModal(<?= $c['complaint_id'] ?>)" class="btn btn-primary btn-sm" style="font-size: 11px; padding: 4px 10px;">
                                            <i class="fas fa-user-plus"></i> Assign Technician
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end; gap: 6px;">
                                        <button onclick='viewTicket(<?= json_encode($c) ?>)' class="btn btn-outline btn-sm" title="View details">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if (!$c['assigned_staff_name'] && !in_array($c['status_name'], ['Resolved', 'Closed', 'Rejected'])): ?>
                                            <button onclick="openDispatchModal(<?= $c['complaint_id'] ?>)" class="btn btn-primary btn-sm">
                                                <i class="fas fa-user-check"></i> Assign
                                            </button>
                                        <?php elseif (!in_array($c['status_name'], ['Resolved', 'Closed', 'Rejected'])): ?>
                                            <button onclick="openDispatchModal(<?= $c['complaint_id'] ?>)" class="btn btn-outline btn-sm">
                                                <i class="fas fa-sync-alt"></i> Reassign
                                            </button>
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

<div class="modal-overlay" id="dispatch-modal">
    <div class="modal" style="max-width: 460px;">
        <div class="modal-header">
            <h3>Dispatch Job Assignment</h3>
            <button class="modal-close" onclick="Modal.close('dispatch-modal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="dispatch-form" onsubmit="assignStaff(event)">
            <input type="hidden" name="complaint_id" id="m-complaint-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="m-staff-id" class="form-label">Available Technicians <span class="required">*</span></label>
                    <div class="input-group">
                        <select name="staff_id" id="m-staff-id" class="form-control" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staffList as $staff): ?>
                                <option value="<?= $staff['staff_id'] ?>">
                                    <?= sanitize($staff['name']) ?> [<?= sanitize($staff['specialization'] ?: 'General Repairs') ?>] (<?= sanitize($staff['availability']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-hard-hat input-group-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('dispatch-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Dispatch Job</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="ticket-modal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Complaint Reference Specifications</h3>
            <button class="modal-close" onclick="Modal.close('ticket-modal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="complaint-detail">
                <div class="detail-main" style="grid-column: span 2;">
                    <div class="detail-section">
                        <div class="detail-row">
                            <div class="detail-label">Summary Title</div>
                            <div class="detail-value text-primary font-bold" id="v-title">Title</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Student Name</div>
                            <div class="detail-value" id="v-student">Student</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Building / Location</div>
                            <div class="detail-value" id="v-location">Location</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Submit Date</div>
                            <div class="detail-value" id="v-date">Date</div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Student Log Description</h4>
                        <p id="v-description" style="color:var(--text-secondary); padding:16px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:var(--radius-md); font-size:13.5px; line-height:1.6; white-space:pre-wrap;"></p>
                    </div>

                    <div class="detail-section text-center" id="v-image-container" style="display:none;">
                        <h4 style="font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase; text-align:left;">Attachment Reference</h4>
                        <img id="v-image" src="#" alt="Dispatch preview image" class="complaint-image" style="max-height: 250px; display:inline-block; width:auto; border-radius:var(--radius-md);">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="Modal.close('ticket-modal')">Close Window</button>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
function openDispatchModal(id) {
    document.getElementById('m-complaint-id').value = id;
    Modal.open('dispatch-modal');
}

async function assignStaff(e) {
    e.preventDefault();
    if (!validateForm('dispatch-form')) return;

    const formData = new FormData(document.getElementById('dispatch-form'));
    const res = await ajaxRequest('" . BACKEND_URL . "/admin/ajax/assign_complaint.php', 'POST', formData);
    if (res.success) {
        Toast.success('Dispatched', res.message);
        Modal.close('dispatch-modal');
        setTimeout(() => location.reload(), 1000);
    } else {
        Toast.error('Failure', res.message);
    }
}

function viewTicket(c) {
    document.getElementById('v-title').textContent = c.title;
    document.getElementById('v-student').textContent = c.student_name || '—';
    document.getElementById('v-location').textContent = c.building_name;
    document.getElementById('v-date').textContent = c.created_at;
    document.getElementById('v-description').textContent = c.description;

    const imgContainer = document.getElementById('v-image-container');
    const imgElement = document.getElementById('v-image');
    
    if (c.image) {
        imgElement.src = '" . BACKEND_URL . "/uploads/complaints/' + c.image;
        imgContainer.style.display = 'block';
    } else {
        imgElement.src = '#';
        imgContainer.style.display = 'none';
    }

    Modal.open('ticket-modal');
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Category Chart
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: " . json_encode($chartCategories) . ",
            datasets: [{
                data: " . json_encode($chartCategoryCounts) . ",
                backgroundColor: [
                    '#6366f1', '#8b5cf6', '#06b6d4', '#ec4899', '#3b82f6', 
                    '#10b981', '#f59e0b', '#ef4444', '#14b8a6', '#f43f5e'
                ],
                borderColor: '#111827',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#94a3b8',
                        font: { family: 'Inter', size: 11 }
                    }
                }
            }
        }
    });

    // 2. Building Chart
    const ctxBld = document.getElementById('buildingChart').getContext('2d');
    new Chart(ctxBld, {
        type: 'bar',
        data: {
            labels: " . json_encode($chartBuildings) . ",
            datasets: [{
                label: 'Complaints',
                data: " . json_encode($chartBuildingCounts) . ",
                backgroundColor: 'rgba(99, 102, 241, 0.85)',
                hoverBackgroundColor: '#6366f1',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    ticks: { color: '#94a3b8', font: { family: 'Inter' } },
                    grid: { color: 'rgba(148,163,184,0.05)' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#94a3b8', font: { family: 'Inter' } },
                    grid: { color: 'rgba(148,163,184,0.05)' }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
