<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];
$action = sanitize($_POST['action'] ?? '');

if ($action === 'delete') {
    $complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
    
    if ($complaintId <= 0) {
        jsonError('Invalid complaint ID.');
    }
    
    try {
        $stmt = $pdo->prepare(
            "SELECT c.*, cs.status_name 
             FROM complaints c
             JOIN complaint_status cs ON c.status_id = cs.status_id
             WHERE c.complaint_id = ? AND c.student_id = ?"
        );
        $stmt->execute([$complaintId, $studentId]);
        $complaint = $stmt->fetch();
        
        if (!$complaint) {
            jsonError('Complaint record not found.');
        }
        
        if ($complaint['status_name'] !== 'Pending') {
            jsonError('Only pending complaints can be deleted.');
        }
        
        // Delete image attachment if exists
        if ($complaint['image'] && file_exists(COMPLAINT_IMG_PATH . $complaint['image'])) {
            unlink(COMPLAINT_IMG_PATH . $complaint['image']);
        }
        
        // Perform deletion
        $stmt = $pdo->prepare("DELETE FROM complaints WHERE complaint_id = ? AND student_id = ?");
        $stmt->execute([$complaintId, $studentId]);
        
        jsonSuccess('Complaint deleted successfully.');
        
    } catch (Exception $e) {
        jsonError('Failed to delete complaint: ' . $e->getMessage());
    }
} else {
    jsonError('Unsupported action requested.');
}
