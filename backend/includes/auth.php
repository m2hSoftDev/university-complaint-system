<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin($role = null) {
    if (!isLoggedIn()) {
        $loginUrl = defined('FRONTEND_URL') ? FRONTEND_URL . '/login.php' : '../login.php';
        header('Location: ' . $loginUrl);
        exit();
    }
    if ($role !== null && $_SESSION['role'] !== $role) {
        redirectToDashboard();
        exit();
    }
}

function redirectToDashboard() {
    if (!isLoggedIn()) {
        $loginUrl = defined('FRONTEND_URL') ? FRONTEND_URL . '/login.php' : '../login.php';
        header('Location: ' . $loginUrl);
        exit();
    }
    $baseUrl = defined('FRONTEND_URL') ? FRONTEND_URL : '../frontend';
    $role = $_SESSION['role'] ?? 'student';
    switch ($role) {
        case 'admin':
            header('Location: ' . $baseUrl . '/admin/dashboard.php');
            break;
        case 'staff':
            header('Location: ' . $baseUrl . '/staff/dashboard.php');
            break;
        default:
            header('Location: ' . $baseUrl . '/student/dashboard.php');
            break;
    }
    exit();
}


function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'user_id'    => $_SESSION['user_id'],
        'name'       => $_SESSION['name'],
        'email'      => $_SESSION['email'],
        'role'       => $_SESSION['role'],
        'student_id' => $_SESSION['student_id'] ?? null,
        'staff_id'   => $_SESSION['staff_id'] ?? null,
    ];
}


function setUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
}


function getUnreadNotificationCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getNotifications($pdo, $userId, $limit = 10) {
    $limit = (int)$limit;
    $stmt = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}


function markNotificationRead($pdo, $notificationId, $userId) {
    $stmt = $pdo->prepare(
        "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?"
    );
    $stmt->execute([$notificationId, $userId]);
}


function markAllNotificationsRead($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}
