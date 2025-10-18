<?php

/**
 * Konfigurasi umum aplikasi
 */

// Timezone Indonesia
date_default_timezone_set('Asia/Jakarta');

// Konstanta aplikasi
define('APP_NAME', 'Project Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/wpm/V2/PM/');

// Konstanta keamanan
define('SESSION_TIMEOUT', 3600); // 1 jam dalam detik
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 menit

// Konstanta upload file
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB dalam bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Error reporting (matikan di production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi helper untuk redirect
 */
function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Fungsi untuk cek apakah user sudah login
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Fungsi untuk cek role user
 */
function hasRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Fungsi untuk cek multiple roles
 */
function hasAnyRole($roles = [])
{
    if (!isset($_SESSION['role'])) return false;
    return in_array($_SESSION['role'], $roles);
}

/**
 * Fungsi untuk cek permission task management per divisi
 */
function canManageTask($task_division, $user_division)
{
    // Admin bisa manage semua
    if ($_SESSION['role'] === 'Admin') return true;

    // User hanya bisa manage task divisinya sendiri
    return $task_division === $user_division;
}

/**
 * Fungsi untuk cek read access
 */
function canViewTask($task_division, $user_division)
{
    // Semua role bisa melihat semua tasks
    return true;
}
?>