<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

$action = sanitize($_POST['action'] ?? '');

try {
    if ($action === 'add') {
        $building_name = sanitize($_POST['building_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($building_name)) jsonError('Building name is required.');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM buildings WHERE building_name = ?");
        $stmt->execute([$building_name]);
        if ($stmt->fetchColumn() > 0) jsonError('Building location name already exists.');

        $stmt = $pdo->prepare("INSERT INTO buildings (building_name, description) VALUES (?, ?)");
        $stmt->execute([$building_name, $description]);
        jsonSuccess('Building location added successfully.');

    } elseif ($action === 'edit') {
        $buildingId = isset($_POST['building_id']) ? (int)$_POST['building_id'] : 0;
        $building_name = sanitize($_POST['building_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($buildingId <= 0 || empty($building_name)) jsonError('Required fields are missing.');

        // Uniqueness check ignoring current building
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM buildings WHERE building_name = ? AND building_id != ?");
        $stmt->execute([$building_name, $buildingId]);
        if ($stmt->fetchColumn() > 0) jsonError('Building location name already exists.');

        $stmt = $pdo->prepare("UPDATE buildings SET building_name = ?, description = ? WHERE building_id = ?");
        $stmt->execute([$building_name, $description, $buildingId]);
        jsonSuccess('Building location updated successfully.');

    } elseif ($action === 'delete') {
        $buildingId = isset($_POST['building_id']) ? (int)$_POST['building_id'] : 0;
        if ($buildingId <= 0) jsonError('Invalid Building ID.');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE building_id = ?");
        $stmt->execute([$buildingId]);
        if ($stmt->fetchColumn() > 0) {
            jsonError('Cannot delete building containing existing complaint records.');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE building_id = ?");
        $stmt->execute([$buildingId]);
        if ($stmt->fetchColumn() > 0) {
            jsonError('Cannot delete building while students are registered under it.');
        }

        $stmt = $pdo->prepare("DELETE FROM buildings WHERE building_id = ?");
        $stmt->execute([$buildingId]);
        jsonSuccess('Building location deleted successfully.');

    } else {
        jsonError('Unsupported action.');
    }
} catch (Exception $e) {
    jsonError('Failed to complete location CRUD operation: ' . $e->getMessage());
}
