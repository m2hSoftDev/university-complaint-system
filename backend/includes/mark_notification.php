<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    jsonError('Unauthorized', 401);
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all'])) {
        markAllNotificationsRead($pdo, $userId);
        jsonSuccess('All notifications marked as read');
    } elseif (isset($_POST['notification_id'])) {
        markNotificationRead($pdo, (int)$_POST['notification_id'], $userId);
        jsonSuccess('Notification marked as read');
    } else {
        jsonError('Invalid request');
    }
} else {
    jsonError('Method not allowed', 405);
}
