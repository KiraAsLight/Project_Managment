<?php

/**
 * Delete PON
 * Hanya Admin yang bisa delete
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Only admin can delete
require_role(['Admin']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid PON ID");
}

$pon_id = (int)$_GET['id'];
$conn = getDBConnection();

// Get PON info untuk log
$stmt = $conn->prepare("SELECT pon_number FROM pon WHERE pon_id = ?");
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$result = $stmt->get_result();
$pon = $result->fetch_assoc();

if (!$pon) {
    die("PON tidak ditemukan");
}

// Delete PON (akan cascade delete tasks dan qc_documents karena foreign key)
$delete_stmt = $conn->prepare("DELETE FROM pon WHERE pon_id = ?");
$delete_stmt->bind_param("i", $pon_id);

if ($delete_stmt->execute()) {
    // Log activity
    log_activity(
        $conn,
        $_SESSION['user_id'],
        'Delete PON',
        "Menghapus PON {$pon['pon_number']}"
    );

    // Redirect dengan success message
    $_SESSION['success_message'] = "PON {$pon['pon_number']} berhasil dihapus";
    redirect('modules/pon/list.php');
} else {
    die("Gagal menghapus PON: " . $conn->error);
}

closeDBConnection($conn);
?>