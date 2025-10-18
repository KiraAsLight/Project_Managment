<?php
// modules/material/material_update.php
/**
 * Process material updates
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Turn off output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_login();

if (!hasAnyRole(['Admin', 'Engineering'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$conn = getDBConnection();

try {
    if (!isset($_POST['material_id'])) {
        throw new Exception("Material ID is required");
    }

    $material_id = (int)$_POST['material_id'];

    // Validate material exists and user has access
    $check_stmt = $conn->prepare("SELECT ml.* FROM material_lists ml WHERE ml.material_id = ?");
    $check_stmt->bind_param("i", $material_id);
    $check_stmt->execute();
    $material = $check_stmt->get_result()->fetch_assoc();

    if (!$material) {
        throw new Exception("Material tidak ditemukan");
    }

    // Update material_lists
    $update_stmt = $conn->prepare("UPDATE material_lists 
                                  SET name = ?, assy_marking = ?, rv = ?, quantity = ?, 
                                      dimensions = ?, length_mm = ?, weight_kg = ?, 
                                      total_weight_kg = ?, remarks = ?
                                  WHERE material_id = ?");

    $name = $_POST['name'];
    $assy_marking = $_POST['assy_marking'] ?: null;
    $rv = $_POST['rv'] ?: null;
    $quantity = (int)$_POST['quantity'];
    $dimensions = $_POST['dimensions'] ?: null;
    $length_mm = $_POST['length_mm'] ? (float)$_POST['length_mm'] : null;
    $weight_kg = (float)$_POST['weight_kg'];
    $total_weight_kg = $quantity * $weight_kg;
    $remarks = $_POST['remarks'] ?: null;

    $update_stmt->bind_param(
        "sssisddddsi",
        $name,
        $assy_marking,
        $rv,
        $quantity,
        $dimensions,
        $length_mm,
        $weight_kg,
        $total_weight_kg,
        $remarks,
        $material_id
    );

    if (!$update_stmt->execute()) {
        throw new Exception("Gagal update material: " . $update_stmt->error);
    }

    // Update engineering progress
    if (isset($_POST['eng_status']) || isset($_POST['eng_progress']) || isset($_POST['eng_notes'])) {
        $progress_stmt = $conn->prepare("INSERT INTO material_progress 
                                        (material_id, division, status, progress_percent, notes, updated_by) 
                                        VALUES (?, 'Engineering', ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE 
                                        status = VALUES(status), 
                                        progress_percent = VALUES(progress_percent),
                                        notes = VALUES(notes),
                                        updated_by = VALUES(updated_by),
                                        updated_at = CURRENT_TIMESTAMP");

        $eng_status = $_POST['eng_status'] ?? 'Pending';
        $eng_progress = $_POST['eng_progress'] ? (float)$_POST['eng_progress'] : 0;
        $eng_notes = $_POST['eng_notes'] ?: null;

        $progress_stmt->bind_param("isdsi", $material_id, $eng_status, $eng_progress, $eng_notes, $_SESSION['user_id']);
        $progress_stmt->execute();
    }

    // Log activity
    log_activity(
        $conn,
        $_SESSION['user_id'],
        'Update Material',
        "Update material '{$name}' (ID: {$material_id})"
    );

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Material berhasil diupdate'
    ]);
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>