<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/includes/auth.php';

session_destroy();
header('Location: ' . FRONTEND_URL . '/login.php');
exit();
