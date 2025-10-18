<?php

/**
 * Process new task creation
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    // Collect and sanitize data
    $pon_id = (int)$_POST['pon_id'];
    $division = sanitize_input($_POST['responsible_division']);
    $task_name = sanitize_input($_POST['task_name']);
    $phase = sanitize_input($_POST['phase']);
    $description = sanitize_input($_POST['description']);
    $start_date = sanitize_input($_POST['start_date']);
    $finish_date = sanitize_input($_POST['finish_date']);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $pic_internal = sanitize_input($_POST['pic_internal']);

    // Insert new task
    $query = "INSERT INTO tasks (pon_id, phase, responsible_division, task_name, description, 
                                pic_internal, assigned_to, start_date, finish_date, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "isssssiss",
        $pon_id,
        $phase,
        $division,
        $task_name,
        $description,
        $pic_internal,
        $assigned_to,
        $start_date,
        $finish_date
    );

    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;

        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Create Task',
            "Membuat task '$task_name' untuk divisi $division (Task ID: $task_id)"
        );

        echo json_encode(['success' => true, 'task_id' => $task_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }

    closeDBConnection($conn);
}

?>