<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

try {
    // 1. Core Metrics
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

    // 2. Fetch Category distribution
    $categoryData = $pdo->query(
        "SELECT cc.category_name, COUNT(c.complaint_id) as count 
         FROM complaint_categories cc
         LEFT JOIN complaints c ON cc.category_id = c.category_id
         GROUP BY cc.category_id 
         ORDER BY count DESC"
    )->fetchAll();

    // 3. Fetch Building distribution
    $buildingData = $pdo->query(
        "SELECT b.building_name, COUNT(c.complaint_id) as count 
         FROM buildings b
         LEFT JOIN complaints c ON b.building_id = c.building_id
         GROUP BY b.building_id 
         ORDER BY count DESC"
    )->fetchAll();

    // 4. Staff Performance Index (tasks completed, average rating)
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

} catch (Exception $e) {
    $totalComplaints = $pendingComplaints = $inProgressCount = $resolvedComplaints = 0;
    $categoryData = $buildingData = $staffPerf = [];
}

// Convert PHP arrays to JSON for ChartJS consumption
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

<!-- ─── Admin Metrics Cards ─── -->
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

<!-- ─── Charts Display Section ─── -->
<div class="charts-grid stagger-in mt-lg">
    <!-- Chart 1: Category Distribution -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie text-gradient"></i> By Complaint Category</h3>
        </div>
        <div class="chart-wrapper">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

    <!-- Chart 2: Building Distribution -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar text-gradient"></i> By Campus Location</h3>
        </div>
        <div class="chart-wrapper">
            <canvas id="buildingChart"></canvas>
        </div>
    </div>
</div>

<!-- ─── Performance Table & Quick Links ─── -->
<div class="grid-3 stagger-in mt-lg" style="grid-template-columns: 2fr 1fr;">
    <!-- Staff Performance -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-shield text-gradient"></i> Staff Performance Rating</h3>
            <a href="<?= BASE_URL ?>/admin/manage_staff.php" class="btn btn-outline btn-sm">Manage Staff</a>
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

    <!-- Quick Operations Control -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-cogs text-gradient"></i> Directory Controls</h3>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; gap: 16px;">
            <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-primary w-full">
                <i class="fas fa-clipboard-list"></i> Ticket Dispatch Control
            </a>
            <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-success w-full">
                <i class="fas fa-chart-line"></i> Statistics & Reports
            </a>
            <div style="border-top: 1px solid var(--border); padding-top: 16px; margin-top: 8px;">
                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; letter-spacing: 0.05em;">
                    System Overview
                </h4>
                <ul style="display: flex; flex-direction: column; gap: 10px; font-size: 13px; color: var(--text-secondary);">
                    <li class="flex items-center justify-between">
                        <span>Active categories:</span>
                        <strong><?= count($chartCategories) ?></strong>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Campus locations:</span>
                        <strong><?= count($chartBuildings) ?></strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php 
$extraScripts = "
<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
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
