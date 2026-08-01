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
        $employee_id = sanitize($_POST['employee_id'] ?? '');
        $designation = sanitize($_POST['designation'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $availability = sanitize($_POST['availability'] ?? 'available');

        if (empty($name) || empty($email) || empty($password) || empty($employee_id)) {
            jsonError('Required fields are missing.');
        }

        // Email check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) jsonError('Email address is already in use.');

        // Employee ID check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_staff WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        if ($stmt->fetchColumn() > 0) jsonError('Employee ID is already registered.');

        $pdo->beginTransaction();

        $hashedPass = password_hash($password, PASSWORD_BCRYPT);
        $stmtUser = $pdo->prepare(
            "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'staff', 'active')"
        );
        $stmtUser->execute([$name, $email, $phone, $hashedPass]);
        $userId = $pdo->lastInsertId();

        $stmtStaff = $pdo->prepare(
            "INSERT INTO maintenance_staff (user_id, employee_id, designation, specialization, phone, availability) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtStaff->execute([$userId, $employee_id, $designation, $specialization, $phone, $availability]);

        $pdo->commit();
        jsonSuccess('Staff record added successfully.');

    } elseif ($action === 'edit') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $employee_id = sanitize($_POST['employee_id'] ?? '');
        $designation = sanitize($_POST['designation'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $availability = sanitize($_POST['availability'] ?? 'available');
        $status = sanitize($_POST['status'] ?? 'active');

        if ($userId <= 0 || empty($name) || empty($email) || empty($employee_id)) {
            jsonError('Required fields are missing.');
        }

        // Email check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) jsonError('Email address is already in use.');

        // Employee ID check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_staff WHERE employee_id = ? AND user_id != ?");
        $stmt->execute([$employee_id, $userId]);
        if ($stmt->fetchColumn() > 0) jsonError('Employee ID is already registered.');

        $pdo->beginTransaction();

        // Update core user
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

        // Update staff details
        $stmtStaff = $pdo->prepare(
            "UPDATE maintenance_staff 
             SET employee_id = ?, designation = ?, specialization = ?, phone = ?, availability = ? 
             WHERE user_id = ?"
        );
        $stmtStaff->execute([$employee_id, $designation, $specialization, $phone, $availability, $userId]);

        $pdo->commit();
        jsonSuccess('Staff record updated successfully.');

    } elseif ($action === 'delete') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if ($userId <= 0) jsonError('Invalid User ID.');

        // Fetch staff_id to reset assignments
        $stmtStaff = $pdo->prepare("SELECT staff_id FROM maintenance_staff WHERE user_id = ?");
        $stmtStaff->execute([$userId]);
        $staffId = $stmtStaff->fetchColumn();

        if ($staffId) {
            $pdo->beginTransaction();
            // Reset active assignments back to unassigned status
            $stmtReset = $pdo->prepare(
                "UPDATE complaints c
                 JOIN assignments a ON c.complaint_id = a.complaint_id
                 SET c.status_id = 1 
                 WHERE a.staff_id = ? AND a.assignment_status IN ('Assigned', 'Accepted')"
            );
            $stmtReset->execute([$staffId]);

            // Deleting user cascades automatically to delete maintenance_staff record
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'staff'");
            $stmtDelete->execute([$userId]);

            $pdo->commit();
            jsonSuccess('Staff record deleted successfully.');
        } else {
            jsonError('Staff representative profile not found.');
        }

    } else {
        jsonError('Unsupported action.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('CRUD error: ' . $e->getMessage());
}
