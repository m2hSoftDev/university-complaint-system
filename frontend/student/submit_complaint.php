<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('student');

$studentId = $_SESSION['student_id'];
$error = '';
$success = '';

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
    
    if (empty($title) || empty($description) || empty($category_id) || empty($building_id)) {
        $error = 'Please fill in all required fields.';
    } else {
        $imageName = null;
        $uploadOk = true;
        
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadImage($_FILES['complaint_image'], COMPLAINT_IMG_PATH);
            if ($uploadResult['success']) {
                $imageName = $uploadResult['filename'];
            } else {
                $error = 'Image upload failed: ' . $uploadResult['error'];
                $uploadOk = false;
            }
        }
        
        if ($uploadOk) {
            try {
                $statusPendingId = getStatusIdByName($pdo, 'Pending');
                if (!$statusPendingId) {
                    $statusPendingId = 1;
                }
                
                $stmt = $pdo->prepare(
                    "INSERT INTO complaints (student_id, category_id, building_id, status_id, title, description, image, priority) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $studentId,
                    (int)$category_id,
                    (int)$building_id,
                    $statusPendingId,
                    $title,
                    $description,
                    $imageName,
                    $priority
                ]);
                
                $complaintId = $pdo->lastInsertId();
                
                notifyAdmins(
                    $pdo, 
                    "New Complaint Submitted", 
                    "A new complaint (#CMP-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . ") has been filed by a student."
                );
                
                $_SESSION['complaint_success'] = 'Your complaint has been submitted successfully!';
                header('Location: ' . FRONTEND_URL . '/student/my_complaints.php');
                exit();
                
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
                if ($imageName && file_exists(COMPLAINT_IMG_PATH . $imageName)) {
                    unlink(COMPLAINT_IMG_PATH . $imageName);
                }
            }
        }
    }
}

$pageTitle = "Submit Complaint";
$currentPage = "submit";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-edit text-accent"></i> File a Maintenance Issue</div>
    </div>
    
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="<?= FRONTEND_URL ?>/student/submit_complaint.php" method="POST" enctype="multipart/form-data" id="complaint-form" onsubmit="return validateForm('complaint-form')">
            <div class="form-group">
                <label for="title" class="form-label">Issue Title / Summary <span class="required">*</span></label>
                <div class="input-group">
                    <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Water leak in Hostel washroom" required>
                    <i class="fas fa-tag input-group-icon"></i>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="category_id" class="form-label">Category <span class="required">*</span></label>
                    <div class="input-group">
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= sanitize($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-tools input-group-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="building_id" class="form-label">Location / Building <span class="required">*</span></label>
                    <div class="input-group">
                        <select name="building_id" id="building_id" class="form-control" required>
                            <option value="">-- Select Building --</option>
                            <?php foreach ($buildings as $bld): ?>
                                <option value="<?= $bld['building_id'] ?>"><?= sanitize($bld['building_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-map-marker-alt input-group-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Priority Level</label>
                <div style="display: flex; gap: 16px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="radio" name="priority" value="Low"> Low
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="radio" name="priority" value="Medium" checked> Medium
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="radio" name="priority" value="High"> High
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Detailed Description <span class="required">*</span></label>
                <textarea name="description" id="description" class="form-control" placeholder="Provide details..." required></textarea>
            </div>

            <div class="form-group">
                <label for="complaint_image" class="form-label">Attach Image Reference (Optional)</label>
                <input type="file" name="complaint_image" id="complaint_image" class="form-control" accept="image/*">
                <img id="image_preview" src="#" alt="Preview" style="display:none; max-width: 200px; margin-top: 10px; border-radius: 8px; border: 1px solid var(--border);">
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
                <a href="<?= FRONTEND_URL ?>/student/dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Submit Complaint
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
