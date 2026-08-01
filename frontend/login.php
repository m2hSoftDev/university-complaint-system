<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/includes/auth.php';
require_once __DIR__ . '/../backend/includes/functions.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$success = '';

if (isset($_SESSION['reg_success'])) {
    $success = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact administrator.';
            } else {
                setUserSession($user);
                
                if ($user['role'] === 'student') {
                    $stmtStud = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
                    $stmtStud->execute([$user['user_id']]);
                    $_SESSION['student_id'] = $stmtStud->fetchColumn();
                } elseif ($user['role'] === 'staff') {
                    $stmtStaff = $pdo->prepare("SELECT staff_id FROM maintenance_staff WHERE user_id = ?");
                    $stmtStaff->execute([$user['user_id']]);
                    $_SESSION['staff_id'] = $stmtStaff->fetchColumn();
                }
                
                redirectToDashboard();
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — CCMS Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= FRONTEND_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo-icon">🏛️</div>
            <h1 class="text-gradient">CCMS Login</h1>
            <p>Campus Complaint & Maintenance Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <form action="<?= FRONTEND_URL ?>/login.php" method="POST" id="login-form" class="auth-form" onsubmit="return validateForm('login-form')">
            <div class="form-group">
                <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                <div class="input-group">
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email address" required autocomplete="email">
                    <i class="fas fa-envelope input-group-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password <span class="required">*</span></label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                    <i class="fas fa-lock input-group-icon"></i>
                    <i class="fas fa-eye input-group-icon" style="left: auto; right: 14px; cursor: pointer; pointer-events: auto;" onclick="togglePassword('password', this)"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-full">
                <i class="fas fa-sign-in-alt"></i> Access Portal
            </button>
        </form>

        <div class="auth-footer">
            <p>Are you a student? <a href="<?= FRONTEND_URL ?>/register.php">Register Here</a></p>
        </div>
    </div>

    <script src="<?= FRONTEND_URL ?>/assets/js/main.js"></script>
</body>
</html>
