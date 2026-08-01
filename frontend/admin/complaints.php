<?php
/**
 * Admin Ticket Dispatch Center
 * Campus Complaint & Maintenance Management System
 */
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

// Filters
$statusFilter = sanitize($_GET['status'] ?? '');
$categoryFilter = sanitize($_GET['category'] ?? '');
$priorityFilter = sanitize($_GET['priority'] ?? '');
$buildingFilter = sanitize($_GET['building'] ?? '');

$queryParams = [];
$whereClause = "WHERE 1=1";

if (!empty($statusFilter)) {
    $whereClause .= " AND cs.status_name = ?";
    $queryParams[] = $statusFilter;
}

if (!empty($categoryFilter)) {
    $whereClause .= " AND c.category_id = ?";
    $queryParams[] = (int)$categoryFilter;
}

if (!empty($priorityFilter)) {
    $whereClause .= " AND c.priority = ?";
    $queryParams[] = $priorityFilter;
}

if (!empty($buildingFilter)) {
    $whereClause .= " AND c.building_id = ?";
    $queryParams[] = (int)$buildingFilter;
}

try {
    // Pagination setup
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         $whereClause"
    );
    $countStmt->execute($queryParams);
    $totalItems = $countStmt->fetchColumn();
} catch (Exception $e) {
    $totalItems = 0;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination = paginate($totalItems, 10, $page);

// Add limits
$queryParams[] = $pagination['offset'];
$queryParams[] = $pagination['per_page'];

try {
    $stmt = $pdo->prepare(
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
         $whereClause
         ORDER BY c.created_at DESC
         LIMIT ?, ?"
    );
    $stmt->execute($queryParams);
    $complaints = $stmt->fetchAll();
} catch (Exception $e) {
    $complaints = [];
}

// Fetch categories, locations, staff list for dropdowns & dispatch modal
try {
    $categories = getCategories($pdo);
    $buildings = getBuildings($pdo);
    $staffList = getAvailableStaff($pdo);
} catch (Exception $e) {
    $categories = $buildings = $staffList = [];
}

$pageTitle = "Dispatch Center";
$currentPage = "complaints";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filters Card -->
<div class="card mb-lg stagger-in">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/admin/complaints.php" class="table-filters" id="filter-form">
            <div class="form-group mb-0">
                <select name="status" class="form-control" onchange="document.getElementById('filter-form').submit()">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Assigned" <?= $statusFilter === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Resolved" <?= $statusFilter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="Closed" <?= $statusFilter === 'Closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>

            <div class="form-group mb-0">
                <select name="category" class="form-control" onchange="document.getElementById('filter-form').submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (int)$categoryFilter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-0">
                <select name="priority" class="form-control" onchange="document.getElementById('filter-form').submit()">
                    <option value="">All Priorities</option>
                    <option value="Low" <?= $priorityFilter === 'Low' ? 'selected' : '' ?>>Low</option>
                    <option value="Medium" <?= $priorityFilter === 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="High" <?= $priorityFilter === 'High' ? 'selected' : '' ?>>High</option>
                </select>
            </div>

            <div class="form-group mb-0">
                <select name="building" class="form-control" onchange="document.getElementById('filter-form').submit()">
                    <option value="">All Buildings</option>
                    <?php foreach ($buildings as $bld): ?>
                        <option value="<?= $bld['building_id'] ?>" <?= (int)$buildingFilter === (int)$bld['building_id'] ? 'selected' : '' ?>>
                            <?= sanitize($bld['building_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($statusFilter) || !empty($categoryFilter) || !empty($priorityFilter) || !empty($buildingFilter)): ?>
                <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-outline btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Dispatch Table Card -->
<div class="card stagger-in">
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
                        <th class="text-right">Action Board</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($complaints)): ?>
                        <tr>
                            <td colspan="9" class="table-empty">
                                <i class="fas fa-clipboard-list"></i>
                                <p>No campus complaint dispatches logged matching filter criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($complaints as $c): ?>
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
                                <td>
                                    <select onchange="updatePriority(<?= $c['complaint_id'] ?>, this.value)" class="form-control btn-sm" style="padding: 2px 8px; width: 100px; min-width: auto; height: auto;">
                                        <option value="Low" <?= $c['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                                        <option value="Medium" <?= $c['priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="High" <?= $c['priority'] === 'High' ? 'selected' : '' ?>>High</option>
                                    </select>
                                </td>
                                <td><?= getStatusBadge($c['status_name']) ?></td>
                                <td>
                                    <?php if ($c['assigned_staff_name']): ?>
                                        <span class="text-primary font-semibold">
                                            <i class="fas fa-user-cog"></i> <?= sanitize($c['assigned_staff_name']) ?>
                                        </span>
                                        <div class="text-sm text-muted">(<?= sanitize($c['assignment_status']) ?>)</div>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <!-- View / Timeline Details -->
                                        <button onclick="viewTicket(<?= json_encode($c) ?>)" class="btn btn-outline btn-sm btn-icon" title="View details"><i class="fas fa-eye"></i></button>
                                        
                                        <!-- Assign Action Button -->
                                        <?php if (!$c['assigned_staff_name'] && $c['status_name'] !== 'Resolved' && $c['status_name'] !== 'Closed' && $c['status_name'] !== 'Rejected'): ?>
                                            <button onclick="openDispatchModal(<?= $c['complaint_id'] ?>)" class="btn btn-primary btn-sm">Assign</button>
                                        <?php elseif ($c['status_name'] !== 'Resolved' && $c['status_name'] !== 'Closed' && $c['status_name'] !== 'Rejected'): ?>
                                            <!-- Re-assign -->
                                            <button onclick="openDispatchModal(<?= $c['complaint_id'] ?>)" class="btn btn-outline btn-sm">Reassign</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Render Pagination -->
        <?php 
            $paginationUrl = BASE_URL . "/admin/complaints.php?status=" . urlencode($statusFilter) . "&category=" . urlencode($categoryFilter) . "&priority=" . urlencode($priorityFilter) . "&building=" . urlencode($buildingFilter);
            echo renderPagination($pagination, $paginationUrl);
        ?>
    </div>
</div>

<!-- Dispatch Assignment Modal Overlay -->
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

<!-- Ticket Specifications Modal Overlay -->
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
<script>
function openDispatchModal(id) {
    document.getElementById('m-complaint-id').value = id;
    Modal.open('dispatch-modal');
}

async function assignStaff(e) {
    e.preventDefault();
    if (!validateForm('dispatch-form')) return;

    const formData = new FormData(document.getElementById('dispatch-form'));
    const res = await ajaxRequest('" . BASE_URL . "/admin/assign_complaint.php', 'POST', formData);
    if (res.success) {
        Toast.success('Dispatched', res.message);
        Modal.close('dispatch-modal');
        setTimeout(() => location.reload(), 1000);
    } else {
        Toast.error('Failure', res.message);
    }
}

async function updatePriority(id, value) {
    const formData = new FormData();
    formData.append('action', 'priority');
    formData.append('complaint_id', id);
    formData.append('priority', value);

    const res = await ajaxRequest('" . BASE_URL . "/admin/ajax/complaint_actions.php', 'POST', formData);
    if (res.success) {
        Toast.success('Updated', res.message);
    } else {
        Toast.error('Failure', res.message);
    }
}

function viewTicket(c) {
    document.getElementById('v-title').textContent = c.title;
    document.getElementById('v-location').textContent = c.building_name;
    document.getElementById('v-date').textContent = c.created_at;
    document.getElementById('v-description').textContent = c.description;

    const imgContainer = document.getElementById('v-image-container');
    const imgElement = document.getElementById('v-image');
    
    if (c.image) {
        imgElement.src = '" . BASE_URL . "/uploads/complaints/' + c.image;
        imgContainer.style.display = 'block';
    } else {
        imgElement.src = '#';
        imgContainer.style.display = 'none';
    }

    Modal.open('ticket-modal');
}
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
