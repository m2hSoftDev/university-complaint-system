<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

$action = sanitize($_POST['action'] ?? '');

try {
    if ($action === 'add') {
        $category_name = sanitize($_POST['category_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($category_name)) jsonError('Category name is required.');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaint_categories WHERE category_name = ?");
        $stmt->execute([$category_name]);
        if ($stmt->fetchColumn() > 0) jsonError('Category name already exists.');

        $stmt = $pdo->prepare("INSERT INTO complaint_categories (category_name, description) VALUES (?, ?)");
        $stmt->execute([$category_name, $description]);
        jsonSuccess('Category added successfully.');

    } elseif ($action === 'edit') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $category_name = sanitize($_POST['category_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($categoryId <= 0 || empty($category_name)) jsonError('Required fields are missing.');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaint_categories WHERE category_name = ? AND category_id != ?");
        $stmt->execute([$category_name, $categoryId]);
        if ($stmt->fetchColumn() > 0) jsonError('Category name already exists.');

        $stmt = $pdo->prepare("UPDATE complaint_categories SET category_name = ?, description = ? WHERE category_id = ?");
        $stmt->execute([$category_name, $description, $categoryId]);
        jsonSuccess('Category updated successfully.');

    } elseif ($action === 'delete') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        if ($categoryId <= 0) jsonError('Invalid Category ID.');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        if ($stmt->fetchColumn() > 0) {
            jsonError('Cannot delete category containing existing complaint records.');
        }

        $stmt = $pdo->prepare("DELETE FROM complaint_categories WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        jsonSuccess('Category deleted successfully.');

    } else {
        jsonError('Unsupported action.');
    }
} catch (Exception $e) {
    jsonError('Failed to complete category CRUD operation: ' . $e->getMessage());
}
