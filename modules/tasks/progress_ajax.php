<?php

/**
 * AJAX Handler untuk Task Progress Updates
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

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
        case 'get':
            getTaskData($conn);
            break;

        case 'update':
            updateTaskProgress($conn);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) closeDBConnection($conn);
    ob_end_flush();
}

/**
 * Get task data for progress update
 */
function getTaskData($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Task ID required');
    }

    $task_id = (int)$_GET['id'];

    $query = "SELECT 
                t.*,
                p.pon_number,
                p.project_name
              FROM tasks t
              JOIN pon p ON t.pon_id = p.pon_id
              WHERE t.task_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();

    if (!$task) {
        throw new Exception('Task not found');
    }

    echo json_encode(['success' => true, 'task' => $task]);
}

/**
 * Update task progress - VERSION FIXED
 */
function updateTaskProgress($conn)
{
    // Check permission
    if (!canManageMaterial()) {
        throw new Exception('Permission denied for updating tasks');
    }

    // Validate required fields
    $required_fields = ['task_id', 'progress', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $task_id = (int)$_POST['task_id'];
    $progress = (float)$_POST['progress'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';

    // Validate progress range
    if ($progress < 0 || $progress > 100) {
        throw new Exception('Progress must be between 0 and 100');
    }

    // Build progress note with timestamp
    $progress_note = "\n\n--- Progress Update " . date('Y-m-d H:i') . " ---\n";
    $progress_note .= "Progress: " . $progress . "%\n";
    $progress_note .= "Status: " . $status . "\n";
    if (!empty($notes)) {
        $progress_note .= "Notes: " . $notes . "\n";
    }

    // Check current task state
    $check_stmt = $conn->prepare("SELECT actual_start_date, notes FROM tasks WHERE task_id = ?");
    $check_stmt->bind_param("i", $task_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $current_task = $check_result->fetch_assoc();

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];
    $types = "";

    // Basic updates
    $updates[] = "progress = ?";
    $params[] = $progress;
    $types .= "d";

    $updates[] = "status = ?";
    $params[] = $status;
    $types .= "s";

    // Handle notes concatenation
    $updates[] = "notes = CONCAT(IFNULL(notes, ''), ?)";
    $params[] = $progress_note;
    $types .= "s";

    $updates[] = "updated_at = NOW()";

    // Auto-set actual_start_date if progress > 0 and wasn't started before
    if ($progress > 0 && empty($current_task['actual_start_date'])) {
        $updates[] = "actual_start_date = CURDATE()";
    }

    // Auto-set actual_finish_date if progress is 100%
    if ($progress == 100 && $status == 'Completed') {
        $updates[] = "actual_finish_date = CURDATE()";
    }

    // Add task_id for WHERE clause
    $params[] = $task_id;
    $types .= "i";

    // Build final query
    $query = "UPDATE tasks SET " . implode(", ", $updates) . " WHERE task_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Log activity
        $task_info = getTaskInfo($conn, $task_id);
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Update Progress',
            "Updated task progress: {$task_info['task_name']} to {$progress}% - {$status}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Progress updated successfully',
            'progress' => $progress,
            'status' => $status
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get task info for logging
 */
function getTaskInfo($conn, $task_id)
{
    $stmt = $conn->prepare("SELECT task_name, pon_id FROM tasks WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>