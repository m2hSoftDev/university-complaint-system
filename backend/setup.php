<?php
require_once __DIR__ . '/config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCMS Backend Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= FRONTEND_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">
<div class="auth-card" style="max-width: 560px;">
    <div class="auth-logo">
        <div class="logo-icon">⚙️</div>
        <h1>CCMS Backend Setup</h1>
        <p>Initial system configuration & folder verification</p>
    </div>

    <?php
    $messages = [];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Update admin password
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@campus.edu'");
            $stmt->execute([$adminPassword]);
            $messages[] = ' Admin password set to <code>admin123</code>';
        } catch (Exception $e) {
            $errors[] = ' Failed to update admin password: ' . $e->getMessage();
        }

        // 2. Create upload directories
        $dirs = [
            __DIR__ . '/uploads/complaints',
            __DIR__ . '/uploads/repairs'
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0777, true)) {
                    $messages[] = ' Created directory: ' . basename(dirname($dir)) . '/' . basename($dir);
                } else {
                    $errors[] = '
                     Failed to create: ' . $dir;
                }
            } else {
                $messages[] = ' Directory exists: ' . basename(dirname($dir)) . '/' . basename($dir);
            }
        }

        // 3. Verify database tables
        $tables = ['users', 'students', 'maintenance_staff', 'complaints', 'complaint_categories', 
                   'buildings', 'complaint_status', 'assignments', 'complaint_updates', 'feedback', 
                   'notifications', 'admins'];
        $existingTables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $existingTables[] = $row[0];
        }

        foreach ($tables as $table) {
            if (in_array($table, $existingTables)) {
                $messages[] = ' Table <code>' . $table . '</code> exists';
            } else {
                $errors[] = ' Missing table: <code>' . $table . '</code>';
            }
        }

        // 4. Verify seed data
        $catCount = $pdo->query("SELECT COUNT(*) FROM complaint_categories")->fetchColumn();
        $bldCount = $pdo->query("SELECT COUNT(*) FROM buildings")->fetchColumn();
        $statusCount = $pdo->query("SELECT COUNT(*) FROM complaint_status")->fetchColumn();

        $messages[] = " Categories: $catCount | Buildings: $bldCount | Statuses: $statusCount";
    }
    ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?= count($errors) ?> issue(s) found
    </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
    <div class="card" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto;">
        <div class="card-body">
            <?php foreach (array_merge($messages, $errors) as $msg): ?>
            <div style="padding: 6px 0; font-size: 13px; border-bottom: 1px solid var(--border);">
                <?= $msg ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="text-align: center;">
        <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 14px;">
            <?php if (empty($errors)): ?>
                <i class="fas fa-check-circle text-success"></i> Setup complete! You can now login.
            <?php else: ?>
                <i class="fas fa-exclamation-triangle text-warning"></i> Fix the issues above and run setup again.
            <?php endif; ?>
        </p>
        <a href="<?= FRONTEND_URL ?>/login.php" class="btn btn-primary btn-lg">
            <i class="fas fa-sign-in-alt"></i> Go to Login
        </a>
    </div>

    <?php else: ?>
    <div style="margin-bottom: 24px;">
        <h4 style="margin-bottom: 12px;">This script will:</h4>
        <ul style="list-style: none; padding: 0;">
            <li style="padding: 8px 0; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-key" style="color: var(--accent-indigo); width: 20px;"></i> Set admin password to <code style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px;">admin123</code>
            </li>
            <li style="padding: 8px 0; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-folder-plus" style="color: var(--accent-cyan); width: 20px;"></i> Create upload directories
            </li>
            <li style="padding: 8px 0; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-database" style="color: var(--success); width: 20px;"></i> Verify database tables & seed data
            </li>
        </ul>
    </div>
    
    <form method="POST">
        <button type="submit" class="btn btn-primary btn-lg w-full">
            <i class="fas fa-rocket"></i> Run Setup
        </button>
    </form>

    <div class="auth-footer" style="margin-top: 24px;">
        <p><strong>Prerequisites:</strong></p>
        <p style="margin-top: 8px;">1. Import <code>campus_complaint_system.sql</code> in phpMyAdmin</p>
        <p>2. Start Apache & MySQL in XAMPP</p>
    </div>
    <?php endif; ?>
</div>

<script src="<?= FRONTEND_URL ?>/assets/js/main.js"></script>
</body>
</html>
