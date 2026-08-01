<?php


function sanitize($data) {  
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function sanitizeArray($data) {
    return array_map('sanitize', $data);
}


function uploadImage($file, $targetDir) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL    => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file selected',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Upload failed'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_', true) . '.' . $ext;
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Failed to save uploaded file'];
}


function formatDate($date, $format = 'M d, Y h:i A') {
    if (empty($date)) return '—';
    return date($format, strtotime($date));
}

function formatDateShort($date) {
    return formatDate($date, 'M d, Y');
}

function timeAgo($datetime) {
    if (empty($datetime)) return '—';
    $time = time() - strtotime($datetime);

    if ($time < 1)      return 'Just now';
    if ($time < 60)     return $time . 's ago';
    if ($time < 3600)   return floor($time / 60) . 'm ago';
    if ($time < 86400)  return floor($time / 3600) . 'h ago';
    if ($time < 604800) return floor($time / 86400) . 'd ago';

    return date('M d, Y', strtotime($datetime));
}


function createNotification($pdo, $userId, $title, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    return $stmt->execute([$userId, $title, $message]);
}

function notifyAdmins($pdo, $title, $message) {
    $admins = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $admin) {
        createNotification($pdo, $admin['user_id'], $title, $message);
    }
}


function getStatusBadge($status) {
    $map = [
        'Pending'     => 'badge-warning',
        'Assigned'    => 'badge-info',
        'Accepted'    => 'badge-info',
        'In Progress' => 'badge-primary',
        'Resolved'    => 'badge-success',
        'Rejected'    => 'badge-danger',
        'Closed'      => 'badge-secondary',
    ];
    $class = $map[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . sanitize($status) . '</span>';
}

function getPriorityBadge($priority) {
    $map = [
        'Low'    => 'priority-low',
        'Medium' => 'priority-medium',
        'High'   => 'priority-high',
    ];
    $class = $map[$priority] ?? 'priority-medium';
    return '<span class="badge ' . $class . '">' . sanitize($priority) . '</span>';
}

function getAvailabilityBadge($availability) {
    $map = [
        'available' => 'badge-success',
        'busy'      => 'badge-warning',
        'offline'   => 'badge-secondary',
    ];
    $class = $map[$availability] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . ucfirst(sanitize($availability)) . '</span>';
}


function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function jsonSuccess($message = 'Success', $data = []) {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

function jsonError($message = 'Error', $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}


function getCategories($pdo) {
    return $pdo->query("SELECT * FROM complaint_categories ORDER BY category_name")->fetchAll();
}

function getBuildings($pdo) {
    return $pdo->query("SELECT * FROM buildings ORDER BY building_name")->fetchAll();
}

function getStatuses($pdo) {
    return $pdo->query("SELECT * FROM complaint_status ORDER BY status_id")->fetchAll();
}

function getAvailableStaff($pdo) {
    return $pdo->query(
        "SELECT ms.staff_id, u.name, ms.specialization, ms.availability, ms.employee_id
         FROM maintenance_staff ms 
         JOIN users u ON ms.user_id = u.user_id 
         WHERE u.status = 'active'
         ORDER BY ms.availability DESC, u.name"
    )->fetchAll();
}

function getStatusIdByName($pdo, $statusName) {
    $stmt = $pdo->prepare("SELECT status_id FROM complaint_status WHERE status_name = ?");
    $stmt->execute([$statusName]);
    return $stmt->fetchColumn();
}


function paginate($totalItems, $perPage = 10, $currentPage = 1) {
    $totalPages = max(1, ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total'       => $totalItems,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';

    $sep = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    $html = '<div class="pagination">';
    
    // Previous
    if ($pagination['has_prev']) {
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . ($pagination['current'] - 1) . '" class="page-btn"><i class="fas fa-chevron-left"></i></a>';
    }

    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i == $pagination['current']) {
            $html .= '<span class="page-btn active">' . $i . '</span>';
        } elseif (abs($i - $pagination['current']) <= 2 || $i == 1 || $i == $pagination['total_pages']) {
            $html .= '<a href="' . $baseUrl . $sep . 'page=' . $i . '" class="page-btn">' . $i . '</a>';
        } elseif (abs($i - $pagination['current']) == 3) {
            $html .= '<span class="page-dots">…</span>';
        }
    }

    // Next
    if ($pagination['has_next']) {
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . ($pagination['current'] + 1) . '" class="page-btn"><i class="fas fa-chevron-right"></i></a>';
    }

    $html .= '</div>';
    return $html;
}


function generateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRF() . '">';
}
