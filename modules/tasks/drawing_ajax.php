<?php

/**
 * AJAX Handler untuk Engineering Drawings Management
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Start output buffering
ob_start();

try {
    require_login();

    $action = $_GET['action'] ?? '';

    if (empty($action)) {
        throw new Exception('No action specified');
    }

    $conn = getDBConnection();
    header('Content-Type: application/json');

    switch ($action) {
        case 'upload':
            handleDrawingUpload($conn);
            break;

        case 'list':
            getDrawingsList($conn);
            break;

        case 'download':
            downloadDrawing($conn);
            break;

        case 'view':
            viewDrawing($conn);
            break;

        case 'delete':
            deleteDrawing($conn);
            break;

        case 'update_status':
            updateDrawingStatus($conn);
            break;

        case 'get':
            getDrawingData($conn);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        closeDBConnection($conn);
    }
    ob_end_flush();
}

/**
 * Handle drawing file upload
 */
function handleDrawingUpload($conn)
{
    // Check permission
    if (!canManageMaterial()) {
        throw new Exception('Permission denied for uploading drawings');
    }

    // Validate file upload
    if (!isset($_FILES['drawing_file']) || $_FILES['drawing_file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'File upload error: ';
        switch ($_FILES['drawing_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg .= 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg .= 'File upload incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg .= 'No file selected';
                break;
            default:
                $error_msg .= 'Unknown error (' . $_FILES['drawing_file']['error'] . ')';
        }
        throw new Exception($error_msg);
    }

    $file = $_FILES['drawing_file'];

    // Validate file type
    $allowed_extensions = ['pdf', 'dwg', 'dxf', 'PDF', 'DWG', 'DXF'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        throw new Exception('Invalid file type. Allowed formats: PDF, DWG, DXF');
    }

    // Validate file size (20MB max)
    $max_size = 20 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum 20MB allowed');
    }

    if ($file['size'] == 0) {
        throw new Exception('File is empty');
    }

    // Create upload directory if not exists
    $upload_dir = '../../assets/uploads/drawings/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Cannot create upload directory');
        }
    }

    // Generate safe filename
    $original_name = $file['name'];
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
    $new_filename = uniqid() . '_' . $safe_name;
    $file_path = $upload_dir . $new_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Validate required form data
    $required_fields = ['pon_id', 'task_id', 'drawing_number', 'drawing_name'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            unlink($file_path); // Clean up file
            throw new Exception("Missing required field: " . $field);
        }
    }

    // Get or create Engineering task
    $task_id = getOrCreateEngineeringTask($conn, (int)$_POST['pon_id']);

    // Save to database
    $stmt = $conn->prepare("INSERT INTO engineering_drawings 
        (pon_id, task_id, drawing_number, drawing_name, revision, file_name, file_path, 
         file_size, file_type, upload_date, status, notes, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)");

    $revision = !empty($_POST['revision']) ? $_POST['revision'] : 'A';
    $status = !empty($_POST['status']) ? $_POST['status'] : 'Draft';
    $notes = !empty($_POST['notes']) ? $_POST['notes'] : '';

    $stmt->bind_param(
        "iisssssisssi",
        $_POST['pon_id'],
        $task_id,
        $_POST['drawing_number'],
        $_POST['drawing_name'],
        $revision,
        $original_name,
        $file_path,
        $file['size'],
        $file['type'],
        $status,
        $notes,
        $_SESSION['user_id']
    );

    if ($stmt->execute()) {
        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Upload Drawing',
            "Uploaded drawing: {$_POST['drawing_number']} - {$_POST['drawing_name']}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Drawing uploaded successfully',
            'drawing_id' => $stmt->insert_id
        ]);
    } else {
        unlink($file_path); // Delete file if DB insert fails
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get drawings list for a PON
 */
function getDrawingsList($conn)
{
    if (!isset($_GET['pon_id']) || empty($_GET['pon_id'])) {
        throw new Exception('PON ID required');
    }

    $pon_id = (int)$_GET['pon_id'];

    $query = "SELECT 
                ed.*,
                u.full_name as uploaded_by_name,
                a.full_name as approved_by_name
              FROM engineering_drawings ed
              LEFT JOIN users u ON ed.uploaded_by = u.user_id
              LEFT JOIN users a ON ed.approved_by = a.user_id
              WHERE ed.pon_id = ? 
              ORDER BY ed.drawing_number, ed.revision, ed.upload_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $drawings = [];
    while ($row = $result->fetch_assoc()) {
        // Format file size for display
        $row['file_size_mb'] = round($row['file_size'] / 1024 / 1024, 2);
        $row['file_type_icon'] = getFileTypeIcon($row['file_type']);
        $drawings[] = $row;
    }

    echo json_encode([
        'success' => true,
        'drawings' => $drawings,
        'count' => count($drawings)
    ]);
}

/**
 * Download drawing file
 */
function downloadDrawing($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Drawing ID required');
    }

    $drawing_id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM engineering_drawings WHERE drawing_id = ?");
    $stmt->bind_param("i", $drawing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $drawing = $result->fetch_assoc();

    if (!$drawing) {
        throw new Exception('Drawing not found');
    }

    if (!file_exists($drawing['file_path'])) {
        throw new Exception('File not found on server');
    }

    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $drawing['file_type']);
    header('Content-Disposition: attachment; filename="' . $drawing['file_name'] . '"');
    header('Content-Length: ' . $drawing['file_size']);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    // Log download activity
    log_activity(
        $conn,
        $_SESSION['user_id'],
        'Download Drawing',
        "Downloaded drawing: {$drawing['drawing_number']}"
    );

    readfile($drawing['file_path']);
    exit;
}

