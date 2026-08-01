<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/includes/auth.php';

if (isLoggedIn()) {
    redirectToDashboard();
} else {
    header('Location: ' . FRONTEND_URL . '/login.php');
    exit();
}
