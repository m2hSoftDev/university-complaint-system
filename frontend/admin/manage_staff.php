<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

try {
    $stmt = $pdo->query(
        "SELECT ms.*, u.name, u.email, u.status 
         FROM maintenance_staff ms
         JOIN users u ON ms.user_id = u.user_id
         ORDER BY u.name ASC"
    );
    $staffList = $stmt->fetchAll();
} catch (Exception $e) {
    $staffList = [];
}

$pageTitle = "Manage Staff";
$currentPage = "staff";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-lg stagger-in">
    <div class="card-header">
        <h3><i class="fas fa-hard-hat text-gradient"></i> Maintenance Technicians</h3>
        <button onclick="openAddModal()" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add New Technician
        </button>
    </div>
    
    <div class="card-body">
        <div class="table-filters">
            <div class="filter-search input-group" style="max-width: 400px;">
                <input type="text" id="staff-search" class="form-control" placeholder="Search by name, ID, or specialization...">
                <i class="fas fa-search input-group-icon"></i>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table" id="staff-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Designation</th>
                        <th>Specialization</th>
                        <th>Availability</th>
                        <th>Account Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr>
                            <td colspan="8" class="table-empty">
                                <i class="fas fa-users-slash"></i>
                                <p>No technicians logged in directory.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffList as $staff): ?>
                            <tr>
                                <td><strong class="text-primary"><?= sanitize($staff['employee_id']) ?></strong></td>
                                <td><?= sanitize($staff['name']) ?></td>
                                <td><?= sanitize($staff['email']) ?></td>
                                <td><?= sanitize($staff['designation'] ?: 'Technician') ?></td>
                                <td><?= sanitize($staff['specialization'] ?: 'General Repairs') ?></td>
                                <td><?= getAvailabilityBadge($staff['availability']) ?></td>
                                <td>
                                    <span class="badge <?= $staff['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst(sanitize($staff['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <button onclick='openEditModal(<?= json_encode($staff) ?>)' class="btn btn-outline btn-sm btn-icon" title="Edit Staff Record">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="handleDelete(<?= $staff['user_id'] ?>)" class="btn btn-outline btn-sm btn-icon text-danger" title="Delete Staff Record">
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

<div class="modal-overlay" id="staff-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Add Technician</h3>
            <button class="modal-close" onclick="Modal.close('staff-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="staff-form" onsubmit="saveStaff(event)">
            <input type="hidden" name="user_id" id="m-user-id">
            <input type="hidden" name="action" id="m-action" value="add">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="m-name" class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="m-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="m-email" class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="m-email" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-phone" class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="m-phone" class="form-control">
                    </div>
                    <div class="form-group" id="password-group">
                        <label for="m-password" class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="m-password" class="form-control" placeholder="••••••••">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-employee-id" class="form-label">Employee ID <span class="required">*</span></label>
                        <input type="text" name="employee_id" id="m-employee-id" class="form-control" placeholder="EMP-4093" required>
                    </div>
                    <div class="form-group">
                        <label for="m-designation" class="form-label">Designation</label>
                        <input type="text" name="designation" id="m-designation" class="form-control" placeholder="Senior Electrician">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-specialization" class="form-label">Specialization / Expertise</label>
                        <input type="text" name="specialization" id="m-specialization" class="form-control" placeholder="Electrical, HVAC, Plumbing">
                    </div>
                    <div class="form-group">
                        <label for="m-availability" class="form-label">Availability Status</label>
                        <select name="availability" id="m-availability" class="form-control">
                            <option value="available">Available</option>
                            <option value="busy">Busy</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="m-status" class="form-label">Account Status</label>
                    <select name="status" id="m-status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('staff-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modal-submit-btn">Save Record</button>
            </div>
        </form>
    </div>
</div>

<?php 
$extraScripts = "
<script>
setupTableSearch('staff-search', 'staff-table');

function openAddModal() {
    document.getElementById('staff-form').reset();
    document.getElementById('m-action').value = 'add';
    document.getElementById('m-user-id').value = '';
    document.getElementById('modal-title').textContent = 'Add Technician';
    document.getElementById('m-password').setAttribute('required', 'required');
    document.getElementById('password-group').style.display = 'block';
    Modal.open('staff-modal');
}

function openEditModal(staff) {
    document.getElementById('m-action').value = 'edit';
    document.getElementById('m-user-id').value = staff.user_id;
    document.getElementById('modal-title').textContent = 'Modify Technician Details';
    
    document.getElementById('m-name').value = staff.name;
    document.getElementById('m-email').value = staff.email;
    document.getElementById('m-phone').value = staff.phone || '';
    document.getElementById('m-employee-id').value = staff.employee_id;
    document.getElementById('m-designation').value = staff.designation || '';
    document.getElementById('m-specialization').value = staff.specialization || '';
    document.getElementById('m-availability').value = staff.availability;
    document.getElementById('m-status').value = staff.status;
    
    document.getElementById('m-password').removeAttribute('required');
    document.getElementById('m-password').placeholder = 'Leave blank to keep current';
    
    Modal.open('staff-modal');
}

async function saveStaff(e) {
    e.preventDefault();
    if (!validateForm('staff-form')) return;
    
    const formData = new FormData(document.getElementById('staff-form'));
    const res = await ajaxRequest('" . BACKEND_URL . "/admin/ajax/staff_crud.php', 'POST', formData);
    if (res.success) {
        Toast.success('Done', res.message);
        Modal.close('staff-modal');
        setTimeout(() => location.reload(), 1000);
    } else {
        Toast.error('Failure', res.message);
    }
}

function handleDelete(id) {
    confirmAction(
        'Delete Technician', 
        'Are you sure you want to permanently erase this technician profile from directory? This resets their active dispatches back to unassigned.',
        async () => {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('user_id', id);
            
            const res = await ajaxRequest('" . BACKEND_URL . "/admin/ajax/staff_crud.php', 'POST', formData);
            if (res.success) {
                Toast.success('Done', res.message);
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
