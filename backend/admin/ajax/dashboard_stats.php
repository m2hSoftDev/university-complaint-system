<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

try {
    $statusData = $pdo->query(
        "SELECT cs.status_name, COUNT(c.complaint_id) as count 
         FROM complaint_status cs
         LEFT JOIN complaints c ON cs.status_id = c.status_id
         GROUP BY cs.status_id"
    )->fetchAll();

    $categoryData = $pdo->query(
        "SELECT cc.category_name, COUNT(c.complaint_id) as count 
         FROM complaint_categories cc
         LEFT JOIN complaints c ON cc.category_id = c.category_id
         GROUP BY cc.category_id"
    )->fetchAll();

    $locationData = $pdo->query(
        "SELECT b.building_name, COUNT(c.complaint_id) as count 
         FROM buildings b
         LEFT JOIN complaints c ON b.building_id = c.building_id
         GROUP BY b.building_id"
    )->fetchAll();

    jsonSuccess('Dashboard analytics fetched successfully', [
        'statuses' => $statusData,
        'categories' => $categoryData,
        'locations' => $locationData
    ]);

} catch (Exception $e) {
    jsonError('Failed to fetch dashboard statistics: ' . $e->getMessage());
}
