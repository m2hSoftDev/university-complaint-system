<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/includes/auth.php';
require_once __DIR__ . '/../backend/includes/functions.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';

try {
    $buildings = getBuildings($pdo);
} catch (Exception $e) {
    $buildings = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $student_number = sanitize($_POST['student_number'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $semester = sanitize($_POST['semester'] ?? '');
    $building_id = $_POST['building_id'] ?? null;
    $room_no = sanitize($_POST['room_no'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($student_number)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email address is already registered.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ?");
                $stmt->execute([$student_number]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Student ID number is already registered.';
                } else {
                    $pdo->beginTransaction();

                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmtUser = $pdo->prepare(
                        "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')"
                    );
                    $stmtUser->execute([$name, $email, $phone, $hashedPassword]);
                    $userId = $pdo->lastInsertId();

                    $b_id = !empty($building_id) ? (int)$building_id : null;
                    $stmtStudent = $pdo->prepare(
                        "INSERT INTO students (user_id, student_number, department, semester, building_id, room_no) VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stmtStudent->execute([$userId, $student_number, $department, $semester, $b_id, $room_no]);

                    $pdo->commit();

                    $_SESSION['reg_success'] = 'Registration successful! You can now log in.';
                    header('Location: ' . FRONTEND_URL . '/login.php');
                    exit();
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'System error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration — CCMS Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= FRONTEND_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">

    <div class="auth-card auth-card-lg">
        <div class="auth-logo">
            <div class="logo-icon">👨‍🎓</div>
            <h1 class="text-gradient">Student Register</h1>
            <p>Create your CCMS account to submit and track complaints</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="<?= FRONTEND_URL ?>/register.php" method="POST" id="register-form" onsubmit="return validateForm('register-form')">
            <h3 style="font-size: 14px; color: var(--accent-indigo); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
                Personal Details
            </h3>
            
            <div class="form-row-2col">
                <div class="form-group">
                    <label for="name" class="form-label">Full Name <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="text" name="name" id="name" class="form-control" placeholder="Enter your full name" required>
                        <i class="fas fa-user input-group-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email address" required>
                        <i class="fas fa-envelope input-group-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-row-2col">
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-group">
                        <input type="text" name="phone" id="phone" class="form-control" placeholder="Enter your phone number">
                        <i class="fas fa-phone input-group-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="new-password">
                        <i class="fas fa-lock input-group-icon"></i>
                        <i class="fas fa-eye input-group-icon" style="left: auto; right: 14px; cursor: pointer; pointer-events: auto;" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>
            </div>

            <h3 style="font-size: 14px; color: var(--accent-indigo); margin-top: 10px; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
                Academic & Location Information
            </h3>

            <div class="form-row-2col">
                <div class="form-group">
                    <label for="student_number" class="form-label">Student ID / Roll <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="text" name="student_number" id="student_number" class="form-control" placeholder="Enter your student ID / roll number" required>
                        <i class="fas fa-id-card input-group-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="department" class="form-label">Department</label>
                    <div class="input-group">
                        <input type="text" name="department" id="department" class="form-control" placeholder="Enter your department">
                        <i class="fas fa-graduation-cap input-group-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-row-2col">
                <div class="form-group">
                    <label for="semester" class="form-label">Semester</label>
                    <div class="input-group">
                        <input type="text" name="semester" id="semester" class="form-control" placeholder="Enter your semester">
                        <i class="fas fa-calendar-alt input-group-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="building_id" class="form-label">Hostel / Building</label>
                    <div class="input-group">
                        <select name="building_id" id="building_id" class="form-control">
                            <option value="">-- Select Location --</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?= $b['building_id'] ?>"><?= sanitize($b['building_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-building input-group-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="room_no" class="form-label">Room Number / Lab No</label>
                <div class="input-group">
                    <input type="text" name="room_no" id="room_no" class="form-control" placeholder="Enter your room or lab number">
                    <i class="fas fa-door-open input-group-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-full" style="margin-top: 15px;">
                <i class="fas fa-user-plus"></i> Complete Registration
            </button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="<?= FRONTEND_URL ?>/login.php">Login Here</a></p>
        </div>
    </div>

    <script src="<?= FRONTEND_URL ?>/assets/js/main.js"></script>
</body>
</html>
