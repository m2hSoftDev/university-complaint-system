<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

$action = sanitize($_POST['action'] ?? '');
$complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;

if ($complaintId <= 0) {
    jsonError('Invalid Complaint ID.');
}

try {
    if ($action === 'priority') {
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        
        if (!in_array($priority, ['Low', 'Medium', 'High'])) {
            jsonError('Invalid priority level.');
        }

        $stmt = $pdo->prepare("UPDATE complaints SET priority = ? WHERE complaint_id = ?");
        $stmt->execute([$priority, $complaintId]);
        jsonSuccess('Priority updated successfully to ' . $priority);

    } elseif ($action === 'status') {
        $statusId = isset($_POST['status_id']) ? (int)$_POST['status_id'] : 0;
        
        if ($statusId <= 0) {
            jsonError('Invalid status ID selection.');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaint_status WHERE status_id = ?");
        $stmt->execute([$statusId]);
        if ($stmt->fetchColumn() <= 0) {
            jsonError('Selected status does not exist.');
        }

        $stmt = $pdo->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        $stmt->execute([$statusId, $complaintId]);
        jsonSuccess('Complaint status updated successfully.');

    } else {
        jsonError('Unsupported action.');
    }
} catch (Exception $e) {
    jsonError('Failed to execute complaint action: ' . $e->getMessage());
}
