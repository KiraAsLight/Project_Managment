<?php

/**
 * ============================================
 * AJAX Handler untuk Fabrication Progress
 * COMPLETE IMPLEMENTATION dengan Role Check & Validation
 * ============================================
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$action = $_GET['action'];
$conn = getDBConnection();

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'update_material_progress':
            updateMaterialFabricationProgress($conn);
            break;

        case 'get_fabrication_materials':
            getFabricationMaterials($conn);
            break;

        case 'start_fabrication':
            startFabricationProcess($conn);
            break;

        case 'complete_fabrication':
            completeFabricationProcess($conn);
            break;

        case 'get_production_tracking':
            getProductionTrackingData($conn);
            break;

        case 'log_workshop_activity':
            logWorkshopActivity($conn);
            break;

        case 'generate_fabrication_report':
            generateFabricationReport($conn);
            break;

        case 'get_material_history':
            getMaterialHistory($conn);
            break;

        case 'update_qc_checklist':
            updateQCChecklist($conn);
            break;

        case 'get_qc_checklist':
            getQCChecklist($conn);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    closeDBConnection($conn);
}

/**
 * ============================================
 * UPDATE MATERIAL FABRICATION PROGRESS
 * ============================================
 */
function updateMaterialFabricationProgress($conn)
{
    // PERMISSION CHECK
    if (!canManageFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk mengupdate fabrication");
    }

    // VALIDATION
    if (!isset($_POST['material_id']) || !isset($_POST['progress']) || !isset($_POST['fabrication_status'])) {
        throw new Exception("Data material tidak lengkap");
    }

    $material_id = (int)$_POST['material_id'];
    $progress_percent = (float)$_POST['progress'];
    $status = trim($_POST['fabrication_status']);
    $fabrication_phase = trim($_POST['fabrication_phase'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $qc_status = trim($_POST['qc_status'] ?? 'Pending');
    $workstation = trim($_POST['workstation'] ?? 'Main Workshop');
    $shift = trim($_POST['shift'] ?? 'Shift 1');

    // VALIDATE PROGRESS
    if (!validateFabricationProgress($progress_percent)) {
        throw new Exception("Progress harus antara 0-100%");
    }

    // Auto-set phase if not provided
    if (empty($fabrication_phase)) {
        $fabrication_phase = getFabricationPhaseByProgress($progress_percent);
    }

    // Check if material exists
    $material_check = $conn->prepare("
        SELECT ml.material_id, ml.name, ml.pon_id, mo.status as order_status 
        FROM material_lists ml 
        LEFT JOIN material_orders mo ON ml.material_id = mo.material_id 
        WHERE ml.material_id = ?
    ");
    $material_check->bind_param("i", $material_id);
    $material_check->execute();
    $material_data = $material_check->get_result()->fetch_assoc();

    if (!$material_data) {
        throw new Exception("Material tidak ditemukan");
    }

    // Check if material sudah di-order (optional warning, tidak blocking)
    $order_warning = '';
    if (!$material_data['order_status'] || $material_data['order_status'] == 'Cancelled') {
        $order_warning = ' (Warning: Material belum di-order)';
    }

    // Get current progress untuk history
    $current_stmt = $conn->prepare("SELECT progress_percent, status FROM material_progress WHERE material_id = ? AND division = 'Fabrikasi'");
    $current_stmt->bind_param("i", $material_id);
    $current_stmt->execute();
    $current_data = $current_stmt->get_result()->fetch_assoc();

    $progress_from = $current_data['progress_percent'] ?? 0.00;
    $status_from = $current_data['status'] ?? 'Pending';

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check if fabrication progress already exists
        $check_stmt = $conn->prepare("SELECT progress_id FROM material_progress WHERE material_id = ? AND division = 'Fabrikasi'");
        $check_stmt->bind_param("i", $material_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();

        $current_time = date('Y-m-d H:i:s');

        if ($exists) {
            // UPDATE existing progress
            $stmt = $conn->prepare("UPDATE material_progress SET 
                                    status = ?, 
                                    progress_percent = ?, 
                                    fabrication_phase = ?,
                                    qc_status = ?,
                                    workstation = ?,
                                    shift = ?,
                                    notes = CONCAT(IFNULL(notes, ''), ?),
                                    updated_by = ?,
                                    updated_at = NOW(),
                                    completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE completed_at END
                                    WHERE material_id = ? AND division = 'Fabrikasi'");

            $update_notes = "\n\n--- Update " . $current_time . " ---\n" .
                "Phase: " . $fabrication_phase . "\n" .
                "Progress: " . $progress_percent . "%\n" .
                "Status: " . $status . "\n" .
                "QC: " . $qc_status . "\n" .
                "Workstation: " . $workstation . " | " . $shift . "\n" .
                "Notes: " . $notes;

            $stmt->bind_param(
                "sdssssssi",
                $status,
                $progress_percent,
                $fabrication_phase,
                $qc_status,
                $workstation,
                $shift,
                $update_notes,
                $_SESSION['user_id'],
                $status,
                $material_id
            );
        } else {
            // INSERT new fabrication progress
            $stmt = $conn->prepare("INSERT INTO material_progress 
                                   (material_id, division, status, progress_percent, fabrication_phase, qc_status, workstation, shift, notes, started_at, updated_by) 
                                   VALUES (?, 'Fabrikasi', ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

            $new_notes = "🎯 FABRICATION STARTED\n" .
                "Material: " . $material_data['name'] . "\n" .
                "Phase: " . $fabrication_phase . "\n" .
                "Initial Progress: " . $progress_percent . "%\n" .
                "QC Status: " . $qc_status . "\n" .
                "Workstation: " . $workstation . " | " . $shift . "\n" .
                "Notes: " . $notes;

            $stmt->bind_param(
                "isdssssi",
                $material_id,
                $status,
                $progress_percent,
                $fabrication_phase,
                $qc_status,
                $workstation,
                $shift,
                $new_notes,
                $_SESSION['user_id']
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Gagal update progress fabrication");
        }

        // Log fabrication history
        logFabricationHistory(
            $conn,
            $material_id,
            $progress_from,
            $progress_percent,
            $status_from,
            $status,
            $fabrication_phase,
            $qc_status,
            $notes
        );

        // Commit transaction
        $conn->commit();

        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Update Fabrication',
            'Updated fabrication progress for material: ' . $material_data['name'] . ' to ' . $progress_percent . '%'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Progress fabrication berhasil diupdate' . $order_warning,
            'data' => [
                'material_id' => $material_id,
                'progress' => $progress_percent,
                'status' => $status,
                'phase' => $fabrication_phase
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * ============================================
 * GET FABRICATION MATERIALS
 * ============================================
 */
function getFabricationMaterials($conn)
{
    // PERMISSION CHECK
    if (!canViewFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk melihat data fabrication");
    }

    if (!isset($_GET['pon_id'])) {
        throw new Exception("PON ID required");
    }

    $pon_id = (int)$_GET['pon_id'];

    $query = "SELECT 
                ml.material_id,
                ml.assy_marking,
                ml.name as material_name,
                ml.quantity,
                ml.dimensions,
                ml.length_mm,
                ml.weight_kg,
                ml.total_weight_kg,
                ml.remarks,
                ml.created_at,
                COALESCE(mp.status, 'Pending') as fabrication_status,
                COALESCE(mp.progress_percent, 0) as fabrication_progress,
                mp.fabrication_phase,
                mp.qc_status,
                mp.workstation,
                mp.shift,
                mp.notes as fabrication_notes,
                mp.started_at,
                mp.completed_at,
                mp.updated_at as last_updated,
                mo.status as order_status,
                mo.supplier_name,
                u.full_name as updated_by_name
              FROM material_lists ml
              LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
              LEFT JOIN material_orders mo ON ml.material_id = mo.material_id
              LEFT JOIN users u ON mp.updated_by = u.user_id
              WHERE ml.pon_id = ?
              ORDER BY 
                CASE COALESCE(mp.status, 'Pending') 
                    WHEN 'In Progress' THEN 1
                    WHEN 'Pending' THEN 2
                    WHEN 'Rejected' THEN 3
                    WHEN 'Completed' THEN 4
                    ELSE 5
                END,
                ml.assy_marking";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }

    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'can_manage' => canManageFabrication()
    ]);
}

/**
 * ============================================
 * START FABRICATION PROCESS
 * ============================================
 */
function startFabricationProcess($conn)
{
    // PERMISSION CHECK
    if (!canManageFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk memulai fabrication");
    }

    if (!isset($_POST['material_id'])) {
        throw new Exception("Material ID required");
    }

    $material_id = (int)$_POST['material_id'];
    $fabrication_phase = trim($_POST['fabrication_phase'] ?? 'Material Preparation');
    $notes = trim($_POST['notes'] ?? 'Fabrication process started');

    // Check if already exists
    $check_stmt = $conn->prepare("SELECT progress_id, status FROM material_progress WHERE material_id = ? AND division = 'Fabrikasi'");
    $check_stmt->bind_param("i", $material_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();

    if ($exists && $exists['status'] !== 'Pending') {
        throw new Exception("Fabrication sudah dimulai untuk material ini (Status: " . $exists['status'] . ")");
    }

    // Get material info
    $material_stmt = $conn->prepare("SELECT name, pon_id FROM material_lists WHERE material_id = ?");
    $material_stmt->bind_param("i", $material_id);
    $material_stmt->execute();
    $material_data = $material_stmt->get_result()->fetch_assoc();

    if (!$material_data) {
        throw new Exception("Material tidak ditemukan");
    }

    $conn->begin_transaction();

    try {
        if ($exists) {
            // Update dari Pending ke In Progress
            $stmt = $conn->prepare("UPDATE material_progress SET 
                                   status = 'In Progress',
                                   progress_percent = 5.00,
                                   fabrication_phase = ?,
                                   workstation = 'Main Workshop',
                                   shift = 'Shift 1',
                                   notes = CONCAT(IFNULL(notes, ''), ?),
                                   started_at = NOW(),
                                   updated_by = ?,
                                   updated_at = NOW()
                                   WHERE material_id = ? AND division = 'Fabrikasi'");

            $start_notes = "\n\n🚀 FABRICATION PROCESS STARTED\n" .
                "Start Time: " . date('Y-m-d H:i:s') . "\n" .
                "Material: " . $material_data['name'] . "\n" .
                "Initial Phase: " . $fabrication_phase . "\n" .
                "Notes: " . $notes;

            $stmt->bind_param("ssii", $fabrication_phase, $start_notes, $_SESSION['user_id'], $material_id);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO material_progress 
                                   (material_id, division, status, progress_percent, fabrication_phase, qc_status, workstation, shift, notes, started_at, updated_by) 
                                   VALUES (?, 'Fabrikasi', 'In Progress', 5.00, ?, 'Pending', 'Main Workshop', 'Shift 1', ?, NOW(), ?)");

            $start_notes = "🚀 FABRICATION PROCESS STARTED\n" .
                "Material: " . $material_data['name'] . "\n" .
                "Initial Phase: " . $fabrication_phase . "\n" .
                "Notes: " . $notes;

            $stmt->bind_param("issi", $material_id, $fabrication_phase, $start_notes, $_SESSION['user_id']);
        }

        if (!$stmt->execute()) {
            throw new Exception("Failed to start fabrication process");
        }

        // Log history
        logFabricationHistory($conn, $material_id, 0, 5, 'Pending', 'In Progress', $fabrication_phase, 'Pending', $notes);

        $conn->commit();

        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Start Fabrication',
            'Started fabrication for material: ' . $material_data['name']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Fabrication process berhasil dimulai',
            'data' => [
                'material_id' => $material_id,
                'status' => 'In Progress',
                'progress' => 5.00
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * ============================================
 * COMPLETE FABRICATION PROCESS
 * ============================================
 */
function completeFabricationProcess($conn)
{
    // PERMISSION CHECK
    if (!canManageFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk menyelesaikan fabrication");
    }

    if (!isset($_POST['material_id'])) {
        throw new Exception("Material ID required");
    }

    $material_id = (int)$_POST['material_id'];
    $final_notes = trim($_POST['notes'] ?? 'Fabrication completed');
    $qc_status = trim($_POST['qc_status'] ?? 'Passed');

    // Get current progress
    $current_stmt = $conn->prepare("SELECT progress_percent, status, fabrication_phase FROM material_progress WHERE material_id = ? AND division = 'Fabrikasi'");
    $current_stmt->bind_param("i", $material_id);
    $current_stmt->execute();
    $current_data = $current_stmt->get_result()->fetch_assoc();

    if (!$current_data) {
        throw new Exception("Fabrication belum dimulai untuk material ini");
    }

    if ($current_data['status'] === 'Completed') {
        throw new Exception("Fabrication sudah completed sebelumnya");
    }

    // Get material info
    $material_stmt = $conn->prepare("SELECT name FROM material_lists WHERE material_id = ?");
    $material_stmt->bind_param("i", $material_id);
    $material_stmt->execute();
    $material_data = $material_stmt->get_result()->fetch_assoc();

    if (!$material_data) {
        throw new Exception("Material tidak ditemukan");
    }

    $conn->begin_transaction();

    try {
        // Complete fabrication
        $stmt = $conn->prepare("UPDATE material_progress SET 
                                status = 'Completed', 
                                progress_percent = 100.00, 
                                fabrication_phase = 'Final Assembly & Finishing',
                                qc_status = ?,
                                notes = CONCAT(IFNULL(notes, ''), ?),
                                completed_at = NOW(),
                                updated_by = ?,
                                updated_at = NOW()
                                WHERE material_id = ? AND division = 'Fabrikasi'");

        $completion_notes = "\n\n🎉 FABRICATION COMPLETED\n" .
            "Completion Time: " . date('Y-m-d H:i:s') . "\n" .
            "QC Status: " . $qc_status . "\n" .
            "Final Notes: " . $final_notes;

        $stmt->bind_param("ssii", $qc_status, $completion_notes, $_SESSION['user_id'], $material_id);

        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            throw new Exception("Failed to complete fabrication process");
        }

        // Log history
        logFabricationHistory(
            $conn,
            $material_id,
            $current_data['progress_percent'],
            100,
            $current_data['status'],
            'Completed',
            'Final Assembly & Finishing',
            $qc_status,
            $final_notes
        );

        $conn->commit();

        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Complete Fabrication',
            'Completed fabrication for material: ' . $material_data['name'] . ' (QC: ' . $qc_status . ')'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Fabrication process berhasil diselesaikan',
            'data' => [
                'material_id' => $material_id,
                'status' => 'Completed',
                'progress' => 100.00,
                'qc_status' => $qc_status
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * ============================================
 * GET PRODUCTION TRACKING DATA
 * ============================================
 */
function getProductionTrackingData($conn)
{
    if (!canViewFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk melihat production tracking");
    }

    if (!isset($_GET['pon_id'])) {
        throw new Exception("PON ID required");
    }

    $pon_id = (int)$_GET['pon_id'];

    $query = "SELECT 
                ml.material_id,
                ml.assy_marking,
                ml.name as material_name,
                ml.quantity,
                ml.total_weight_kg,
                mp.fabrication_phase,
                mp.progress_percent,
                mp.status,
                mp.workstation,
                mp.shift,
                mp.started_at,
                mp.completed_at,
                mp.updated_at,
                u.full_name as operator_name,
                TIMESTAMPDIFF(HOUR, mp.started_at, COALESCE(mp.completed_at, NOW())) as hours_in_production
              FROM material_lists ml
              INNER JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
              LEFT JOIN users u ON mp.updated_by = u.user_id
              WHERE ml.pon_id = ? AND mp.status = 'In Progress'
              ORDER BY mp.updated_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $production_data = [];
    while ($row = $result->fetch_assoc()) {
        $production_data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'production_data' => $production_data
    ]);
}

/**
 * ============================================
 * LOG WORKSHOP ACTIVITY
 * ============================================
 */
function logWorkshopActivity($conn)
{
    if (!canManageFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk log workshop activity");
    }

    if (!isset($_POST['material_id']) || !isset($_POST['activity_type'])) {
        throw new Exception("Data aktivitas tidak lengkap");
    }

    $material_id = (int)$_POST['material_id'];
    $activity_type = trim($_POST['activity_type']);
    $workstation = trim($_POST['workstation'] ?? 'Main Workshop');
    $shift = trim($_POST['shift'] ?? 'Shift 1');
    $notes = trim($_POST['notes'] ?? '');
    $operator_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO workshop_activities 
                           (material_id, activity_type, workstation, shift, notes, operator_id) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $material_id, $activity_type, $workstation, $shift, $notes, $operator_id);

    if ($stmt->execute()) {
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Workshop Activity',
            'Logged: ' . $activity_type . ' for material ID: ' . $material_id
        );
        echo json_encode(['success' => true, 'message' => 'Aktivitas workshop berhasil dicatat']);
    } else {
        throw new Exception("Gagal mencatat aktivitas workshop");
    }
}

/**
 * ============================================
 * GENERATE FABRICATION REPORT
 * ============================================
 */
function generateFabricationReport($conn)
{
    if (!canViewFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk generate report");
    }

    if (!isset($_GET['pon_id'])) {
        throw new Exception("PON ID required");
    }

    $pon_id = (int)$_GET['pon_id'];

    // Summary statistics
    $query = "SELECT 
                COUNT(ml.material_id) as total_materials,
                SUM(ml.total_weight_kg) as total_weight,
                COUNT(CASE WHEN mp.status = 'Completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN mp.status = 'In Progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN COALESCE(mp.status, 'Pending') = 'Pending' THEN 1 END) as pending_count,
                AVG(mp.progress_percent) as avg_progress,
                MIN(mp.started_at) as fabrication_start,
                MAX(mp.completed_at) as fabrication_end,
                COUNT(DISTINCT mp.updated_by) as total_operators,
                AVG(TIMESTAMPDIFF(DAY, mp.started_at, COALESCE(mp.completed_at, NOW()))) as avg_duration
              FROM material_lists ml
              LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
              WHERE ml.pon_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report_data = $result->fetch_assoc();

    // Material details
    $details_query = "SELECT 
                        ml.material_id,
                        ml.assy_marking,
                        ml.name as material_name,
                        ml.quantity,
                        ml.total_weight_kg,
                        COALESCE(mp.status, 'Pending') as status,
                        COALESCE(mp.progress_percent, 0) as progress_percent,
                        mp.fabrication_phase,
                        mp.qc_status,
                        mp.started_at,
                        mp.completed_at,
                        DATEDIFF(COALESCE(mp.completed_at, NOW()), mp.started_at) as days_in_production
                      FROM material_lists ml
                      LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
                      WHERE ml.pon_id = ?
                      ORDER BY 
                        CASE COALESCE(mp.status, 'Pending')
                            WHEN 'Completed' THEN 1
                            WHEN 'In Progress' THEN 2
                            ELSE 3
                        END,
                        mp.progress_percent DESC";

    $details_stmt = $conn->prepare($details_query);
    $details_stmt->bind_param("i", $pon_id);
    $details_stmt->execute();
    $details_result = $details_stmt->get_result();

    $material_details = [];
    while ($row = $details_result->fetch_assoc()) {
        $material_details[] = $row;
    }

    echo json_encode([
        'success' => true,
        'report' => $report_data,
        'material_details' => $material_details
    ]);
}

/**
 * ============================================
 * GET MATERIAL HISTORY
 * ============================================
 */
function getMaterialHistory($conn)
{
    if (!canViewFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk melihat history");
    }

    if (!isset($_GET['material_id'])) {
        throw new Exception("Material ID required");
    }

    $material_id = (int)$_GET['material_id'];

    $query = "SELECT 
                fh.*,
                u.full_name as updated_by_name
              FROM fabrication_history fh
              LEFT JOIN users u ON fh.updated_by = u.user_id
              WHERE fh.material_id = ?
              ORDER BY fh.created_at DESC
              LIMIT 50";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
}

/**
 * ============================================
 * UPDATE QC CHECKLIST
 * ============================================
 */
function updateQCChecklist($conn)
{
    if (!canManageQC()) {
        throw new Exception("Anda tidak memiliki akses untuk update QC checklist");
    }

    if (!isset($_POST['material_id']) || !isset($_POST['check_item']) || !isset($_POST['check_result'])) {
        throw new Exception("Data QC checklist tidak lengkap");
    }

    $material_id = (int)$_POST['material_id'];
    $check_item = trim($_POST['check_item']);
    $check_result = trim($_POST['check_result']); // Pass, Fail, N/A
    $remarks = trim($_POST['remarks'] ?? '');
    $inspector_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO qc_checklist 
                           (material_id, check_item, check_result, inspector_id, inspection_date, remarks) 
                           VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("issis", $material_id, $check_item, $check_result, $inspector_id, $remarks);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'QC checklist berhasil diupdate']);
    } else {
        throw new Exception("Gagal update QC checklist");
    }
}

/**
 * ============================================
 * GET QC CHECKLIST
 * ============================================
 */
function getQCChecklist($conn)
{
    if (!canViewFabrication()) {
        throw new Exception("Anda tidak memiliki akses untuk melihat QC checklist");
    }

    if (!isset($_GET['material_id'])) {
        throw new Exception("Material ID required");
    }

    $material_id = (int)$_GET['material_id'];

    $query = "SELECT 
                qc.*,
                u.full_name as inspector_name
              FROM qc_checklist qc
              LEFT JOIN users u ON qc.inspector_id = u.user_id
              WHERE qc.material_id = ?
              ORDER BY qc.inspection_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $checklist = [];
    while ($row = $result->fetch_assoc()) {
        $checklist[] = $row;
    }

    echo json_encode([
        'success' => true,
        'checklist' => $checklist
    ]);
}
?>