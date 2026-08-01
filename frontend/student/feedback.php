<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
    $comments = sanitize($_POST['comments'] ?? '');

    if ($complaintId <= 0 || $rating < 1 || $rating > 5) {
        $_SESSION['feedback_error'] = 'Invalid feedback parameters.';
        header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
        exit();
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
            $_SESSION['feedback_error'] = 'Complaint record not found.';
            header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
            exit();
        }

        $pdo->beginTransaction();

        $stmtFb = $pdo->prepare(
            "INSERT INTO feedback (complaint_id, student_id, rating, comments)
             VALUES (?, ?, ?, ?)"
        );
        $stmtFb->execute([$complaintId, $studentId, $rating, $comments]);

        $statusClosedId = getStatusIdByName($pdo, 'Closed');
        if (!$statusClosedId) $statusClosedId = 6;

        $stmtClosed = $pdo->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        $stmtClosed->execute([$statusClosedId, $complaintId]);

        $stmtLog = $pdo->prepare(
            "INSERT INTO complaint_updates (complaint_id, staff_id, status_id, progress_note)
             VALUES (?, NULL, ?, ?)"
        );
        $stmtLog->execute([
            $complaintId,
            $statusClosedId,
            "Feedback submitted (" . $rating . " Stars). Complaint ticket officially closed."
        ]);

        $pdo->commit();

        $_SESSION['feedback_success'] = 'Thank you! Your feedback has been recorded and ticket closed.';
        header('Location: ' . FRONTEND_URL . '/student/view_complaint.php?id=' . $complaintId);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['feedback_error'] = 'Failed to submit feedback: ' . $e->getMessage();
        header('Location: ' . FRONTEND_URL . '/student/view_complaint.php?id=' . $complaintId);
        exit();
    }
} else {
    header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
    exit();
}
