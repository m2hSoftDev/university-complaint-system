<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

$action = sanitize($_POST['action'] ?? '');

try {
    if ($action === 'add') {
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
            jsonError('Required fields are missing.');
        }

        // Email checks
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) jsonError('Email address is already in use.');

        // Student ID check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ?");
        $stmt->execute([$student_number]);
        if ($stmt->fetchColumn() > 0) jsonError('Student ID is already registered.');

        $pdo->beginTransaction();

        $hashedPass = password_hash($password, PASSWORD_BCRYPT);
        $stmtUser = $pdo->prepare(
            "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')"
        );
        $stmtUser->execute([$name, $email, $phone, $hashedPass]);
        $userId = $pdo->lastInsertId();

        $b_id = !empty($building_id) ? (int)$building_id : null;
        $stmtStud = $pdo->prepare(
            "INSERT INTO students (user_id, student_number, department, semester, building_id, room_no) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtStud->execute([$userId, $student_number, $department, $semester, $b_id, $room_no]);

        $pdo->commit();
        jsonSuccess('Student record added successfully.');

    } elseif ($action === 'edit') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $student_number = sanitize($_POST['student_number'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $semester = sanitize($_POST['semester'] ?? '');
        $building_id = $_POST['building_id'] ?? null;
        $room_no = sanitize($_POST['room_no'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');

        if ($userId <= 0 || empty($name) || empty($email) || empty($student_number)) {
            jsonError('Required fields are missing.');
        }

        // Email check ignoring current user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) jsonError('Email address is already in use.');

        // Student ID check ignoring current student
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ? AND user_id != ?");
        $stmt->execute([$student_number, $userId]);
        if ($stmt->fetchColumn() > 0) jsonError('Student ID is already registered.');

        $pdo->beginTransaction();

        // Update core user attributes
        if (!empty($password)) {
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare(
                "UPDATE users SET name = ?, email = ?, phone = ?, password = ?, status = ? WHERE user_id = ?"
            );
            $stmtUser->execute([$name, $email, $phone, $hashedPass, $status, $userId]);
        } else {
            $stmtUser = $pdo->prepare(
                "UPDATE users SET name = ?, email = ?, phone = ?, status = ? WHERE user_id = ?"
            );
            $stmtUser->execute([$name, $email, $phone, $status, $userId]);
        }

        // Update student attributes
        $b_id = !empty($building_id) ? (int)$building_id : null;
        $stmtStud = $pdo->prepare(
            "UPDATE students SET student_number = ?, department = ?, semester = ?, building_id = ?, room_no = ? WHERE user_id = ?"
        );
        $stmtStud->execute([$student_number, $department, $semester, $b_id, $room_no, $userId]);

        $pdo->commit();
        jsonSuccess('Student record updated successfully.');

    } elseif ($action === 'delete') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if ($userId <= 0) jsonError('Invalid User ID.');

        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$userId]);
        jsonSuccess('Student record deleted successfully.');

    } else {
        jsonError('Unsupported action.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('CRUD error: ' . $e->getMessage());
}
