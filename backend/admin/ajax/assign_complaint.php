<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

$adminId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
    $staffId = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;

    if ($complaintId <= 0 || $staffId <= 0) {
        jsonError('Invalid parameters. Select a valid technician.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT c.*, cs.status_name 
             FROM complaints c
             JOIN complaint_status cs ON c.status_id = cs.status_id
             WHERE c.complaint_id = ?"
        );
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch();

        if (!$complaint) {
            jsonError('Complaint not found.');
        }

        if (in_array($complaint['status_name'], ['Resolved', 'Closed'])) {
            jsonError('Cannot assign a resolved or closed complaint.');
        }

        //  Fetch technician details
        $stmtStaff = $pdo->prepare(
            "SELECT ms.*, u.name, u.user_id as staff_user_id 
             FROM maintenance_staff ms
             JOIN users u ON ms.user_id = u.user_id
             WHERE ms.staff_id = ?"
        );
        $stmtStaff->execute([$staffId]);
        $staff = $stmtStaff->fetch();

        if (!$staff) {
            jsonError('Technician profile not found.');
        }

        $stmtOldAssign = $pdo->prepare(
            "UPDATE assignments 
             SET assignment_status = 'Rejected' 
             WHERE complaint_id = ? AND assignment_status IN ('Assigned', 'Accepted')"
        );
        $stmtOldAssign->execute([$complaintId]);

        //  Create new job assignment
        $stmtNewAssign = $pdo->prepare(
            "INSERT INTO assignments (complaint_id, staff_id, assigned_by, assignment_status) 
             VALUES (?, ?, ?, 'Assigned')"
        );
        $stmtNewAssign->execute([$complaintId, $staffId, $adminId]);

        //  Update parent complaint status to 'Assigned'
        $statusAssignedId = getStatusIdByName($pdo, 'Assigned');
        if (!$statusAssignedId) $statusAssignedId = 2; // Fallback

        $stmtComplaint = $pdo->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        $stmtComplaint->execute([$statusAssignedId, $complaintId]);

        //  Log progress update history entry
        $stmtUpdate = $pdo->prepare(
            "INSERT INTO complaint_updates (complaint_id, staff_id, status_id, progress_note) 
             VALUES (?, ?, ?, ?)"
        );
        $stmtUpdate->execute([
            $complaintId,
            $staffId,
            $statusAssignedId,
            "Complaint assigned to: " . $staff['name'] . " (" . ($staff['designation'] ?: 'Technician') . ") by System Administrator."
        ]);

        //  Notify technician
        createNotification(
            $pdo,
            $staff['staff_user_id'],
            "New Job Dispatch! 🛠️",
            "You have been assigned to complaint #CMP-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " (" . sanitize($complaint['title']) . ")."
        );

        //  Notify student
        $stmtStudUser = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmtStudUser->execute([$complaint['student_id']]);
        $studentUserId = $stmtStudUser->fetchColumn();

        if ($studentUserId) {
            createNotification(
                $pdo,
                $studentUserId,
                "Representative Assigned ⚙️",
                "Your complaint (#CMP-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . ") has been assigned to: " . $staff['name'] . "."
            );
        }

        $pdo->commit();
        jsonSuccess('Job assigned successfully to: ' . $staff['name']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to complete assignment operation: ' . $e->getMessage());
    }
} else {
    jsonError('Method not allowed', 405);
}
