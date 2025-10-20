<?php

/**
 * AJAX Handler untuk Delivery Tracking
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
            saveDelivery($conn);
            break;

        case 'get':
            getDelivery($conn);
            break;

        case 'update_status':
            updateDeliveryStatus($conn);
            break;

        case 'delete':
            deleteDelivery($conn);
            break;

        case 'list':
            getDeliveriesList($conn);
            break;

        case 'receive_items':
            receiveDeliveryItems($conn);
            break;

        case 'get_by_order':
            getDeliveriesByOrder($conn);
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
 * Save delivery record
 */
function saveDelivery($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for managing deliveries');
    }

    $required_fields = ['order_id', 'delivery_number', 'delivery_date', 'carrier_name'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $delivery_id = $_POST['delivery_id'] ?? 0;
    $order_id = (int)$_POST['order_id'];
    $delivery_number = sanitize_input($_POST['delivery_number']);
    $delivery_date = $_POST['delivery_date'];
    $carrier_name = sanitize_input($_POST['carrier_name']);
    $driver_name = sanitize_input($_POST['driver_name'] ?? '');
    $vehicle_number = sanitize_input($_POST['vehicle_number'] ?? '');
    $tracking_number = sanitize_input($_POST['tracking_number'] ?? '');
    $estimated_arrival = !empty($_POST['estimated_arrival']) ? $_POST['estimated_arrival'] : null;
    $actual_arrival = !empty($_POST['actual_arrival']) ? $_POST['actual_arrival'] : null;
    $notes = sanitize_input($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'Scheduled';

    // Validate delivery number uniqueness
    if ($delivery_id) {
        $check_stmt = $conn->prepare("SELECT delivery_id FROM deliveries WHERE delivery_number = ? AND delivery_id != ?");
        $check_stmt->bind_param("si", $delivery_number, $delivery_id);
    } else {
        $check_stmt = $conn->prepare("SELECT delivery_id FROM deliveries WHERE delivery_number = ?");
        $check_stmt->bind_param("s", $delivery_number);
    }

    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Delivery number already exists');
    }

    if ($delivery_id) {
        // Update existing delivery
        $query = "UPDATE deliveries SET 
                  delivery_number = ?, delivery_date = ?, carrier_name = ?, 
                  driver_name = ?, vehicle_number = ?, tracking_number = ?,
                  estimated_arrival = ?, actual_arrival = ?, notes = ?, status = ?,
                  updated_at = NOW()
                  WHERE delivery_id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssssssi",
            $delivery_number,
            $delivery_date,
            $carrier_name,
            $driver_name,
            $vehicle_number,
            $tracking_number,
            $estimated_arrival,
            $actual_arrival,
            $notes,
            $status,
            $delivery_id
        );
    } else {
        // Create new delivery
        $query = "INSERT INTO deliveries 
                  (order_id, delivery_number, delivery_date, carrier_name, 
                   driver_name, vehicle_number, tracking_number, estimated_arrival,
                   actual_arrival, notes, status, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "issssssssssi",
            $order_id,
            $delivery_number,
            $delivery_date,
            $carrier_name,
            $driver_name,
            $vehicle_number,
            $tracking_number,
            $estimated_arrival,
            $actual_arrival,
            $notes,
            $status,
            $_SESSION['user_id']
        );
    }

    if ($stmt->execute()) {
        $new_delivery_id = $delivery_id ?: $stmt->insert_id;

        // Update order status if delivery is created/completed
        if (!$delivery_id && $status === 'Delivered') {
            updateOrderStatusFromDelivery($conn, $order_id);
        }

        // Log activity
        $action_type = $delivery_id ? 'Update Delivery' : 'Create Delivery';
        log_activity(
            $conn,
            $_SESSION['user_id'],
            $action_type,
            "{$action_type}: {$delivery_number} for Order #{$order_id}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Delivery ' . ($delivery_id ? 'updated' : 'created') . ' successfully',
            'delivery_id' => $new_delivery_id
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get delivery data
 */
function getDelivery($conn)
{
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Delivery ID required');
    }

    $delivery_id = (int)$_GET['id'];

    $query = "SELECT d.*, mo.order_id, mo.supplier_name, mo.material_type, mo.quantity as order_quantity,
                     u.full_name as created_by_name
              FROM deliveries d 
              JOIN material_orders mo ON d.order_id = mo.order_id
              LEFT JOIN users u ON d.created_by = u.user_id
              WHERE d.delivery_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $delivery = $result->fetch_assoc();

    if (!$delivery) {
        throw new Exception('Delivery not found');
    }

    echo json_encode(['success' => true, 'delivery' => $delivery]);
}

/**
 * Update delivery status
 */
function updateDeliveryStatus($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for updating delivery status');
    }

    $required_fields = ['delivery_id', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $delivery_id = (int)$_POST['delivery_id'];
    $status = $_POST['status'];
    $notes = sanitize_input($_POST['notes'] ?? '');

    // Set actual arrival date if status is Delivered
    $actual_arrival = ($status === 'Delivered') ? date('Y-m-d H:i:s') : null;

    $query = "UPDATE deliveries 
              SET status = ?, actual_arrival = ?, notes = CONCAT(IFNULL(notes, ''), ?), updated_at = NOW()
              WHERE delivery_id = ?";

    $status_notes = "\n\nStatus updated to: {$status} on " . date('Y-m-d H:i:s');
    if (!empty($notes)) {
        $status_notes .= " - " . $notes;
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $status, $actual_arrival, $status_notes, $delivery_id);

    if ($stmt->execute()) {
        // Update order status if delivery is completed
        if ($status === 'Delivered') {
            $order_id = getOrderIdFromDelivery($conn, $delivery_id);
            updateOrderStatusFromDelivery($conn, $order_id);
        }

        // Log activity
        $delivery_info = getDeliveryInfo($conn, $delivery_id);
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Update Delivery Status',
            "Updated delivery status to: {$status} - {$delivery_info['delivery_number']}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Delivery status updated successfully',
            'status' => $status
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Delete delivery
 */
function deleteDelivery($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for deleting deliveries');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Delivery ID required');
    }

    $delivery_id = (int)$_GET['id'];

    // Get delivery info before deletion for logging
    $delivery_info = getDeliveryInfo($conn, $delivery_id);

    $stmt = $conn->prepare("DELETE FROM deliveries WHERE delivery_id = ?");
    $stmt->bind_param("i", $delivery_id);

    if ($stmt->execute()) {
        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Delete Delivery',
            "Deleted delivery: {$delivery_info['delivery_number']}"
        );

        echo json_encode(['success' => true, 'message' => 'Delivery deleted successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

/**
 * Get deliveries list for a PON
 */
function getDeliveriesList($conn)
{
    if (!isset($_GET['pon_id']) || empty($_GET['pon_id'])) {
        throw new Exception('PON ID required');
    }

    $pon_id = (int)$_GET['pon_id'];

    $query = "SELECT d.*, mo.supplier_name, mo.material_type, mo.quantity as order_quantity,
                     mo.order_id, mo.status as order_status,
                     u.full_name as created_by_name
              FROM deliveries d 
              JOIN material_orders mo ON d.order_id = mo.order_id
              LEFT JOIN users u ON d.created_by = u.user_id
              WHERE mo.pon_id = ? 
              ORDER BY d.delivery_date DESC, d.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $deliveries = [];
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }

    echo json_encode([
        'success' => true,
        'deliveries' => $deliveries,
        'count' => count($deliveries)
    ]);
}

/**
 * Get deliveries by order ID
 */
function getDeliveriesByOrder($conn)
{
    if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
        throw new Exception('Order ID required');
    }

    $order_id = (int)$_GET['order_id'];

    $query = "SELECT d.*, u.full_name as created_by_name
              FROM deliveries d 
              LEFT JOIN users u ON d.created_by = u.user_id
              WHERE d.order_id = ? 
              ORDER BY d.delivery_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $deliveries = [];
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }

    // Calculate total received quantity
    $total_received = 0;
    foreach ($deliveries as $delivery) {
        $total_received += $delivery['received_quantity'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'deliveries' => $deliveries,
        'total_received' => $total_received,
        'count' => count($deliveries)
    ]);
}

/**
 * Receive delivery items with partial quantities
 */
function receiveDeliveryItems($conn)
{
    if (!canManagePurchasing()) {
        throw new Exception('Permission denied for receiving items');
    }

    $required_fields = ['delivery_id', 'received_quantity'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }

    $delivery_id = (int)$_POST['delivery_id'];
    $received_quantity = (float)$_POST['received_quantity'];
    $receive_notes = sanitize_input($_POST['receive_notes'] ?? '');
    $receive_date = $_POST['receive_date'] ?? date('Y-m-d');

    // Get delivery and order info
    $delivery_info = getDeliveryInfo($conn, $delivery_id);
    $order_id = $delivery_info['order_id'];
    $order_quantity = $delivery_info['quantity'];

    // Calculate received quantities
    $previous_received = $delivery_info['received_quantity'] ?? 0;
    $total_received = $previous_received + $received_quantity;

    // Update delivery received quantity
    $delivery_stmt = $conn->prepare("UPDATE deliveries SET received_quantity = ?, actual_arrival = NOW(), status = 'Delivered' WHERE delivery_id = ?");
    $delivery_stmt->bind_param("di", $total_received, $delivery_id);

    if (!$delivery_stmt->execute()) {
        throw new Exception('Failed to update delivery: ' . $delivery_stmt->error);
    }

    // Update order status based on received quantity
    $new_order_status = 'Partial Received';
    if ($total_received >= $order_quantity) {
        $new_order_status = 'Received';
    }

    $order_stmt = $conn->prepare("UPDATE material_orders SET status = ?, actual_receiving_date = NOW() WHERE order_id = ?");
    $order_stmt->bind_param("si", $new_order_status, $order_id);

    if (!$order_stmt->execute()) {
        throw new Exception('Failed to update order status: ' . $order_stmt->error);
    }

    // Add receive notes
    if (!empty($receive_notes)) {
        $notes_stmt = $conn->prepare("UPDATE deliveries SET notes = CONCAT(IFNULL(notes, ''), ?) WHERE delivery_id = ?");
        $receive_note = "\n\nReceived: " . $received_quantity . " units on " . $receive_date . " - " . $receive_notes;
        $notes_stmt->bind_param("si", $receive_note, $delivery_id);
        $notes_stmt->execute();
    }

    // Log receiving activity
    log_activity(
        $conn,
        $_SESSION['user_id'],
        'Receive Items',
        "Received {$received_quantity} items for Delivery #{$delivery_id}. Order status: {$new_order_status}"
    );

    echo json_encode([
        'success' => true,
        'message' => "Items received successfully. {$received_quantity} units added to inventory.",
        'order_status' => $new_order_status,
        'total_received' => $total_received,
        'completion_percentage' => round(($total_received / $order_quantity) * 100, 2)
    ]);
}

/**
 * Helper function to update order status from delivery
 */
function updateOrderStatusFromDelivery($conn, $order_id)
{
    // Calculate total received quantity from all deliveries for this order
    $received_query = "SELECT SUM(received_quantity) as total_received FROM deliveries WHERE order_id = ? AND status = 'Delivered'";
    $received_stmt = $conn->prepare($received_query);
    $received_stmt->bind_param("i", $order_id);
    $received_stmt->execute();
    $received_result = $received_stmt->get_result();
    $total_received = $received_result->fetch_assoc()['total_received'] ?? 0;

    // Get order quantity
    $order_query = "SELECT quantity FROM material_orders WHERE order_id = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_data = $order_result->fetch_assoc();
    $order_quantity = $order_data['quantity'] ?? 0;

    // Determine order status
    $new_status = 'Ordered';
    if ($total_received > 0) {
        $new_status = ($total_received >= $order_quantity) ? 'Received' : 'Partial Received';
    }

    // Update order status
    $update_stmt = $conn->prepare("UPDATE material_orders SET status = ?, actual_receiving_date = NOW() WHERE order_id = ?");
    $update_stmt->bind_param("si", $new_status, $order_id);
    $update_stmt->execute();

    return $new_status;
}

/**
 * Get delivery info for logging
 */
function getDeliveryInfo($conn, $delivery_id)
{
    $stmt = $conn->prepare("SELECT delivery_number, order_id FROM deliveries WHERE delivery_id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get order ID from delivery
 */
function getOrderIdFromDelivery($conn, $delivery_id)
{
    $stmt = $conn->prepare("SELECT order_id FROM deliveries WHERE delivery_id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['order_id'] ?? null;
}

/**
 * Get delivery info with order details
 */
function getDeliveryInfoWithOrder($conn, $delivery_id)
{
    $query = "SELECT d.*, mo.quantity, mo.material_type, mo.supplier_name 
              FROM deliveries d 
              JOIN material_orders mo ON d.order_id = mo.order_id 
              WHERE d.delivery_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>