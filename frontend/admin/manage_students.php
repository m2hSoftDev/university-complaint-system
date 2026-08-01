<?php
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

requireLogin('admin');

try {
    $buildings = getBuildings($pdo);
} catch (Exception $e) {
    $buildings = [];
}

try {
    $stmt = $pdo->query(
        "SELECT s.*, u.name, u.email, u.phone, u.status, b.building_name 
         FROM students s
         JOIN users u ON s.user_id = u.user_id
         LEFT JOIN buildings b ON s.building_id = b.building_id
         ORDER BY u.name ASC"
    );
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    $students = [];
}

$pageTitle = "Manage Students";
$currentPage = "students";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-lg stagger-in">
    <div class="card-header">
        <h3><i class="fas fa-user-graduate text-gradient"></i> Student Directory</h3>
        <button onclick="openAddModal()" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add New Student
        </button>
    </div>
    
    <div class="card-body">
        <div class="table-filters">
            <div class="filter-search input-group" style="max-width: 400px;">
                <input type="text" id="student-search" class="form-control" placeholder="Search by name, ID, or department...">
                <i class="fas fa-search input-group-icon"></i>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table" id="students-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Hostel / Building</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                <i class="fas fa-users-slash"></i>
                                <p>No student records logged in system database catalog.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong class="text-primary"><?= sanitize($student['student_number']) ?></strong></td>
                                <td><?= sanitize($student['name']) ?></td>
                                <td><?= sanitize($student['email']) ?></td>
                                <td><?= sanitize($student['department'] ?: '—') ?> (<?= sanitize($student['semester'] ?: '—') ?>)</td>
                                <td><?= sanitize($student['building_name'] ?: '—') ?> <?= !empty($student['room_no']) ? "(Rm " . sanitize($student['room_no']) . ")" : "" ?></td>
                                <td>
                                    <span class="badge <?= $student['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst(sanitize($student['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <!-- Safe JSON encoding inside HTML data attributes -->
                                        <button data-student='<?= htmlspecialchars(json_encode($student), ENT_QUOTES, "UTF-8") ?>' onclick="viewStudentFromBtn(this)" class="btn btn-outline btn-sm btn-icon" title="View Student Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button data-student='<?= htmlspecialchars(json_encode($student), ENT_QUOTES, "UTF-8") ?>' onclick="openEditModalFromBtn(this)" class="btn btn-outline btn-sm btn-icon" title="Edit Student">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="handleDelete(<?= (int)$student['user_id'] ?>)" class="btn btn-outline btn-sm btn-icon text-danger" title="Delete Student">
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

<div class="modal-overlay" id="student-view-modal">
    <div class="modal" style="max-width: 560px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-graduate" style="margin-right:8px;"></i> Student Profile</h3>
            <button class="modal-close" onclick="closeModal('student-view-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="detail-section">
                <div class="detail-row">
                    <div class="detail-label">Student ID / Roll</div>
                    <div class="detail-value text-primary font-bold" id="vs-student-number">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value" id="vs-name">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value" id="vs-email">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Phone Number</div>
                    <div class="detail-value" id="vs-phone">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department</div>
                    <div class="detail-value" id="vs-department">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Semester</div>
                    <div class="detail-value" id="vs-semester">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Hostel / Building</div>
                    <div class="detail-value" id="vs-building">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Room Number</div>
                    <div class="detail-value" id="vs-room">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Account Status</div>
                    <div class="detail-value" id="vs-status">—</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Registered On</div>
                    <div class="detail-value" id="vs-created">—</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('student-view-modal')">Close</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="student-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Add Student Record</h3>
            <button class="modal-close" onclick="closeModal('student-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="student-form" onsubmit="saveStudent(event)">
            <input type="hidden" name="user_id" id="m-user-id">
            <input type="hidden" name="action" id="m-action" value="add">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="m-name" class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="m-name" class="form-control" placeholder="Enter student's full name" required>
                    </div>
                    <div class="form-group">
                        <label for="m-email" class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="m-email" class="form-control" placeholder="Enter email address" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-phone" class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="m-phone" class="form-control" placeholder="Enter phone number">
                    </div>
                    <div class="form-group" id="password-group">
                        <label for="m-password" class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="m-password" class="form-control" placeholder="Enter password">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-student-number" class="form-label">Student ID / Roll <span class="required">*</span></label>
                        <input type="text" name="student_number" id="m-student-number" class="form-control" placeholder="Enter student ID / roll" required>
                    </div>
                    <div class="form-group">
                        <label for="m-department" class="form-label">Department</label>
                        <input type="text" name="department" id="m-department" class="form-control" placeholder="Enter department">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-semester" class="form-label">Semester</label>
                        <input type="text" name="semester" id="m-semester" class="form-control" placeholder="Enter semester">
                    </div>
                    <div class="form-group">
                        <label for="m-building-id" class="form-label">Hostel / Location</label>
                        <select name="building_id" id="m-building-id" class="form-control">
                            <option value="">-- None --</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?= $b['building_id'] ?>"><?= sanitize($b['building_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="m-room-no" class="form-label">Room Number</label>
                        <input type="text" name="room_no" id="m-room-no" class="form-control" placeholder="Enter room number">
                    </div>
                    <div class="form-group">
                        <label for="m-status" class="form-label">Account Status</label>
                        <select name="status" id="m-status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('student-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modal-submit-btn">Save Student</button>
            </div>
        </form>
    </div>
</div>

<?php 
$extraScripts = "
<script>
// Safe Modal Open/Close Wrappers
function openModal(id) {
    if (window.Modal && typeof Modal.open === 'function') {
        Modal.open(id);
    } else {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('active', 'show');
            el.style.display = 'flex';
        }
    }
}

