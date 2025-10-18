<?php

/**
 * Process progress update (via AJAX)
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
            $stmt->bind_param("sdsiis", $status, $progress_percent, $notes, $_SESSION['user_id'], $material_id, $division);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO material_progress 
                                   (material_id, division, status, progress_percent, notes, updated_by, started_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issdsi", $material_id, $division, $status, $progress_percent, $notes, $_SESSION['user_id']);
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
                'Update Procurement Progress',
                "Update procurement progress for '{$material['name']}' to {$progress_percent}% - {$status} (Material ID: {$material_id})"
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

exit;