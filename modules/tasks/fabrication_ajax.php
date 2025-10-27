<?php

/**
 * AJAX Handler untuk Fabrication Progress - FOKUS MATERIAL PROGRESS
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['action'])) {
    die("Invalid action");
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

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function updateMaterialFabricationProgress($conn)
{
    if (!isset($_POST['material_id']) || !isset($_POST['progress_percent']) || !isset($_POST['status'])) {
        throw new Exception("Data material tidak lengkap");
    }

    $material_id = (int)$_POST['material_id'];
    $progress_percent = (float)$_POST['progress_percent'];
    $status = $_POST['status'];
    $fabrication_phase = $_POST['fabrication_phase'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $qc_status = $_POST['qc_status'] ?? 'Pending';

    // Check if material exists dan sudah diorder
    $material_check = $conn->prepare("
        SELECT ml.material_id, ml.name, mo.status as order_status 
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
                                notes = CONCAT(IFNULL(notes, ''), ?),
                                updated_by = ?,
                                updated_at = NOW(),
                                completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE completed_at END
                                WHERE material_id = ? AND division = 'Fabrikasi'");
        $update_notes = "\n\n--- Fabrication Update " . $current_time . " ---\nPhase: " . $fabrication_phase . "\nProgress: " . $progress_percent . "%\nStatus: " . $status . "\nQC: " . $qc_status . "\nNotes: " . $notes;
        $stmt->bind_param("sdsisi", $status, $progress_percent, $update_notes, $_SESSION['user_id'], $status, $material_id);
    } else {
        // INSERT new fabrication progress - MULAI PROSES FABRIKASI
        $stmt = $conn->prepare("INSERT INTO material_progress (material_id, division, status, progress_percent, notes, started_at, updated_by) 
                               VALUES (?, 'Fabrikasi', ?, ?, ?, NOW(), ?)");
        $new_notes = "🎯 FABRICATION PROCESS STARTED\nMaterial: " . $material_data['name'] . "\nPhase: " . $fabrication_phase . "\nInitial Progress: " . $progress_percent . "%\nQC Status: " . $qc_status . "\nNotes: " . $notes;
        $stmt->bind_param("isdsi", $material_id, $status, $progress_percent, $new_notes, $_SESSION['user_id']);
    }

    if ($stmt->execute()) {
        log_activity($conn, $_SESSION['user_id'], 'Update Fabrication', 'Updated fabrication progress for material: ' . $material_data['name']);
        echo json_encode(['success' => true, 'message' => 'Progress fabrication material berhasil diupdate']);
    } else {
        throw new Exception("Gagal update progress fabrication");
    }
}

function getFabricationMaterials($conn)
{
    if (!isset($_GET['pon_id'])) {
        throw new Exception("PON ID required");
    }

    $pon_id = (int)$_GET['pon_id'];

    // Get materials untuk fabrication + progress status
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
                COALESCE(mp.status, 'Pending') as fabrication_status,
                COALESCE(mp.progress_percent, 0) as fabrication_progress,
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
                    WHEN 'Completed' THEN 3
                    WHEN 'In Progress' THEN 2  
                    WHEN 'Rejected' THEN 1
                    ELSE 0
                END DESC,
                ml.assy_marking";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }

    // Get fabrication tasks juga
    $tasks_query = "SELECT * FROM tasks WHERE pon_id = ? AND responsible_division = 'Fabrikasi' ORDER BY start_date ASC";
    $tasks_stmt = $conn->prepare($tasks_query);
    $tasks_stmt->bind_param("i", $pon_id);
    $tasks_stmt->execute();
    $tasks_result = $tasks_stmt->get_result();

    $tasks = [];
    while ($row = $tasks_result->fetch_assoc()) {
        $tasks[] = $row;
    }

    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'tasks' => $tasks
    ]);
}

function startFabricationProcess($conn)
{
    if (!isset($_POST['material_id'])) {
        throw new Exception("Material ID required");
    }

    $material_id = (int)$_POST['material_id'];
    $fabrication_phase = $_POST['fabrication_phase'] ?? 'Material Preparation';
    $notes = $_POST['notes'] ?? 'Fabrication process started';

    // Check if already exists
    $check_stmt = $conn->prepare("SELECT progress_id FROM material_progress WHERE material_id = ? AND division = 'Fabrikasi'");
    $check_stmt->bind_param("i", $material_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();

    if ($exists) {
        throw new Exception("Fabrication already started for this material");
    }

    // Get material info
    $material_stmt = $conn->prepare("SELECT name FROM material_lists WHERE material_id = ?");
    $material_stmt->bind_param("i", $material_id);
    $material_stmt->execute();
    $material_data = $material_stmt->get_result()->fetch_assoc();

    if (!$material_data) {
        throw new Exception("Material not found");
    }

    // Start fabrication process
    $stmt = $conn->prepare("INSERT INTO material_progress (material_id, division, status, progress_percent, notes, started_at, updated_by) 
                           VALUES (?, 'Fabrikasi', 'In Progress', 5.00, ?, NOW(), ?)");
    $start_notes = "🚀 FABRICATION PROCESS STARTED\nMaterial: " . $material_data['name'] . "\nInitial Phase: " . $fabrication_phase . "\nNotes: " . $notes;
    $stmt->bind_param("isi", $material_id, $start_notes, $_SESSION['user_id']);

    if ($stmt->execute()) {
        log_activity($conn, $_SESSION['user_id'], 'Start Fabrication', 'Started fabrication for material: ' . $material_data['name']);
        echo json_encode(['success' => true, 'message' => 'Fabrication process started successfully']);
    } else {
        throw new Exception("Failed to start fabrication process");
    }
}

function completeFabricationProcess($conn)
{
    if (!isset($_POST['material_id'])) {
        throw new Exception("Material ID required");
    }

    $material_id = (int)$_POST['material_id'];
    $final_notes = $_POST['notes'] ?? 'Fabrication completed';
    $qc_status = $_POST['qc_status'] ?? 'Passed';

    // Get material info
    $material_stmt = $conn->prepare("SELECT name FROM material_lists WHERE material_id = ?");
    $material_stmt->bind_param("i", $material_id);
    $material_stmt->execute();
    $material_data = $material_stmt->get_result()->fetch_assoc();

    if (!$material_data) {
        throw new Exception("Material not found");
    }

    // Complete fabrication process
    $stmt = $conn->prepare("UPDATE material_progress SET 
                            status = 'Completed', 
                            progress_percent = 100.00, 
                            notes = CONCAT(IFNULL(notes, ''), ?),
                            completed_at = NOW(),
                            updated_by = ?,
                            updated_at = NOW()
                            WHERE material_id = ? AND division = 'Fabrikasi'");
    $completion_notes = "\n\n🎉 FABRICATION COMPLETED\nCompletion Time: " . date('Y-m-d H:i:s') . "\nQC Status: " . $qc_status . "\nFinal Notes: " . $final_notes;
    $stmt->bind_param("sii", $completion_notes, $_SESSION['user_id'], $material_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        log_activity($conn, $_SESSION['user_id'], 'Complete Fabrication', 'Completed fabrication for material: ' . $material_data['name']);
        echo json_encode(['success' => true, 'message' => 'Fabrication process completed successfully']);
    } else {
        throw new Exception("Failed to complete fabrication process - process may not have been started");
    }
}

function getProductionTrackingData($conn)
{
    if (!isset($_GET['pon_id'])) {
        throw new Exception("PON ID required");
    }

    $pon_id = (int)$_GET['pon_id'];

    // Get real-time production data
    $query = "SELECT 
                ml.material_id,
                ml.assy_marking,
                ml.name as material_name,
                ml.quantity,
                ml.total_weight_kg,
                mp.fabrication_phase,
                mp.progress_percent,
                mp.status,
                mp.started_at,
                mp.completed_at,
                mp.updated_at,
                u.full_name as operator_name,
                w.workstation,
                w.shift,
                w.notes as workshop_notes
              FROM material_lists ml
              LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
              LEFT JOIN users u ON mp.updated_by = u.user_id
              LEFT JOIN workshop_activities w ON ml.material_id = w.material_id
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

function logWorkshopActivity($conn)
{
    if (!isset($_POST['material_id']) || !isset($_POST['activity_type'])) {
        throw new Exception("Data aktivitas tidak lengkap");
    }

    $material_id = (int)$_POST['material_id'];
    $activity_type = $_POST['activity_type'];
    $workstation = $_POST['workstation'] ?? 'Main Workshop';
    $shift = $_POST['shift'] ?? 'Shift 1';
    $notes = $_POST['notes'] ?? '';
    $operator_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO workshop_activities (material_id, activity_type, workstation, shift, notes, operator_id) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $material_id, $activity_type, $workstation, $shift, $notes, $operator_id);

    if ($stmt->execute()) {
        log_activity($conn, $_SESSION['user_id'], 'Workshop Activity', 'Logged workshop activity for material ID: ' . $material_id);
        echo json_encode(['success' => true, 'message' => 'Aktivitas workshop berhasil dicatat']);
    } else {
        throw new Exception("Gagal mencatat aktivitas workshop");
    }
}

function generateFabricationReport($conn)
{
    if (!isset($_GET['pon_id'])) {
        throw new Exception("PON ID required");
    }

    $pon_id = (int)$_GET['pon_id'];

    // Comprehensive fabrication report
    $query = "SELECT 
                COUNT(ml.material_id) as total_materials,
                SUM(ml.total_weight_kg) as total_weight,
                COUNT(CASE WHEN mp.status = 'Completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN mp.status = 'In Progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN mp.status = 'Pending' THEN 1 END) as pending_count,
                AVG(mp.progress_percent) as avg_progress,
                MIN(mp.started_at) as fabrication_start,
                MAX(mp.completed_at) as fabrication_end,
                COUNT(DISTINCT mp.updated_by) as total_operators
              FROM material_lists ml
              LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
              WHERE ml.pon_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report_data = $result->fetch_assoc();

    // Get material completion details
    $details_query = "SELECT 
                        ml.material_id,
                        ml.assy_marking,
                        ml.name as material_name,
                        ml.quantity,
                        ml.total_weight_kg,
                        mp.status,
                        mp.progress_percent,
                        mp.started_at,
                        mp.completed_at,
                        DATEDIFF(COALESCE(mp.completed_at, NOW()), mp.started_at) as days_in_production
                      FROM material_lists ml
                      LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
                      WHERE ml.pon_id = ?
                      ORDER BY mp.status, mp.progress_percent DESC";

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

$conn->close();
?>