function closeModal(id) {
    if (window.Modal && typeof Modal.close === 'function') {
        Modal.close(id);
    } else {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('active', 'show');
            el.style.display = 'none';
        }
    }
}

// Data Unpackers from Button Attributes
function viewStudentFromBtn(btn) {
    try {
        const student = JSON.parse(btn.getAttribute('data-student'));
        viewStudent(student);
    } catch (e) {
        console.error('Failed to parse student data for viewing:', e);
    }
}

function openEditModalFromBtn(btn) {
    try {
        const student = JSON.parse(btn.getAttribute('data-student'));
        openEditModal(student);
    } catch (e) {
        console.error('Failed to parse student data for editing:', e);
    }
}

if (typeof setupTableSearch === 'function') {
    setupTableSearch('student-search', 'students-table');
}

function viewStudent(student) {
    document.getElementById('vs-student-number').textContent = student.student_number || '—';
    document.getElementById('vs-name').textContent = student.name || '—';
    document.getElementById('vs-email').textContent = student.email || '—';
    document.getElementById('vs-phone').textContent = student.phone || '—';
    document.getElementById('vs-department').textContent = student.department || '—';
    document.getElementById('vs-semester').textContent = student.semester || '—';
    document.getElementById('vs-building').textContent = student.building_name || '—';
    document.getElementById('vs-room').textContent = student.room_no || '—';
    document.getElementById('vs-status').textContent = student.status ? student.status.charAt(0).toUpperCase() + student.status.slice(1) : '—';
    document.getElementById('vs-created').textContent = student.created_at || '—';
    openModal('student-view-modal');
}

function openAddModal() {
    document.getElementById('student-form').reset();
    document.getElementById('m-action').value = 'add';
    document.getElementById('m-user-id').value = '';
    document.getElementById('modal-title').textContent = 'Add Student Record';
    document.getElementById('m-password').setAttribute('required', 'required');
    document.getElementById('password-group').style.display = 'block';
    openModal('student-modal');
}

function openEditModal(student) {
    document.getElementById('m-action').value = 'edit';
    document.getElementById('m-user-id').value = student.user_id;
    document.getElementById('modal-title').textContent = 'Modify Student Record';
    
    document.getElementById('m-name').value = student.name || '';
    document.getElementById('m-email').value = student.email || '';
    document.getElementById('m-phone').value = student.phone || '';
    document.getElementById('m-student-number').value = student.student_number || '';
    document.getElementById('m-department').value = student.department || '';
    document.getElementById('m-semester').value = student.semester || '';
    document.getElementById('m-building-id').value = student.building_id || '';
    document.getElementById('m-room-no').value = student.room_no || '';
    document.getElementById('m-status').value = student.status || 'active';
    
    document.getElementById('m-password').removeAttribute('required');
    document.getElementById('m-password').placeholder = 'Leave blank to keep current';
    
    openModal('student-modal');
}

async function saveStudent(e) {
    e.preventDefault();
    if (typeof validateForm === 'function' && !validateForm('student-form')) return;
    
    const formData = new FormData(document.getElementById('student-form'));
    const targetUrl = '../../backend/admin/ajax/student_crud.php';

    try {
        let res;
        if (typeof ajaxRequest === 'function') {
            res = await ajaxRequest(targetUrl, 'POST', formData);
        } else {
            const fetchRes = await fetch(targetUrl, { method: 'POST', body: formData });
            res = await fetchRes.json();
        }

        if (res && res.success) {
            if (window.Toast && typeof Toast.success === 'function') {
                Toast.success('Done', res.message);
            } else {
                alert(res.message || 'Student saved successfully!');
            }
            closeModal('student-modal');
            setTimeout(() => location.reload(), 800);
        } else {
            const errMsg = (res && res.message) ? res.message : 'Action failed.';
            if (window.Toast && typeof Toast.error === 'function') {
                Toast.error('Failure', errMsg);
            } else {
                alert('Error: ' + errMsg);
            }
        }
    } catch (err) {
        alert('A network or server error occurred.');
        console.error(err);
    }
}

function handleDelete(id) {
    const performDelete = async () => {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', id);
        const targetUrl = '../../backend/admin/ajax/student_crud.php';
        
        try {
            let res;
            if (typeof ajaxRequest === 'function') {
                res = await ajaxRequest(targetUrl, 'POST', formData);
            } else {
                const fetchRes = await fetch(targetUrl, { method: 'POST', body: formData });
                res = await fetchRes.json();
            }

            if (res && res.success) {
                if (window.Toast && typeof Toast.success === 'function') {
                    Toast.success('Done', res.message);
                } else {
                    alert(res.message || 'Record deleted successfully.');
                }
                setTimeout(() => location.reload(), 800);
            } else {
                const errMsg = (res && res.message) ? res.message : 'Delete failed.';
                if (window.Toast && typeof Toast.error === 'function') {
                    Toast.error('Failure', errMsg);
                } else {
                    alert('Error: ' + errMsg);
                }
            }
        } catch (err) {
            alert('A network or server error occurred during deletion.');
            console.error(err);
        }
    };

    if (typeof confirmAction === 'function') {
        confirmAction(
            'Delete Student Record', 
            'Are you sure you want to permanently erase this student record?',
            performDelete
        );
    } else {
        if (confirm('Are you sure you want to permanently erase this student record?')) {
            performDelete();
        }
    }
}
</script>
";
require_once __DIR__ . '/../includes/footer.php'; 
?>