<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

$startDate = sanitize($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = sanitize($_GET['end_date'] ?? date('Y-m-d'));
$categoryFilter = sanitize($_GET['category'] ?? '');
$buildingFilter = sanitize($_GET['building'] ?? '');

$queryParams = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
$whereClause = "WHERE c.created_at BETWEEN ? AND ?";

if (!empty($categoryFilter)) {
    $whereClause .= " AND c.category_id = ?";
    $queryParams[] = (int)$categoryFilter;
}

if (!empty($buildingFilter)) {
    $whereClause .= " AND c.building_id = ?";
    $queryParams[] = (int)$buildingFilter;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt = $pdo->prepare(
            "SELECT c.complaint_id, c.title, c.priority, cs.status_name, cc.category_name, 
                    b.building_name, u.name as student_name, c.created_at, c.resolved_at
             FROM complaints c
             JOIN complaint_status cs ON c.status_id = cs.status_id
             JOIN complaint_categories cc ON c.category_id = cc.category_id
             JOIN buildings b ON c.building_id = b.building_id
             JOIN students s ON c.student_id = s.student_id
             JOIN users u ON s.user_id = u.user_id
             $whereClause
             ORDER BY c.created_at DESC"
        );
        $stmt->execute($queryParams);
        $data = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ccms_report_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');

        // Column Headers
        fputcsv($output, ['Ref ID', 'Title Summary', 'Priority', 'Status', 'Category', 'Location', 'Student Name', 'Submitted Date', 'Resolved Date']);

        foreach ($data as $row) {
            fputcsv($output, [
                '#CMP-' . str_pad($row['complaint_id'], 4, '0', STR_PAD_LEFT),
                $row['title'],
                $row['priority'],
                $row['status_name'],
                $row['category_name'],
                $row['building_name'],
                $row['student_name'],
                $row['created_at'],
                $row['resolved_at'] ?: '—'
            ]);
        }
        fclose($output);
        exit();
    } catch (Exception $e) {
        die('Export failed: ' . $e->getMessage());
    }
}


try {
    // 1. Total Complaints in selection
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c $whereClause");
    $stmt->execute($queryParams);
    $totalReported = $stmt->fetchColumn();

    // 2. Status counts
    $stmt = $pdo->prepare(
        "SELECT cs.status_name, COUNT(c.complaint_id) as count
         FROM complaint_status cs
         LEFT JOIN complaints c ON cs.status_id = c.status_id AND c.created_at BETWEEN ? AND ?
         GROUP BY cs.status_id"
    );
    // Bind dates to the subquery conditions
    $stmt->execute([$queryParams[0], $queryParams[1]]);
    $statusStats = $stmt->fetchAll();

    // 3. Average Resolution Time (in hours)
    $stmt = $pdo->prepare(
        "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at)), 1)
         FROM complaints c
         $whereClause AND c.resolved_at IS NOT NULL"
    );
    // Strip pagination logic to execute core filter
    $stmt->execute(array_slice($queryParams, 0, count($queryParams)));
    $avgResolutionTime = $stmt->fetchColumn() ?: '0.0';

    // 4. Feedback metrics
    $stmt = $pdo->prepare(
        "SELECT ROUND(AVG(f.rating), 1) as avg_rating, COUNT(f.feedback_id) as total_ratings
         FROM feedback f
         JOIN complaints c ON f.complaint_id = c.complaint_id
         $whereClause"
    );
    $stmt->execute(array_slice($queryParams, 0, count($queryParams)));
    $feedbackStats = $stmt->fetch();

    // 5. Build full table list
    $stmt = $pdo->prepare(
        "SELECT c.*, cs.status_name, cc.category_name, b.building_name, u.name as student_name
         FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         JOIN complaint_categories cc ON c.category_id = cc.category_id
         JOIN buildings b ON c.building_id = b.building_id
         JOIN students s ON c.student_id = s.student_id
         JOIN users u ON s.user_id = u.user_id
         $whereClause
         ORDER BY c.created_at DESC
         LIMIT 50"
    );
    $stmt->execute(array_slice($queryParams, 0, count($queryParams)));
    $records = $stmt->fetchAll();

} catch (Exception $e) {
    $totalReported = $feedbackStats = 0;
    $avgResolutionTime = '0.0';
    $statusStats = $records = [];
}

