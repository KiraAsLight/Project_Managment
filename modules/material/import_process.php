<?php
// modules/material/import_process.php

ob_start(); // Start output buffering

error_reporting(0); // Nonaktifkan error reporting
ini_set('display_errors', 0);

// REQUIRE FILES 
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CLEAN OUTPUT BUFFER
ob_clean();

// SET JSON HEADER
header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Cek role
if (!(isset($_SESSION['role']) && in_array($_SESSION['role'], ['Admin', 'Engineering']))) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = getDBConnection();

try {
    // Validasi required fields
    if (!isset($_POST['pon_id'])) {
        throw new Exception("pon_id is required");
    }

    $pon_id = (int)$_POST['pon_id'];
    $file_type = $_POST['file_type'] ?? 'excel';
    $description = $_POST['description'] ?? '';

    // Validate PON exists
    $stmt = $conn->prepare("SELECT pon_number, project_name FROM pon WHERE pon_id = ?");
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pon = $result->fetch_assoc();

    if (!$pon) {
        throw new Exception("PON tidak ditemukan");
    }

    // Check file upload
    if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['material_file']['error'] ?? 'NO_FILE';
        throw new Exception("File upload error: " . $error_code);
    }

    $uploaded_file = $_FILES['material_file'];
    $file_name = $uploaded_file['name'];
    $file_tmp = $uploaded_file['tmp_name'];
    $file_size = $uploaded_file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate file size
    if ($file_size > 10 * 1024 * 1024) {
        throw new Exception("File terlalu besar. Maksimal 10MB");
    }

    // Create upload directory
    $upload_dir = __DIR__ . '/uploads/engineering/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $new_filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
    $target_file = $upload_dir . $new_filename;

    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $target_file)) {
        throw new Exception("Gagal menyimpan file");
    }

    $response = [];

    if ($file_type === 'excel' && in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
        // Process Excel/CSV file
        $import_result = importExcelToMaterialLists($conn, $target_file, $pon_id, $description);

        if ($import_result['success']) {
            $response = [
                'success' => true,
                'message' => "File berhasil diupload dan {$import_result['imported']} data diimport ke sistem"
            ];

            // Log activity
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'Upload Material Excel',
                "Upload material list untuk PON {$pon['pon_number']} - {$import_result['imported']} items diimport"
            );
        } else {
            throw new Exception("Gagal import data: " . $import_result['error']);
        }

        // Clean up
        if (file_exists($target_file)) {
            unlink($target_file);
        }
    } elseif ($file_type === 'pdf' && $file_ext === 'pdf') {
        // Process PDF file
        $response = [
            'success' => true,
            'message' => "File PDF berhasil diupload sebagai dokumentasi"
        ];

        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Upload Material PDF',
            "Upload material list PDF untuk PON {$pon['pon_number']} - {$file_name}"
        );
    } else {
        throw new Exception("Format file tidak sesuai");
    }

    // CLEAN OUTPUT SEBELUM JSON
    ob_clean();
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    // Clean up
    if (isset($target_file) && file_exists($target_file)) {
        unlink($target_file);
    }

    // CLEAN OUTPUT SEBELUM JSON ERROR
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

$conn->close();

/**
 * Import Excel/CSV file to material_lists table
 */
function importExcelToMaterialLists($conn, $file_path, $pon_id, $description)
{
    $result = ['success' => false, 'imported' => 0, 'error' => ''];

    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if (in_array($file_ext, ['csv', 'xlsx', 'xls'])) {
        return parseCSVFile($conn, $file_path, $pon_id);
    } else {
        $result['error'] = "Format file tidak didukung: " . $file_ext;
        return $result;
    }
}

/**
 * Parse CSV file and import to material_lists
 */
function parseCSVFile($conn, $file_path, $pon_id)
{
    $result = ['success' => false, 'imported' => 0, 'error' => ''];

    try {
        $rows = [];
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        } else {
            throw new Exception("Cannot open file: " . $file_path);
        }

        // Skip header row
        array_shift($rows);

        $imported_count = 0;
        $conn->begin_transaction();

        // Create a temporary task for these materials
        $task_stmt = $conn->prepare("INSERT INTO tasks (pon_id, phase, responsible_division, task_name, description) VALUES (?, 'Engineering', 'Engineering', 'Material List Upload', 'Auto-generated task for material list')");
        $task_stmt->bind_param("i", $pon_id);
        $task_stmt->execute();
        $task_id = $conn->insert_id;

        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0]) || empty(trim($row[3]))) {
                continue;
            }

            // Map CSV columns to database fields
            $assy_marking = !empty($row[1]) ? trim($row[1]) : null;
            $rv = !empty($row[2]) ? trim($row[2]) : null;
            $name = trim($row[3]);
            $quantity = !empty($row[4]) ? (int)$row[4] : 0;
            $dimensions = !empty($row[5]) ? trim($row[5]) : null;
            $length_mm = !empty($row[6]) ? (float)$row[6] : null;
            $weight_kg = !empty($row[7]) ? (float)$row[7] : 0;
            $total_weight_kg = !empty($row[8]) ? (float)$row[8] : ($quantity * $weight_kg);
            $remarks = !empty($row[9]) ? trim($row[9]) : null;

            // Insert to material_lists
            $stmt = $conn->prepare("INSERT INTO material_lists 
                                   (pon_id, task_id, assy_marking, rv, name, quantity, dimensions, 
                                    length_mm, weight_kg, total_weight_kg, remarks, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param(
                "iisssisdddsi",
                $pon_id,
                $task_id,
                $assy_marking,
                $rv,
                $name,
                $quantity,
                $dimensions,
                $length_mm,
                $weight_kg,
                $total_weight_kg,
                $remarks,
                $_SESSION['user_id']
            );

            if ($stmt->execute()) {
                $material_id = $stmt->insert_id;

                // Create progress tracking for all divisions
                $divisions = ['Engineering', 'Purchasing', 'Fabrikasi', 'Logistik'];
                foreach ($divisions as $division) {
                    $progress_stmt = $conn->prepare("INSERT INTO material_progress 
                                                   (material_id, division, status, progress_percent, updated_by) 
                                                   VALUES (?, ?, 'Pending', 0, ?)");
                    $progress_stmt->bind_param("isi", $material_id, $division, $_SESSION['user_id']);
                    $progress_stmt->execute();
                }

                $imported_count++;
            } else {
                throw new Exception("Insert failed: " . $stmt->error);
            }
        }

        $conn->commit();
        $result['success'] = true;
        $result['imported'] = $imported_count;
    } catch (Exception $e) {
        $conn->rollback();
        $result['error'] = $e->getMessage();
    }

    return $result;
}
