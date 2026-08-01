<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('staff');

$staffId = $_SESSION['staff_id'];
$error = '';

$searchFilter = sanitize($_GET['search'] ?? '');

$queryParams = [$staffId];
$whereClause = "WHERE a.staff_id = ? AND a.assignment_status = 'Completed'";

if (!empty($searchFilter)) {
    $whereClause .= " AND (c.title LIKE ? OR c.description LIKE ? OR a.repair_notes LIKE ?)";
    $queryParams[] = "%$searchFilter%";
    $queryParams[] = "%$searchFilter%";
    $queryParams[] = "%$searchFilter%";
}

try {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM assignments a
         JOIN complaints c ON a.complaint_id = c.complaint_id
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
        "SELECT a.*, c.title, c.description, c.priority, cc.category_name, b.building_name, 
                f.rating, f.comments as feedback_comments
         FROM assignments a
         JOIN complaints c ON a.complaint_id = c.complaint_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
         $whereClause
         ORDER BY a.completed_date DESC
         LIMIT {$offset}, {$perPage}"
    );
    $stmt->execute($queryParams);
    $completedTasks = $stmt->fetchAll();
} catch (Exception $e) {
    $completedTasks = [];
    $error = 'Failed to load completed task history.';
}

$pageTitle = "Work History";
$currentPage = "completed";
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="card mb-lg stagger-in">
    <div class="card-body">
        <form method="GET" action="<?= FRONTEND_URL ?>/staff/completed_tasks.php" class="table-filters">
            <div class="filter-search input-group" style="max-width: 400px;">
                <input type="text" name="search" class="form-control" placeholder="Search job details or repair action..." value="<?= sanitize($searchFilter) ?>">
                <i class="fas fa-search input-group-icon"></i>
            </div>
            
            <?php if (!empty($searchFilter)): ?>
                <a href="<?= FRONTEND_URL ?>/staff/completed_tasks.php" class="btn btn-outline btn-sm">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card stagger-in">
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Ref</th>
                        <th>Complaint Detail</th>
                        <th>Location</th>
                        <th>Finished Date</th>
                        <th>Action Logged</th>
                        <th>Repair Photo</th>
                        <th>Feedback / Stars</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completedTasks)): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                <i class="fas fa-archive"></i>
                                <p>No completed jobs cataloged in your history archive.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($completedTasks as $task): ?>
                            <tr>
                                <td>#JOB-<?= str_pad($task['assignment_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="font-semibold text-primary"><?= sanitize($task['title']) ?></div>
                                    <span class="badge badge-secondary"><?= sanitize($task['category_name']) ?></span>
                                </td>
                                <td><?= sanitize($task['building_name']) ?></td>
                                <td><?= formatDateShort($task['completed_date']) ?></td>
                                <td>
                                    <div class="text-sm text-truncate" style="max-width: 250px;" title="<?= sanitize($task['repair_notes']) ?>">
                                        <?= sanitize($task['repair_notes']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($task['repair_image'])): ?>
                                        <img src="<?= BACKEND_URL ?>/uploads/repairs/<?= sanitize($task['repair_image']) ?>" alt="Resolution" style="width: 40px; height: 40px; border-radius: var(--radius-sm); object-fit: cover; border: 1px solid var(--border); cursor: pointer;" onclick="window.open(this.src)">
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($task['rating']): ?>
                                        <div class="stars-display">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star star <?= $i <= $task['rating'] ? 'filled' : '' ?>" style="font-size: 11px;"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if (!empty($task['feedback_comments'])): ?>
                                            <div class="text-muted text-sm text-truncate" style="max-width: 150px; font-style: italic; margin-top: 2px;" title="<?= sanitize($task['feedback_comments']) ?>">
                                                "<?= sanitize($task['feedback_comments']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted text-sm">No rating yet</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php 
            $paginationUrl = FRONTEND_URL . "/staff/completed_tasks.php?search=" . urlencode($searchFilter);
            echo renderPagination($pagination, $paginationUrl);
        ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
