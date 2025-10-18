<?php

/**
 * AJAX Handler untuk Material CRUD Operations - FIXED berdasarkan SQL structure
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Start output buffering
ob_start();

try {
    require_login();

    // Check permission
    if (!canManageMaterial()) {
        throw new Exception('Permission denied');
    }

    $action = $_GET['action'] ?? '';

    if (empty($action)) {
        throw new Exception('No action specified');
    }

    $conn = getDBConnection();
    header('Content-Type: application/json');

    switch ($action) {
        case 'get':
            $material_id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM material_lists WHERE material_id = ?");
            $stmt->bind_param("i", $material_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $material = $result->fetch_assoc();

            if ($material) {
                echo json_encode(['success' => true, 'material' => $material]);
            } else {
                throw new Exception('Material not found');
            }
            break;

        case 'save':
            $material_id = $_POST['material_id'] ?? 0;
            $pon_id = (int)$_POST['pon_id'];

            // Get or create Engineering task
            $task_id = getOrCreateEngineeringTask($conn, $pon_id);

            $assy_marking = sanitize_input($_POST['assy_marking'] ?? '');
            $rv = sanitize_input($_POST['rv'] ?? '');
            $name = sanitize_input($_POST['name']);
            $quantity = (int)$_POST['quantity'];
            $dimensions = sanitize_input($_POST['dimensions'] ?? '');
            $length_mm = $_POST['length_mm'] ? (float)$_POST['length_mm'] : null;
            $weight_kg = $_POST['weight_kg'] ? (float)$_POST['weight_kg'] : null;
            $remarks = sanitize_input($_POST['remarks'] ?? '');
            $created_by = $_SESSION['user_id']; // ← created_by ADA di table

            // Calculate total_weight_kg
            $total_weight_kg = ($quantity * $weight_kg) ?: null;

            // Validate required fields
            if (empty($name) || $quantity <= 0) {
                throw new Exception('Name and Quantity are required');
            }

            if ($material_id) {
                // Update existing material
                $stmt = $conn->prepare("UPDATE material_lists SET 
                    task_id=?, assy_marking=?, rv=?, name=?, quantity=?, dimensions=?, 
                    length_mm=?, weight_kg=?, total_weight_kg=?, remarks=?, created_by=?
                    WHERE material_id=?");
                $stmt->bind_param(
                    "isssisddsdii",
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
                    $created_by,
                    $material_id
                );
            } else {
                // Insert new material - SESUAI STRUCTURE SQL
                $stmt = $conn->prepare("INSERT INTO material_lists (
                    pon_id, task_id, assy_marking, rv, name, quantity, dimensions, 
                    length_mm, weight_kg, total_weight_kg, remarks, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                    $created_by
                );
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Material saved successfully']);
            } else {
                throw new Exception('Database error: ' . $stmt->error);
            }
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                $material_id = (int)$_GET['id'];
                $stmt = $conn->prepare("DELETE FROM material_lists WHERE material_id = ?");
                $stmt->bind_param("i", $material_id);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Material deleted successfully']);
                } else {
                    throw new Exception('Database error: ' . $stmt->error);
                }
            } else {
                throw new Exception('Invalid request method');
            }
            break;

        case 'import':
            // Check if file was uploaded
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . ($_FILES['excel_file']['error'] ?? 'Unknown error'));
            }

            $pon_id = (int)$_POST['pon_id'];
            $created_by = $_SESSION['user_id'];

            // Get or create Engineering task
            $task_id = getOrCreateEngineeringTask($conn, $pon_id);

            $file = $_FILES['excel_file']['tmp_name'];
            $file_name = $_FILES['excel_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Check file extension
            if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Invalid file format. Please use Excel (.xlsx, .xls) or CSV files.');
            }

            $imported_count = 0;
            $errors = [];

            try {
                // Load PhpSpreadsheet
                require_once '../../vendor/autoload.php'; // Jika pakai composer

                // Atau include manual jika tidak pakai composer
                // require_once '../../path/to/PhpSpreadsheet/autoload.php';

                if ($file_ext == 'csv') {
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                } else {
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
                }

                $spreadsheet = $reader->load($file);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                $first_row = true;

                foreach ($rows as $row) {
                    // Skip header row
                    if ($first_row) {
                        $first_row = false;
                        continue;
                    }

                    // Skip empty rows
                    if (empty(array_filter($row)) || empty(trim($row[2] ?? ''))) {
                        continue;
                    }

                    try {
                        // Parse Excel data - SESUAI URUTAN:
                        // assy_marking, rv, name, quantity, dimensions, length_mm, weight_kg, remarks
                        $assy_marking = sanitize_input($row[0] ?? '');
                        $rv = sanitize_input($row[1] ?? '');
                        $name = sanitize_input($row[2] ?? '');
                        $quantity = !empty($row[3]) ? (int)$row[3] : 0;
                        $dimensions = sanitize_input($row[4] ?? '');
                        $length_mm = !empty($row[5]) ? (float)$row[5] : null;
                        $weight_kg = !empty($row[6]) ? (float)$row[6] : 0;
                        $remarks = sanitize_input($row[7] ?? 'Imported from Excel');

                        // Calculate total weight
                        $total_weight_kg = $quantity * $weight_kg;

                        // Validate required fields
                        if (empty($name)) {
                            $errors[] = "Row skipped: Material name is required";
                            continue;
                        }

                        if ($quantity <= 0) {
                            $errors[] = "Row skipped: Quantity must be greater than 0 for '{$name}'";
                            continue;
                        }

                        // Insert into database
                        $stmt = $conn->prepare("INSERT INTO material_lists (
                    pon_id, task_id, assy_marking, rv, name, quantity, dimensions, 
                    length_mm, weight_kg, total_weight_kg, remarks, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

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
                            $created_by
                        );

                        if ($stmt->execute()) {
                            $imported_count++;
                        } else {
                            $errors[] = "Failed to import: {$name} - " . $stmt->error;
                        }
                    } catch (Exception $e) {
                        $row_data = implode(", ", array_slice($row, 0, 8));
                        $errors[] = "Error processing: {$row_data} - " . $e->getMessage();
                    }
                }

                if ($imported_count > 0) {
                    $message = "✅ Successfully imported {$imported_count} materials from Excel file";
                    if (count($errors) > 0) {
                        $message .= "\n\n⚠️ " . count($errors) . " errors:\n" . implode("\n", array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $message .= "\n... and " . (count($errors) - 5) . " more errors";
                        }
                    }
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    throw new Exception("❌ No materials imported. Please check your Excel format.\nErrors:\n" . implode("\n", $errors));
                }
            } catch (Exception $e) {
                throw new Exception('Error reading Excel file: ' . $e->getMessage());
            }
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        closeDBConnection($conn);
    }
    ob_end_flush();
}

/**
 * Get or create default Engineering task for a PON
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

    // Jika tidak ada, buat task Engineering baru - SESUAI STRUCTURE TASKS
    $task_name = "Engineering Design & Material Planning";
    $description = "Material list management and engineering design";
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

?>