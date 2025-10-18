<?php

/**
 * Process fabrikasi progress update (via AJAX)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    $material_id = (int)$_POST['material_id'];
    $division = sanitize_input($_POST['division']);
    $status = sanitize_input($_POST['status']);
    $progress_percent = (float)$_POST['progress_percent'];
    $notes = sanitize_input($_POST['notes'] ?? '');
    $operator_name = sanitize_input($_POST['operator_name'] ?? '');
    $machine_used = sanitize_input($_POST['machine_used'] ?? '');

    // Validate
    if ($material_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
        exit;
    }

    if ($progress_percent < 0 || $progress_percent > 100) {
        echo json_encode(['success' => false, 'message' => 'Progress must be between 0-100%']);
        exit;
    }

    try {
        // Check if progress record exists
        $check_stmt = $conn->prepare("SELECT progress_id FROM material_progress WHERE material_id = ? AND division = ?");
        $check_stmt->bind_param("is", $material_id, $division);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();

        // Combine notes with additional info
        $full_notes = $notes;
        if (!empty($operator_name)) {
            $full_notes .= "\nOperator: " . $operator_name;
        }
        if (!empty($machine_used)) {
            $full_notes .= "\nMachine: " . $machine_used;
        }

        if ($exists) {
            // Update existing record
            $query = "UPDATE material_progress SET 
                     status = ?, 
                     progress_percent = ?, 
                     notes = ?, 
                     updated_by = ?,
                     updated_at = NOW()";

            // Set started_at if status changed to In Progress
            if ($status == 'In Progress') {
                $query .= ", started_at = IF(started_at IS NULL, NOW(), started_at)";
            }

            // Set completed_at if status changed to Completed
            if ($status == 'Completed') {
                $query .= ", completed_at = NOW()";
            } elseif ($status != 'Completed') {
                $query .= ", completed_at = NULL";
            }

            $query .= " WHERE material_id = ? AND division = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("sdsiis", $status, $progress_percent, $full_notes, $_SESSION['user_id'], $material_id, $division);
        } else {
            // Insert new record
            $started_at = $status == 'In Progress' ? 'NOW()' : 'NULL';
            $stmt = $conn->prepare("INSERT INTO material_progress 
                                   (material_id, division, status, progress_percent, notes, updated_by, started_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, $started_at)");
            $stmt->bind_param("issdsi", $material_id, $division, $status, $progress_percent, $full_notes, $_SESSION['user_id']);
        }

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
                'Update Fabrikasi Progress',
                "Update fabrikasi progress for '{$material['name']}' to {$progress_percent}% - {$status} (Material ID: {$material_id})"
            );

            // Jika status Completed, update progress Logistik ke Pending
            if ($status == 'Completed') {
                $logistik_stmt = $conn->prepare("UPDATE material_progress SET status = 'Pending' WHERE material_id = ? AND division = 'Logistik'");
                $logistik_stmt->bind_param("i", $material_id);
                $logistik_stmt->execute();
            }

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