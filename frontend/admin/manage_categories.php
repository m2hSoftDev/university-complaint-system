<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

try {
    $stmt = $pdo->query(
        "SELECT cc.*, COUNT(c.complaint_id) as complaint_count 
         FROM complaint_categories cc
         LEFT JOIN complaints c ON cc.category_id = c.category_id
         GROUP BY cc.category_id
         ORDER BY cc.category_name ASC"
    );
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$pageTitle = "Manage Categories";
$currentPage = "categories";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-lg stagger-in" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3><i class="fas fa-tags text-gradient"></i> Complaint Categories</h3>
        <button onclick="openAddModal()" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Category
        </button>
    </div>
    
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Total Complaints</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" class="table-empty">
                                <i class="fas fa-tags"></i>
                                <p>No categories defined. Please add one to receive complaints.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>#CAT-<?= str_pad($cat['category_id'], 2, '0', STR_PAD_LEFT) ?></td>
                                <td><strong class="text-primary"><?= sanitize($cat['category_name']) ?></strong></td>
                                <td><?= sanitize($cat['description'] ?: 'No description provided') ?></td>
                                <td><span class="badge badge-secondary"><?= $cat['complaint_count'] ?> Filed</span></td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <button onclick='openEditModal(<?= json_encode($cat) ?>)' class="btn btn-outline btn-sm btn-icon" title="Edit Category">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="handleDelete(<?= $cat['category_id'] ?>, <?= $cat['complaint_count'] ?>)" class="btn btn-outline btn-sm btn-icon text-danger" title="Delete Category">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="category-modal">
    <div class="modal" style="max-width: 460px;">
        <div class="modal-header">
            <h3 id="modal-title">Add Category</h3>
            <button class="modal-close" onclick="Modal.close('category-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="category-form" onsubmit="saveCategory(event)">
            <input type="hidden" name="category_id" id="m-category-id">
            <input type="hidden" name="action" id="m-action" value="add">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="m-category-name" class="form-label">Category Name <span class="required">*</span></label>
                    <input type="text" name="category_name" id="m-category-name" class="form-control" required placeholder="e.g. Electrical, Plumbing">
                </div>
                
                <div class="form-group">
                    <label for="m-description" class="form-label">Description</label>
                    <textarea name="description" id="m-description" class="form-control" placeholder="Short description of items falls here..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('category-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<?php 
$extraScripts = "
<script>
function openAddModal() {
    document.getElementById('category-form').reset();
    document.getElementById('m-action').value = 'add';
    document.getElementById('m-category-id').value = '';
    document.getElementById('modal-title').textContent = 'Add Category';
    Modal.open('category-modal');
}

function openEditModal(cat) {
    document.getElementById('m-action').value = 'edit';
    document.getElementById('m-category-id').value = cat.category_id;
    document.getElementById('modal-title').textContent = 'Modify Category';
    document.getElementById('m-category-name').value = cat.category_name;
    document.getElementById('m-description').value = cat.description || '';
    Modal.open('category-modal');
}

async function saveCategory(e) {
    e.preventDefault();
    if (!validateForm('category-form')) return;

    const formData = new FormData(document.getElementById('category-form'));
    const res = await ajaxRequest('" . BASE_URL . "/admin/ajax/category_crud.php', 'POST', formData);
    if (res.success) {
        Toast.success('Success', res.message);
        Modal.close('category-modal');
        setTimeout(() => location.reload(), 1000);
    } else {
        Toast.error('Failure', res.message);
    }
}

function handleDelete(id, count) {
    if (count > 0) {
        Toast.error('Forbidden', 'Cannot delete a category which has filed complaints associated with it.');
        return;
    }

    confirmAction(
        'Delete Category',
        'Are you sure you want to permanently delete this category?',
        async () => {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('category_id', id);

            const res = await ajaxRequest('" . BASE_URL . "/admin/ajax/category_crud.php', 'POST', formData);
            if (res.success) {
                Toast.success('Success', res.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error('Failure', res.message);
            }
        }
    );
}
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>