// Fetch lists for filter inputs
try {
    $categories = getCategories($pdo);
    $buildings = getBuildings($pdo);
} catch (Exception $e) {
    $categories = $buildings = [];
}

$pageTitle = "Reports & Analytics";
$currentPage = "reports";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Date Range & Category filter bar -->
<div class="card mb-lg stagger-in">
    <div class="card-body">
        <form method="GET" action="<?= FRONTEND_URL ?>/admin/reports.php" class="table-filters" style="gap: 16px;">
            <div class="form-group mb-0">
                <label class="form-label" style="margin-bottom: 4px; font-size: 10px;">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
            </div>
            
            <div class="form-group mb-0">
                <label class="form-label" style="margin-bottom: 4px; font-size: 10px;">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
            </div>

            <div class="form-group mb-0">
                <label class="form-label" style="margin-bottom: 4px; font-size: 10px;">Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= (int)$categoryFilter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-0">
                <label class="form-label" style="margin-bottom: 4px; font-size: 10px;">Building</label>
                <select name="building" class="form-control">
                    <option value="">All Buildings</option>
                    <?php foreach ($buildings as $bld): ?>
                        <option value="<?= $bld['building_id'] ?>" <?= (int)$buildingFilter === (int)$bld['building_id'] ? 'selected' : '' ?>>
                            <?= sanitize($bld['building_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-sm" style="align-self: flex-end; margin-top: auto;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync-alt"></i> Update Report
                </button>
                <a href="<?= FRONTEND_URL ?>/admin/reports.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&category=<?= $categoryFilter ?>&building=<?= $buildingFilter ?>&export=csv" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Metrics summary cards -->
<div class="stats-grid stagger-in">
    <div class="stat-card stat-primary">
        <div class="stat-card-top">
            <div class="stat-label">Total Logged</div>
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
        </div>
        <div class="stat-value" data-count="<?= $totalReported ?>">0</div>
        <div class="stat-change">In selected timeframe</div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-card-top">
            <div class="stat-label">Avg Resolve Time</div>
            <div class="stat-icon"><i class="fas fa-tachometer-alt"></i></div>
        </div>
        <div class="stat-value"><?= $avgResolutionTime ?> hr</div>
        <div class="stat-change">Hours from submit to resolution</div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-card-top">
            <div class="stat-label">Satisfaction Index</div>
            <div class="stat-icon"><i class="fas fa-smile"></i></div>
        </div>
        <div class="stat-value"><?= $feedbackStats['avg_rating'] ?: '0.0' ?> / 5</div>
        <div class="stat-change">Based on <?= $feedbackStats['total_ratings'] ?: '0' ?> customer ratings</div>
    </div>
</div>

<!-- Detail logs list -->
<div class="card stagger-in mt-lg">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice text-gradient"></i> Report Log Output (Showing recent 50 records)</h3>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ref Code</th>
                        <th>Summary Title</th>
                        <th>Student Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Filed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" class="table-empty">
                                <i class="fas fa-folder-open"></i>
                                <p>No complaint dispatches logged in this timeframe matching filters.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>#CMP-<?= str_pad($row['complaint_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><strong class="text-primary"><?= sanitize($row['title']) ?></strong></td>
                                <td><?= sanitize($row['student_name']) ?></td>
                                <td><?= sanitize($row['category_name']) ?></td>
                                <td><?= sanitize($row['building_name']) ?></td>
                                <td><?= getPriorityBadge($row['priority']) ?></td>
                                <td><?= getStatusBadge($row['status_name']) ?></td>
                                <td><?= formatDateShort($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
