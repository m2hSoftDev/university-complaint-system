<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];
$success = '';
$error = '';

if (isset($_SESSION['complaint_success'])) {
    $success = $_SESSION['complaint_success'];
    unset($_SESSION['complaint_success']);
}

$statusFilter = sanitize($_GET['status'] ?? '');
$categoryFilter = sanitize($_GET['category'] ?? '');
$searchFilter = sanitize($_GET['search'] ?? '');

$queryParams = [$studentId];
$whereClause = "WHERE c.student_id = ?";

if (!empty($statusFilter)) {
    $whereClause .= " AND cs.status_name = ?";
    $queryParams[] = $statusFilter;
}

if (!empty($categoryFilter)) {
    $whereClause .= " AND cc.category_id = ?";
    $queryParams[] = (int)$categoryFilter;
}

if (!empty($searchFilter)) {
    $whereClause .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $queryParams[] = "%$searchFilter%";
    $queryParams[] = "%$searchFilter%";
}

try {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         $whereClause"
    );
    $countStmt->execute($queryParams);
    $totalItems = $countStmt->fetchColumn();
} catch (Exception $e) {
    $totalItems = 0;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination = paginate($totalItems, 8, $page);

$offset = (int)$pagination['offset'];
$perPage = (int)$pagination['per_page'];

try {
    $stmt = $pdo->prepare(
        "SELECT c.*, cs.status_name, cc.category_name, b.building_name 
         FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         $whereClause
         ORDER BY c.created_at DESC
         LIMIT {$offset}, {$perPage}"
    );
    $stmt->execute($queryParams);
    $complaints = $stmt->fetchAll();
} catch (Exception $e) {
    $complaints = [];
    $error = 'Failed to load complaints list.';
}

try {
    $categories = getCategories($pdo);
} catch (Exception $e) {
    $categories = [];
}

$pageTitle = "My Complaints";
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

<div class="card mb-lg">
    <div class="card-body">
        <form method="GET" action="<?= FRONTEND_URL ?>/student/my_complaints.php" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;" id="filter-form">
            <div class="input-group" style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= sanitize($searchFilter) ?>">
                <i class="fas fa-search input-group-icon"></i>
            </div>
            
            <select name="status" class="form-control" style="width: auto;" onchange="document.getElementById('filter-form').submit()">
                <option value="">All Statuses</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Assigned" <?= $statusFilter === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Resolved" <?= $statusFilter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="Closed" <?= $statusFilter === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>

            <select name="category" class="form-control" style="width: auto;" onchange="document.getElementById('filter-form').submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= (int)$categoryFilter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                        <?= sanitize($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <a href="<?= FRONTEND_URL ?>/student/submit_complaint.php" class="btn btn-primary btn-sm" style="margin-left: auto;">
                <i class="fas fa-plus"></i> Add Complaint
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="complaints-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Summary</th>
                        <th>Photo</th>
                        <th>Location</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($complaints)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 8px;"></i>
                                <p>No matching complaints found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($complaints as $c): ?>
                            <tr>
                                <td>#CMP-<?= str_pad($c['complaint_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-primary);"><?= sanitize($c['title']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= sanitize($c['description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c['image'])): ?>
                                        <img src="<?= BACKEND_URL ?>/uploads/complaints/<?= sanitize($c['image']) ?>" alt="Reference photo" style="width: 40px; height: 40px; border-radius: var(--radius-sm); object-fit: cover; border: 1px solid var(--border); cursor: pointer;" onclick="window.open(this.src)">
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">No photo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($c['building_name']) ?></td>
                                <td><?= sanitize($c['category_name']) ?></td>
                                <td><?= getPriorityBadge($c['priority']) ?></td>
                                <td><?= getStatusBadge($c['status_name']) ?></td>
                                <td><?= formatDate($c['created_at'], 'M d, Y') ?></td>
                                <td style="text-align: right;">
                                    <a href="<?= FRONTEND_URL ?>/student/view_complaint.php?id=<?= $c['complaint_id'] ?>" class="btn btn-secondary btn-sm" title="Track & Info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($c['status_name'] === 'Pending'): ?>
                                        <a href="<?= FRONTEND_URL ?>/student/edit_complaint.php?id=<?= $c['complaint_id'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="handleDelete(<?= $c['complaint_id'] ?>)" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php 
            $paginationUrl = FRONTEND_URL . "/student/my_complaints.php?status=" . urlencode($statusFilter) . "&category=" . urlencode($categoryFilter) . "&search=" . urlencode($searchFilter);
            echo renderPagination($pagination, $paginationUrl);
        ?>
    </div>
</div>

<?php 
$extraScripts = "
<script>
async function handleDelete(id) {
    if (!confirm('Are you sure you want to delete this complaint?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('complaint_id', id);
    
    const res = await ajaxRequest('" . BACKEND_URL . "/student/ajax/complaint_actions.php', 'POST', formData);
    if (res.success) {
        Toast.success('Success', res.message);
        setTimeout(() => location.reload(), 1000);
    } else {
        Toast.error('Failure', res.message);
    }
}
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
