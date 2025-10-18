<?php

/**
 * AJAX Handler untuk Purchase Orders Management
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
            savePurchaseOrder($conn);
            break;

        case 'get':
            getPurchaseOrder($conn);
            break;

        case 'update_status':
            updateOrderStatus($conn);
            break;

        case 'delete':
            deletePurchaseOrder($conn);
            break;

        case 'list':
            getOrdersList($conn);
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
 * Save purchase order (create or update)
 */
function savePurchaseOrder($conn)
{
    // Check permission
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for managing purchase orders');
    }

    // Validate required fields
    $required_fields = ['pon_id', 'supplier_name', 'order_quantity', 'order_unit'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $order_id = $_POST['order_id'] ?? 0;
    $pon_id = (int)$_POST['pon_id'];
    $material_id = !empty($_POST['material_id']) && $_POST['material_id'] !== 'custom' ? (int)$_POST['material_id'] : null;
    $supplier_name = sanitize_input($_POST['supplier_name']);
    $order_quantity = (float)$_POST['order_quantity'];
    $order_unit = sanitize_input($_POST['order_unit']);
    $order_date = !empty($_POST['order_date']) ? $_POST['order_date'] : null;
    $expected_receiving_date = !empty($_POST['expected_receiving_date']) ? $_POST['expected_receiving_date'] : null;
    $specifications = sanitize_input($_POST['specifications'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $created_by = $_SESSION['user_id'];

    // Determine material_type and material_name based on selection
    if ($material_id) {
        // Get material info from material_lists
        $material_stmt = $conn->prepare("SELECT name FROM material_lists WHERE material_id = ?");
        $material_stmt->bind_param("i", $material_id);
        $material_stmt->execute();
        $material_result = $material_stmt->get_result();
        $material = $material_result->fetch_assoc();

        $material_type = 'From Material List';
        $material_name = $material ? $material['name'] : 'Material from List';
    } else {
        // Custom material
        $material_type = !empty($_POST['material_type']) ? sanitize_input($_POST['material_type']) : 'Other';
        $material_name = !empty($_POST['custom_material_name']) ? sanitize_input($_POST['custom_material_name']) : 'Custom Material';
    }

    // Validate quantity
    if ($order_quantity <= 0) {
        throw new Exception('Order quantity must be greater than 0');
    }

    if ($order_id) {
        // Update existing order
        $query = "UPDATE material_orders SET 
                  material_id = ?, supplier_name = ?, material_type = ?, 
                  order_date = ?, expected_receiving_date = ?, quantity = ?, 
                  unit = ?, specifications = ?, notes = ?, updated_at = NOW()
                  WHERE order_id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "issssdsssi",
            $material_id,
            $supplier_name,
            $material_type,
            $order_date,
            $expected_receiving_date,
            $order_quantity,
            $order_unit,
            $specifications,
            $notes,
            $order_id
        );
    } else {
        // Create new order
        $query = "INSERT INTO material_orders 
                  (pon_id, material_id, supplier_name, material_type, order_date, 
                   expected_receiving_date, quantity, unit, specifications, notes, 
                   status, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ordered', ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "iissssdsssi",
            $pon_id,
            $material_id,
            $supplier_name,
            $material_type,
            $order_date,
            $expected_receiving_date,
            $order_quantity,
            $order_unit,
            $specifications,
            $notes,
            $created_by
        );
    }

    if ($stmt->execute()) {
        $new_order_id = $order_id ?: $stmt->insert_id;

        // Log activity
        $action_type = $order_id ? 'Update PO' : 'Create PO';
        log_activity(
            $conn,
            $_SESSION['user_id'],
            $action_type,
            "{$action_type}: {$material_name} - {$supplier_name} (Qty: {$order_quantity} {$order_unit})"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Purchase order ' . ($order_id ? 'updated' : 'created') . ' successfully',
            'order_id' => $new_order_id
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get purchase order data
 */
function getPurchaseOrder($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Order ID required');
    }

    $order_id = (int)$_GET['id'];

    $query = "SELECT mo.*, ml.assy_marking, ml.name as material_name, ml.dimensions
              FROM material_orders mo 
              LEFT JOIN material_lists ml ON mo.material_id = ml.material_id
              WHERE mo.order_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        throw new Exception('Order not found');
    }

    echo json_encode(['success' => true, 'order' => $order]);
}

/**
 * Update order status
 */
function updateOrderStatus($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for updating order status');
    }

    $required_fields = ['order_id', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    $actual_receiving_date = ($status === 'Received' || $status === 'Partial Received') ? date('Y-m-d') : null;

    $query = "UPDATE material_orders 
              SET status = ?, actual_receiving_date = ?, updated_at = NOW()
              WHERE order_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $status, $actual_receiving_date, $order_id);

    if ($stmt->execute()) {
        // Log activity
        $order_info = getOrderInfo($conn, $order_id);
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Update Order Status',
            "Updated order status to: {$status} - {$order_info['material_type']}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'status' => $status
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Delete purchase order
 */
function deletePurchaseOrder($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for deleting orders');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Order ID required');
    }

    $order_id = (int)$_GET['id'];

    // Get order info before deletion for logging
    $order_info = getOrderInfo($conn, $order_id);

    $stmt = $conn->prepare("DELETE FROM material_orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {
        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Delete PO',
            "Deleted purchase order: {$order_info['material_type']} - {$order_info['supplier_name']}"
        );

        echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get orders list for a PON
 */
function getOrdersList($conn)
{
    if (!isset($_GET['pon_id']) || empty($_GET['pon_id'])) {
        throw new Exception('PON ID required');
    }

    $pon_id = (int)$_GET['pon_id'];

    $query = "SELECT mo.*, ml.assy_marking, ml.name as material_name, ml.dimensions,
                     u.full_name as created_by_name
              FROM material_orders mo 
              LEFT JOIN material_lists ml ON mo.material_id = ml.material_id
              LEFT JOIN users u ON mo.created_by = u.user_id
              WHERE mo.pon_id = ? 
              ORDER BY mo.order_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
}

/**
 * Get order info for logging
 */
function getOrderInfo($conn, $order_id)
{
    $stmt = $conn->prepare("SELECT material_type, supplier_name FROM material_orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>