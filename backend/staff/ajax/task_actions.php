<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('staff');

$staffId = $_SESSION['staff_id'];
$action = strtolower(trim($_POST['action'] ?? ''));
$assignmentId = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;

if ($assignmentId <= 0) {
    jsonError('Invalid assignment ID.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.student_id, c.title 
         FROM assignments a 
         JOIN complaints c ON a.complaint_id = c.complaint_id
         WHERE a.assignment_id = ? AND a.staff_id = ?"
    );
    $stmt->execute([$assignmentId, $staffId]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        jsonError('Assignment not found.');
    }

    if ($action === 'accept' || $action === 'accepted') {
        if ($assignment['assignment_status'] !== 'Assigned') {
            jsonError('This task has already been processed.');
        }

        $pdo->beginTransaction();

        $stmtAssign = $pdo->prepare(
            "UPDATE assignments SET assignment_status = 'Accepted', accepted_date = NOW() 
             WHERE assignment_id = ?"
        );
        $stmtAssign->execute([$assignmentId]);

        $statusAcceptedId = getStatusIdByName($pdo, 'Accepted');
        if (!$statusAcceptedId) $statusAcceptedId = 3;

        $stmtComplaint = $pdo->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        $stmtComplaint->execute([$statusAcceptedId, $assignment['complaint_id']]);

        $stmtUpdate = $pdo->prepare(
            "INSERT INTO complaint_updates (complaint_id, staff_id, status_id, progress_note) 
             VALUES (?, ?, ?, 'Staff member accepted the task and initiated repair review.')"
        );
        $stmtUpdate->execute([$assignment['complaint_id'], $staffId, $statusAcceptedId]);

        $stmtStaff = $pdo->prepare("UPDATE maintenance_staff SET availability = 'busy' WHERE staff_id = ?");
        $stmtStaff->execute([$staffId]);

        $stmtStudUser = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmtStudUser->execute([$assignment['student_id']]);
        $studentUserId = $stmtStudUser->fetchColumn();

        if ($studentUserId) {
            createNotification(
                $pdo,
                $studentUserId,
                "Task Accepted ⚙️",
                "A technician has accepted your complaint (#CMP-" . str_pad($assignment['complaint_id'], 4, '0', STR_PAD_LEFT) . ") and is planning repairs."
            );
        }

        $pdo->commit();
        jsonSuccess('Dispatch accepted. Job is now active.');

    } elseif ($action === 'reject' || $action === 'rejected') {
        if ($assignment['assignment_status'] !== 'Assigned') {
            jsonError('This task cannot be rejected anymore.');
        }

        $pdo->beginTransaction();

        $stmtAssign = $pdo->prepare("UPDATE assignments SET assignment_status = 'Rejected' WHERE assignment_id = ?");
        $stmtAssign->execute([$assignmentId]);

        $statusPendingId = getStatusIdByName($pdo, 'Pending');
        if (!$statusPendingId) $statusPendingId = 1;

        $stmtComplaint = $pdo->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        $stmtComplaint->execute([$statusPendingId, $assignment['complaint_id']]);

        $stmtUpdate = $pdo->prepare(
            "INSERT INTO complaint_updates (complaint_id, staff_id, status_id, progress_note) 
             VALUES (?, ?, ?, 'Staff member declined the job assignment. Complaint returned to assignment queue.')"
        );
        $stmtUpdate->execute([$assignment['complaint_id'], $staffId, $statusPendingId]);

        notifyAdmins(
            $pdo,
            "Job Dispatch Rejected ",
            "A technician declined the assignment for complaint (#CMP-" . str_pad($assignment['complaint_id'], 4, '0', STR_PAD_LEFT) . ")."
        );

        $pdo->commit();
        jsonSuccess('Dispatch rejected successfully.');

    } else {
        jsonError('Unsupported action.');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Execution error: ' . $e->getMessage());
}
