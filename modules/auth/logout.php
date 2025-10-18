<?php

/**
 * Handler Logout
 * Menghapus session dan redirect ke login
 */

session_start();

// Log aktivitas sebelum destroy session
if (isset($_SESSION['user_id'])) {
    require_once '../../config/database.php';

    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'Logout', 'User logout dari sistem', ?)");
    $stmt->bind_param("is", $user_id, $ip_address);
    $stmt->execute();
    $stmt->close();
    closeDBConnection($conn);
}

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke login
header("Location: ../../modules/auth/login.php");
exit;
