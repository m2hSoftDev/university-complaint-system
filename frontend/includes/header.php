<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($currentPage)) $currentPage = '';

$user = getCurrentUser();
$notifCount = isLoggedIn() ? getUnreadNotificationCount($pdo, $user['user_id']) : 0;
$notifications = isLoggedIn() ? getNotifications($pdo, $user['user_id'], 8) : [];
$userInitials = '';
if ($user) {
    $parts = explode(' ', $user['name']);
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <meta name="backend-url" content="<?= BACKEND_URL ?>">
    <meta name="frontend-url" content="<?= FRONTEND_URL ?>">
    <title><?= sanitize($pageTitle) ?> — CCMS Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= FRONTEND_URL ?>/assets/css/style.css">
</head>
<body>

<div class="app-container">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon">🏛️</div>
            <h2 class="text-gradient">CCMS</h2>
        </div>

        <nav class="sidebar-menu">
            <?php if ($user['role'] === 'admin'): ?>
            <div class="menu-category">Main Menu</div>
            <a href="<?= FRONTEND_URL ?>/admin/dashboard.php" class="menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="<?= FRONTEND_URL ?>/admin/complaints.php" class="menu-item <?= $currentPage === 'complaints' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list"></i> Complaints
            </a>

            <div class="menu-category">Management</div>
            <a href="<?= FRONTEND_URL ?>/admin/manage_students.php" class="menu-item <?= $currentPage === 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i> Students
            </a>
            <a href="<?= FRONTEND_URL ?>/admin/manage_staff.php" class="menu-item <?= $currentPage === 'staff' ? 'active' : '' ?>">
                <i class="fas fa-hard-hat"></i> Technicians
            </a>
            <a href="<?= FRONTEND_URL ?>/admin/manage_categories.php" class="menu-item <?= $currentPage === 'categories' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="<?= FRONTEND_URL ?>/admin/manage_locations.php" class="menu-item <?= $currentPage === 'locations' ? 'active' : '' ?>">
                <i class="fas fa-map-marker-alt"></i> Locations
            </a>

            <div class="menu-category">Analytics</div>
            <a href="<?= FRONTEND_URL ?>/admin/reports.php" class="menu-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

            <?php elseif ($user['role'] === 'student'): ?>
            <div class="menu-category">Student Portal</div>
            <a href="<?= FRONTEND_URL ?>/student/dashboard.php" class="menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="<?= FRONTEND_URL ?>/student/submit_complaint.php" class="menu-item <?= $currentPage === 'submit' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i> File Complaint
            </a>
            <a href="<?= FRONTEND_URL ?>/student/my_complaints.php" class="menu-item <?= $currentPage === 'complaints' ? 'active' : '' ?>">
                <i class="fas fa-list-alt"></i> Track Complaints
            </a>

            <?php elseif ($user['role'] === 'staff'): ?>
            <div class="menu-category">Technician Desk</div>
            <a href="<?= FRONTEND_URL ?>/staff/dashboard.php" class="menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="<?= FRONTEND_URL ?>/staff/assigned_tasks.php" class="menu-item <?= $currentPage === 'assigned' ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i> Active Tasks
            </a>
            <a href="<?= FRONTEND_URL ?>/staff/completed_tasks.php" class="menu-item <?= $currentPage === 'completed' ? 'active' : '' ?>">
                <i class="fas fa-check-double"></i> Completed Jobs
            </a>
            <?php endif; ?>

            <div class="menu-category" style="margin-top: auto;">Account</div>
            <a href="<?= FRONTEND_URL ?>/logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="avatar"><?= $userInitials ?></div>
                <div class="user-info">
                    <span class="user-name"><?= sanitize($user['name']) ?></span>
                    <span class="user-role"><?= sanitize($user['role']) ?></span>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?= sanitize($pageTitle) ?></h1>
            </div>

            <div class="topbar-right">
                <div class="notification-dropdown">
                    <button class="notification-btn" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="notification-badge"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">
                            <h4>Notifications</h4>
                            <?php if ($notifCount > 0): ?>
                            <button class="btn btn-sm btn-secondary" onclick="markAllNotificationsRead()">Mark read</button>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-body">
                            <?php if (empty($notifications)): ?>
                            <div style="padding: 30px 20px; text-align: center; color: var(--text-muted);">
                                <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 8px;"></i>
                                <p style="font-size: 13px;">No new notifications</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" onclick="markNotificationRead(<?= $notif['notification_id'] ?>)">
                                <div class="notification-title"><?= sanitize($notif['title']) ?></div>
                                <div class="notification-msg"><?= sanitize($notif['message']) ?></div>
                                <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="content-area">
