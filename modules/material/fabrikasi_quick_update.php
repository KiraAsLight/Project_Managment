<?php

/**
 * Quick update untuk fabrikasi (via AJAX)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    $material_id = (int)$_POST['material_id'];
    $status = sanitize_input($_POST['status']);
    $progress_percent = (float)$_POST['progress_percent'];
    $division = sanitize_input($_POST['division']);

    // Validate
    if ($material_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
        exit;
    }

    try {
        $query = "UPDATE material_progress SET 
                 status = ?, 
                 progress_percent = ?, 
                 updated_by = ?,
                 updated_at = NOW()";

        if ($status == 'In Progress') {
            $query .= ", started_at = IF(started_at IS NULL, NOW(), started_at)";
        }

        if ($status == 'Completed') {
            $query .= ", completed_at = NOW()";

            // Also update Logistik status to Pending
            $logistik_stmt = $conn->prepare("UPDATE material_progress SET status = 'Pending' WHERE material_id = ? AND division = 'Logistik'");
            $logistik_stmt->bind_param("i", $material_id);
            $logistik_stmt->execute();
        }

        $query .= " WHERE material_id = ? AND division = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdis", $status, $progress_percent, $_SESSION['user_id'], $material_id, $division);

        if ($stmt->execute()) {
            // Get material info for logging
            $material_stmt = $conn->prepare("SELECT name FROM material_lists WHERE material_id = ?");
            $material_stmt->bind_param("i", $material_id);
            $material_stmt->execute();
            $material = $material_stmt->get_result()->fetch_assoc();

            // Log activity
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'Quick Update Fabrikasi',
                "Quick update fabrikasi for '{$material['name']}' to {$status} (Material ID: {$material_id})"
            );

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    closeDBConnection($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

?>