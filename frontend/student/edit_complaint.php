<?php

require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare(
        "SELECT c.*, cs.status_name 
         FROM complaints c
         JOIN complaint_status cs ON c.status_id = cs.status_id
         WHERE c.complaint_id = ? AND c.student_id = ?"
    );
    $stmt->execute([$complaintId, $studentId]);
    $complaint = $stmt->fetch();
} catch (Exception $e) {
    $complaint = false;
}

if (!$complaint) {
    header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
    exit();
}

if ($complaint['status_name'] !== 'Pending') {
    $_SESSION['complaint_error'] = 'Only pending complaints can be modified.';
    header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
    exit();
}

try {
    $categories = getCategories($pdo);
    $buildings = getBuildings($pdo);
} catch (Exception $e) {
    $categories = [];
    $buildings = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $building_id = $_POST['building_id'] ?? '';
    $priority = $_POST['priority'] ?? 'Medium';
    $delete_image = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';

    if (empty($title) || empty($description) || empty($category_id) || empty($building_id)) {
        $error = 'Please fill in all required fields.';
    } else {
        $imageName = $complaint['image'];
        $uploadOk = true;

        if ($delete_image && $imageName) {
            if (file_exists(COMPLAINT_IMG_PATH . $imageName)) {
                unlink(COMPLAINT_IMG_PATH . $imageName);
            }
            $imageName = null;
        }

        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadImage($_FILES['complaint_image'], COMPLAINT_IMG_PATH);
            if ($uploadResult['success']) {
                if ($complaint['image'] && file_exists(COMPLAINT_IMG_PATH . $complaint['image'])) {
                    unlink(COMPLAINT_IMG_PATH . $complaint['image']);
                }
                $imageName = $uploadResult['filename'];
            } else {
                $error = 'Image upload failed: ' . $uploadResult['error'];
                $uploadOk = false;
            }
        }

        if ($uploadOk) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE complaints 
                     SET title = ?, description = ?, category_id = ?, building_id = ?, priority = ?, image = ? 
                     WHERE complaint_id = ? AND student_id = ?"
                );
                $stmt->execute([
                    $title,
                    $description,
                    (int)$category_id,
                    (int)$building_id,
                    $priority,
                    $imageName,
                    $complaintId,
                    $studentId
                ]);

                $_SESSION['complaint_success'] = 'Complaint updated successfully.';
                header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
                exit();
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Edit Complaint";
$currentPage = "complaints";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-edit text-accent"></i> Edit Complaint #CMP-<?= str_pad($complaint['complaint_id'], 4, '0', STR_PAD_LEFT) ?></div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="<?= FRONTEND_URL ?>/student/edit_complaint.php?id=<?= $complaintId ?>" method="POST" enctype="multipart/form-data" id="complaint-form">
            <div class="form-group">
                <label for="title" class="form-label">Issue Title / Summary <span class="required">*</span></label>
                <div class="input-group">
                    <input type="text" name="title" id="title" class="form-control" value="<?= sanitize($complaint['title']) ?>" required>
                    <i class="fas fa-tag input-group-icon"></i>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="category_id" class="form-label">Category <span class="required">*</span></label>
                    <div class="input-group">
                        <select name="category_id" id="category_id" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= (int)$complaint['category_id'] === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-tools input-group-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="building_id" class="form-label">Location / Building <span class="required">*</span></label>
                    <div class="input-group">
                        <select name="building_id" id="building_id" class="form-control" required>
                            <?php foreach ($buildings as $bld): ?>
                                <option value="<?= $bld['building_id'] ?>" <?= (int)$complaint['building_id'] === (int)$bld['building_id'] ? 'selected' : '' ?>>
                                    <?= sanitize($bld['building_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-map-marker-alt input-group-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Priority Level</label>
                <div style="display: flex; gap: 16px;">
                    <label><input type="radio" name="priority" value="Low" <?= $complaint['priority'] === 'Low' ? 'checked' : '' ?>> Low</label>
                    <label><input type="radio" name="priority" value="Medium" <?= $complaint['priority'] === 'Medium' ? 'checked' : '' ?>> Medium</label>
                    <label><input type="radio" name="priority" value="High" <?= $complaint['priority'] === 'High' ? 'checked' : '' ?>> High</label>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Detailed Description <span class="required">*</span></label>
                <textarea name="description" id="description" class="form-control" required><?= sanitize($complaint['description']) ?></textarea>
            </div>

            <?php if (!empty($complaint['image'])): ?>
                <div class="form-group">
                    <label class="form-label">Current Image Attachment</label>
                    <div>
                        <img src="<?= BACKEND_URL ?>/uploads/complaints/<?= sanitize($complaint['image']) ?>" alt="Reference image" style="max-width: 200px; border-radius: 8px; border: 1px solid var(--border);">
                        <div style="margin-top: 8px;">
                            <label><input type="checkbox" name="delete_image" value="1"> Delete current image</label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="complaint_image" class="form-label">Upload New Image Reference (Replaces current)</label>
                <input type="file" name="complaint_image" id="complaint_image" class="form-control" accept="image/*">
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
                <a href="<?= FRONTEND_URL ?>/student/my_complaints.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
