<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

try {
    $stmt = $pdo->query(
        "SELECT b.*, COUNT(c.complaint_id) as complaint_count 
         FROM buildings b
         LEFT JOIN complaints c ON b.building_id = c.building_id
         GROUP BY b.building_id
         ORDER BY b.building_name ASC"
    );
    $buildings = $stmt->fetchAll();
} catch (Exception $e) {
    $buildings = [];
}

$pageTitle = "Manage Locations";
$currentPage = "locations";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-lg stagger-in" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3><i class="fas fa-map-marker-alt text-gradient"></i> Campus Locations</h3>
        <button onclick="openAddModal()" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Building
        </button>
    </div>
    
    <div class="card-body">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Building ID</th>
                        <th>Building Name</th>
                        <th>Description</th>
                        <th>Associated complaints</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($buildings)): ?>
                        <tr>
                            <td colspan="5" class="table-empty">
                                <i class="fas fa-building"></i>
                                <p>No buildings configured. Please add campus structures.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($buildings as $bld): ?>
                            <tr>
                                <td>#BLD-<?= str_pad($bld['building_id'], 2, '0', STR_PAD_LEFT) ?></td>
                                <td><strong class="text-primary"><?= sanitize($bld['building_name']) ?></strong></td>
                                <td><?= sanitize($bld['description'] ?: 'No details logged') ?></td>
                                <td><span class="badge badge-secondary"><?= $bld['complaint_count'] ?> Filed</span></td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <button onclick='openEditModal(<?= json_encode($bld) ?>)' class="btn btn-outline btn-sm btn-icon" title="Edit Building">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="handleDelete(<?= $bld['building_id'] ?>, <?= $bld['complaint_count'] ?>)" class="btn btn-outline btn-sm btn-icon text-danger" title="Delete Building">
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
<div class="modal-overlay" id="building-modal">
    <div class="modal" style="max-width: 460px;">
        <div class="modal-header">
            <h3 id="modal-title">Add Building</h3>
            <button class="modal-close" onclick="Modal.close('building-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="building-form" onsubmit="saveBuilding(event)">
            <input type="hidden" name="building_id" id="m-building-id">
            <input type="hidden" name="action" id="m-action" value="add">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="m-building-name" class="form-label">Building Name <span class="required">*</span></label>
                    <input type="text" name="building_name" id="m-building-name" class="form-control" required placeholder="e.g. Academic Block A, Library Annex">
                </div>
                
                <div class="form-group">
                    <label for="m-description" class="form-label">Description</label>
                    <textarea name="description" id="m-description" class="form-control" placeholder="Short detail mapping classrooms or blocks..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('building-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Building</button>
            </div>
        </form>
    </div>
</div>

<?php 
$extraScripts = "
<script>
function openAddModal() {
    document.getElementById('building-form').reset();
    document.getElementById('m-action').value = 'add';
    document.getElementById('m-building-id').value = '';
    document.getElementById('modal-title').textContent = 'Add Campus Location';
    Modal.open('building-modal');
}

function openEditModal(bld) {
    document.getElementById('m-action').value = 'edit';
    document.getElementById('m-building-id').value = bld.building_id;
    document.getElementById('modal-title').textContent = 'Modify Campus Location';
    document.getElementById('m-building-name').value = bld.building_name;
    document.getElementById('m-description').value = bld.description || '';
    Modal.open('building-modal');
}

async function saveBuilding(e) {
    e.preventDefault();
    if (!validateForm('building-form')) return;

    const formData = new FormData(document.getElementById('building-form'));
    const res = await ajaxRequest('" . BACKEND_URL . "/admin/ajax/location_crud.php', 'POST', formData);
    if (res.success) {
        Toast.success('Success', res.message);
        Modal.close('building-modal');
        setTimeout(() => location.reload(), 1000);
    } else {
        Toast.error('Failure', res.message);
    }
}

function handleDelete(id, count) {
    if (count > 0) {
        Toast.error('Forbidden', 'Cannot delete a location that has complaints logged against it.');
        return;
    }

    confirmAction(
        'Delete Location Structure',
        'Are you sure you want to permanently delete this building record?',
        async () => {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('building_id', id);

            const res = await ajaxRequest('" . BACKEND_URL . "/admin/ajax/location_crud.php', 'POST', formData);
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
