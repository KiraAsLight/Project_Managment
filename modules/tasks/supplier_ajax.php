<?php

/**
 * AJAX Handler untuk Supplier Management
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
        case 'save':
            saveSupplier($conn);
            break;

        case 'get':
            getSupplier($conn);
            break;

        case 'delete':
            deleteSupplier($conn);
            break;

        case 'list':
            getSuppliersList($conn);
            break;

        case 'search':
            searchSuppliers($conn);
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
 * Save supplier (create or update)
 */
function saveSupplier($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for managing suppliers');
    }

    $required_fields = ['supplier_name', 'contact_person', 'phone', 'email'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $supplier_id = $_POST['supplier_id'] ?? 0;
    $supplier_name = sanitize_input($_POST['supplier_name']);
    $contact_person = sanitize_input($_POST['contact_person']);
    $phone = sanitize_input($_POST['phone']);
    $email = sanitize_input($_POST['email']);
    $address = sanitize_input($_POST['address'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $country = sanitize_input($_POST['country'] ?? 'Indonesia');
    $tax_number = sanitize_input($_POST['tax_number'] ?? '');
    $bank_account = sanitize_input($_POST['bank_account'] ?? '');
    $payment_terms = sanitize_input($_POST['payment_terms'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 1;

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    if ($supplier_id) {
        // Update existing supplier
        $query = "UPDATE suppliers SET 
                  supplier_name = ?, contact_person = ?, phone = ?, email = ?, 
                  address = ?, city = ?, country = ?, tax_number = ?, 
                  bank_account = ?, payment_terms = ?, notes = ?, is_active = ?,
                  updated_at = NOW()
                  WHERE supplier_id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sssssssssssii",
            $supplier_name,
            $contact_person,
            $phone,
            $email,
            $address,
            $city,
            $country,
            $tax_number,
            $bank_account,
            $payment_terms,
            $notes,
            $is_active,
            $supplier_id
        );
    } else {
        // Create new supplier
        $query = "INSERT INTO suppliers 
                  (supplier_name, contact_person, phone, email, address, 
                   city, country, tax_number, bank_account, payment_terms, 
                   notes, is_active, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sssssssssssii",
            $supplier_name,
            $contact_person,
            $phone,
            $email,
            $address,
            $city,
            $country,
            $tax_number,
            $bank_account,
            $payment_terms,
            $notes,
            $is_active,
            $_SESSION['user_id']
        );
    }

    if ($stmt->execute()) {
        $new_supplier_id = $supplier_id ?: $stmt->insert_id;

        // Log activity
        $action_type = $supplier_id ? 'Update Supplier' : 'Create Supplier';
        log_activity(
            $conn,
            $_SESSION['user_id'],
            $action_type,
            "{$action_type}: {$supplier_name}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Supplier ' . ($supplier_id ? 'updated' : 'created') . ' successfully',
            'supplier_id' => $new_supplier_id
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get supplier data
 */
function getSupplier($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Supplier ID required');
    }

    $supplier_id = (int)$_GET['id'];

    $query = "SELECT s.*, u.full_name as created_by_name
              FROM suppliers s 
              LEFT JOIN users u ON s.created_by = u.user_id
              WHERE s.supplier_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();

    if (!$supplier) {
        throw new Exception('Supplier not found');
    }

    echo json_encode(['success' => true, 'supplier' => $supplier]);
}

/**
 * Delete supplier
 */
function deleteSupplier($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for deleting suppliers');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Supplier ID required');
    }

    $supplier_id = (int)$_GET['id'];

    // Check if supplier has existing orders
    $order_check = $conn->prepare("SELECT COUNT(*) as order_count FROM material_orders WHERE supplier_name IN (SELECT supplier_name FROM suppliers WHERE supplier_id = ?)");
    $order_check->bind_param("i", $supplier_id);
    $order_check->execute();
    $result = $order_check->get_result();
    $order_count = $result->fetch_assoc()['order_count'];

    if ($order_count > 0) {
        throw new Exception('Cannot delete supplier with existing purchase orders');
    }

    // Get supplier info before deletion for logging
    $supplier_info = getSupplierInfo($conn, $supplier_id);

    $stmt = $conn->prepare("UPDATE suppliers SET is_active = 0 WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);

    if ($stmt->execute()) {
        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Delete Supplier',
            "Deleted supplier: {$supplier_info['supplier_name']}"
        );

        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get suppliers list
 */
function getSuppliersList($conn)
{
    $query = "SELECT s.*, 
                     (SELECT COUNT(*) FROM material_orders mo WHERE mo.supplier_name = s.supplier_name) as order_count,
                     u.full_name as created_by_name
              FROM suppliers s 
              LEFT JOIN users u ON s.created_by = u.user_id
              WHERE s.is_active = 1
              ORDER BY s.supplier_name";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }

    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers,
        'count' => count($suppliers)
    ]);
}

/**
 * Search suppliers for autocomplete
 */
function searchSuppliers($conn)
{
    $search_term = $_GET['q'] ?? '';

    if (empty($search_term)) {
        echo json_encode(['success' => true, 'suppliers' => []]);
        return;
    }

    $search_term = '%' . $search_term . '%';

    $query = "SELECT supplier_id, supplier_name, contact_person, phone, email 
              FROM suppliers 
              WHERE is_active = 1 
              AND (supplier_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)
              ORDER BY supplier_name 
              LIMIT 10";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }

    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
}

/**
 * Get supplier info for logging
 */
function getSupplierInfo($conn, $supplier_id)
{
    $stmt = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>