/**
 * View drawing file (inline in browser)
 */
function viewDrawing($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Drawing ID required');
    }

    $drawing_id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM engineering_drawings WHERE drawing_id = ?");
    $stmt->bind_param("i", $drawing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $drawing = $result->fetch_assoc();

    if (!$drawing) {
        throw new Exception('Drawing not found');
    }

    if (!file_exists($drawing['file_path'])) {
        throw new Exception('File not found on server');
    }

    // Only PDF can be viewed inline, others force download
    $file_ext = strtolower(pathinfo($drawing['file_name'], PATHINFO_EXTENSION));

    if ($file_ext === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $drawing['file_name'] . '"');
    } else {
        // DWG, DXF force download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $drawing['file_name'] . '"');
    }

    header('Content-Length: ' . $drawing['file_size']);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Log view activity
    log_activity(
        $conn,
        $_SESSION['user_id'],
        'View Drawing',
        "Viewed drawing: {$drawing['drawing_number']}"
    );

    readfile($drawing['file_path']);
    exit;
}

/**
 * Delete drawing
 */
function deleteDrawing($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Invalid request method');
    }

    if (!canManageMaterial()) {
        throw new Exception('Permission denied for deleting drawings');
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Drawing ID required');
    }

    $drawing_id = (int)$_GET['id'];

    // Get drawing info before deletion
    $stmt = $conn->prepare("SELECT * FROM engineering_drawings WHERE drawing_id = ?");
    $stmt->bind_param("i", $drawing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $drawing = $result->fetch_assoc();

    if (!$drawing) {
        throw new Exception('Drawing not found');
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM engineering_drawings WHERE drawing_id = ?");
    $stmt->bind_param("i", $drawing_id);

    if ($stmt->execute()) {
        // Delete physical file
        if (file_exists($drawing['file_path'])) {
            unlink($drawing['file_path']);
        }

        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Delete Drawing',
            "Deleted drawing: {$drawing['drawing_number']} - {$drawing['drawing_name']}"
        );

        echo json_encode(['success' => true, 'message' => 'Drawing deleted successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Update drawing status
 */
function updateDrawingStatus($conn)
{
    if (!canManageMaterial()) {
        throw new Exception('Permission denied for updating drawings');
    }

    $required_fields = ['drawing_id', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $drawing_id = (int)$_POST['drawing_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';

    $stmt = $conn->prepare("UPDATE engineering_drawings 
                           SET status = ?, notes = ?, approved_by = ?, approved_date = CURDATE() 
                           WHERE drawing_id = ?");

    $approved_by = ($status === 'Approved') ? $_SESSION['user_id'] : null;

    $stmt->bind_param("ssii", $status, $notes, $approved_by, $drawing_id);

    if ($stmt->execute()) {
        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Update Drawing Status',
            "Updated drawing status to: {$status}"
        );

        echo json_encode(['success' => true, 'message' => 'Drawing status updated successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Helper function to get file type icon
 */
function getFileTypeIcon($file_type)
{
    if (strpos($file_type, 'pdf') !== false) {
        return 'fas fa-file-pdf';
    } elseif (strpos($file_type, 'dwg') !== false || strpos($file_type, 'dxf') !== false) {
        return 'fas fa-file-code';
    } else {
        return 'fas fa-file';
    }
}

/**
 * Get or create Engineering task (reuse from material_ajax)
 */
function getOrCreateEngineeringTask($conn, $pon_id)
{
    // Cari task Engineering yang sudah ada
    $stmt = $conn->prepare("SELECT task_id FROM tasks WHERE pon_id = ? AND responsible_division = 'Engineering' LIMIT 1");
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $task = $result->fetch_assoc();
        return $task['task_id'];
    }

    // Jika tidak ada, buat task Engineering baru
    $task_name = "Engineering Design & Drawing Management";
    $description = "Engineering drawing upload and management";
    $start_date = date('Y-m-d');
    $finish_date = date('Y-m-d', strtotime('+30 days'));
    $progress = 0.00;
    $status = 'In Progress';

    $stmt = $conn->prepare("INSERT INTO tasks (
        pon_id, phase, responsible_division, task_name, description, 
        start_date, finish_date, progress, status
    ) VALUES (?, 'Engineering', 'Engineering', ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "isssdss",
        $pon_id,
        $task_name,
        $description,
        $start_date,
        $finish_date,
        $progress,
        $status
    );

    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        throw new Exception('Failed to create Engineering task: ' . $stmt->error);
    }
}

/**
 * Get single drawing data
 */
function getDrawingData($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Drawing ID required');
    }

    $drawing_id = (int)$_GET['id'];

    $query = "SELECT 
                ed.*,
                u.full_name as uploaded_by_name,
                a.full_name as approved_by_name
              FROM engineering_drawings ed
              LEFT JOIN users u ON ed.uploaded_by = u.user_id
              LEFT JOIN users a ON ed.approved_by = a.user_id
              WHERE ed.drawing_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $drawing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $drawing = $result->fetch_assoc();

    if (!$drawing) {
        throw new Exception('Drawing not found');
    }

    // Format file size for display
    $drawing['file_size_mb'] = round($drawing['file_size'] / 1024 / 1024, 2);
    $drawing['file_type_icon'] = getFileTypeIcon($drawing['file_type']);

    echo json_encode([
        'success' => true,
        'drawing' => $drawing
    ]);
}
?>