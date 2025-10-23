<?php

/**
 * Helper Functions untuk Dashboard & Sistem
 */

/**
 * Sanitasi input untuk mencegah XSS
 */
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format tanggal Indonesia
 */
function format_date_indo($date)
{
    if (empty($date) || $date == '0000-00-00') return '-';

    $bulan = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agt',
        'Sep',
        'Okt',
        'Nov',
        'Des'
    ];

    $split = explode('-', $date);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

/**
 * Hitung selisih hari dari hari ini
 */
function days_difference($date)
{
    if (empty($date)) return null;

    $today = new DateTime();
    $target = new DateTime($date);
    $diff = $today->diff($target);

    return ($diff->invert ? -1 : 1) * $diff->days;
}

/**
 * Get status badge color
 */
function get_status_color($status)
{
    $colors = [
        'Completed' => 'bg-green-500',
        'In Progress' => 'bg-blue-500',
        'Delayed' => 'bg-red-500',
        'Not Started' => 'bg-gray-500',
        'On Hold' => 'bg-yellow-500',
        'Active' => 'bg-blue-500',
        'Planning' => 'bg-purple-500'
    ];

    return $colors[$status] ?? 'bg-gray-500';
}

/**
 * Get deadline status (untuk warning)
 */
function get_deadline_status($due_date, $status)
{
    if ($status == 'Completed') return 'completed';

    $days = days_difference($due_date);

    if ($days < 0) return 'overdue'; // Terlambat
    if ($days <= 7) return 'urgent'; // Deadline 7 hari
    return 'normal';
}

/**
 * Hitung progress keseluruhan PON berdasarkan tasks
 */
function calculate_pon_progress($conn, $pon_id)
{
    $query = "SELECT AVG(progress) as avg_progress FROM tasks WHERE pon_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    return round($data['avg_progress'] ?? 0, 2);
}

/**
 * Get dashboard statistics
 */
function get_dashboard_stats($conn, $user_role = null)
{
    $stats = [];

    // Total Projects
    $query = "SELECT COUNT(*) as total FROM pon WHERE status NOT IN ('Cancelled', 'Completed')";
    $result = $conn->query($query);
    $stats['total_projects'] = $result->fetch_assoc()['total'];

    // Active Projects (dalam progress)
    $query = "SELECT COUNT(*) as total FROM pon WHERE status NOT IN ('Cancelled', 'Completed', 'Planning')";
    $result = $conn->query($query);
    $stats['active_projects'] = $result->fetch_assoc()['total'];

    // Completed Projects
    $query = "SELECT COUNT(*) as total FROM pon WHERE status = 'Completed'";
    $result = $conn->query($query);
    $stats['completed_projects'] = $result->fetch_assoc()['total'];

    // Delayed Projects (ada task yang terlambat)
    $query = "SELECT COUNT(DISTINCT pon_id) as total FROM tasks 
              WHERE status != 'Completed' AND finish_date < CURDATE()";
    $result = $conn->query($query);
    $stats['delayed_projects'] = $result->fetch_assoc()['total'];

    // Total Weight (Fabrikasi + Logistik yang sudah complete)
    $query = "SELECT SUM(t.weight_value) as total_weight 
              FROM tasks t 
              WHERE t.phase IN ('Fabrication + Trial', 'Delivery') 
              AND t.status = 'Completed'";
    $result = $conn->query($query);
    $weight_data = $result->fetch_assoc();
    $stats['total_weight'] = $weight_data['total_weight'] ?? 0;

    // Fabrication Weight (hanya fabrikasi yang complete)
    $query = "SELECT SUM(t.weight_value) as fab_weight 
              FROM tasks t 
              WHERE t.phase = 'Fabrication + Trial' 
              AND t.status = 'Completed'";
    $result = $conn->query($query);
    $fab_data = $result->fetch_assoc();
    $stats['fabrication_weight'] = $fab_data['fab_weight'] ?? 0;

    return $stats;
}

/**
 * Get progress per division
 */
function get_division_progress($conn)
{
    $divisions = ['Engineering', 'Purchasing', 'Fabrikasi', 'Logistik'];
    $progress = [];

    foreach ($divisions as $div) {
        $query = "SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                    AVG(progress) as avg_progress
                  FROM tasks 
                  WHERE responsible_division = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $div);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        $progress[$div] = [
            'total_tasks' => $data['total_tasks'],
            'completed_tasks' => $data['completed_tasks'],
            'progress_percentage' => round($data['avg_progress'] ?? 0, 0)
        ];
    }

    return $progress;
}

/**
 * Get upcoming deadlines (7 hari ke depan)
 */
function get_upcoming_deadlines($conn, $days = 7)
{
    $query = "SELECT t.*, p.pon_number, p.project_name, t.phase
              FROM tasks t
              JOIN pon p ON t.pon_id = p.pon_id
              WHERE t.status != 'Completed'
              AND t.finish_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              ORDER BY t.finish_date ASC
              LIMIT 5";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $deadlines = [];
    while ($row = $result->fetch_assoc()) {
        $deadlines[] = $row;
    }

    return $deadlines;
}

/**
 * Get recent projects
 */
function get_recent_projects($conn, $limit = 5)
{
    $query = "SELECT 
                p.*,
                (SELECT AVG(progress) FROM tasks WHERE pon_id = p.pon_id) as overall_progress,
                u.full_name as created_by_name
              FROM pon p
              LEFT JOIN users u ON p.created_by = u.user_id
              WHERE p.status NOT IN ('Cancelled', 'Completed')
              ORDER BY p.created_at DESC
              LIMIT ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }

    return $projects;
}

/**
 * Get recent activities
 */
function get_recent_activities($conn, $limit = 10)
{
    $query = "SELECT 
                al.*,
                u.full_name,
                u.role
              FROM activity_logs al
              JOIN users u ON al.user_id = u.user_id
              ORDER BY al.created_at DESC
              LIMIT ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }

    return $activities;
}

/**
 * Get top projects by weight
 */
function get_top_projects_by_weight($conn, $limit = 5)
{
    $query = "SELECT 
                p.pon_number,
                p.project_name,
                p.client_name,
                SUM(t.weight_value) as total_weight
              FROM pon p
              LEFT JOIN tasks t ON p.pon_id = t.pon_id
              WHERE t.phase IN ('Fabrication + Trial', 'Delivery')
              AND t.status = 'Completed'
              GROUP BY p.pon_id
              ORDER BY total_weight DESC
              LIMIT ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }

    return $projects;
}

/**
 * Log activity to database
 */
function log_activity($conn, $user_id, $action, $description, $ip_address = null)
{
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
              VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);

    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}

/**
 * Check session timeout
 */
function check_session_timeout()
{
    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - $_SESSION['login_time'];

        if ($elapsed > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            redirect('modules/auth/login.php');
        }

        // Update login time
        $_SESSION['login_time'] = time();
    }
}

/**
 * Require login - redirect jika belum login
 */
function require_login()
{
    if (!isLoggedIn()) {
        redirect('modules/auth/login.php');
    }
    check_session_timeout();
}

/**
 * Require specific role
 */
function require_role($allowed_roles = [])
{
    require_login();

    if (!hasAnyRole($allowed_roles)) {
        die("Access Denied. You don't have permission to access this page.");
    }
}

/**
 * Permission functions untuk Material Management
 */

// Cek apakah user bisa manage material
function canManageMaterial()
{
    if (hasRole('Admin')) return true;
    if (hasRole('Engineering')) return true;
    return false;
}

function canManagePurchasing()
{
    if (!isset($_SESSION['role'])) return false;

    $allowed_roles = ['Admin', 'Purchasing'];
    return in_array($_SESSION['role'], $allowed_roles);
}

// Cek apakah user bisa view material
function canViewMaterial()
{
    // Semua role yang login bisa view material
    return isLoggedIn();
}

/**
 * ============================================
 * FABRICATION PERMISSION FUNCTIONS
 * ============================================
 */

/**
 * Cek apakah user bisa manage fabrication
 */
function canManageFabrication()
{
    if (!isset($_SESSION['role'])) return false;

    $allowed_roles = ['Admin', 'Fabrikasi'];
    return in_array($_SESSION['role'], $allowed_roles);
}

/**
 * Cek apakah user bisa view fabrication data
 */
function canViewFabrication()
{
    // Semua role yang login bisa view fabrication data
    return isLoggedIn();
}

/**
 * Cek apakah user bisa update QC
 */
function canManageQC()
{
    if (!isset($_SESSION['role'])) return false;

    $allowed_roles = ['Admin', 'Fabrikasi', 'QC'];
    return in_array($_SESSION['role'], $allowed_roles);
}

/**
 * Validate fabrication progress (0-100)
 */
function validateFabricationProgress($progress)
{
    $progress = (float)$progress;
    return $progress >= 0 && $progress <= 100;
}

/**
 * Get fabrication phase by progress percentage
 */
function getFabricationPhaseByProgress($progress)
{
    $progress = (float)$progress;

    if ($progress >= 80) return 'Final Assembly & Finishing';
    if ($progress >= 60) return 'Welding & Joining';
    if ($progress >= 40) return 'Component Assembly';
    if ($progress >= 20) return 'Cutting & Preparation';
    return 'Material Preparation';
}

/**
 * Log fabrication history
 */
function logFabricationHistory($conn, $material_id, $progress_from, $progress_to, $status_from, $status_to, $fabrication_phase, $qc_status, $notes)
{
    $stmt = $conn->prepare("INSERT INTO fabrication_history 
                           (material_id, progress_from, progress_to, status_from, status_to, fabrication_phase, qc_status, notes, updated_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "iddssssi",
        $material_id,
        $progress_from,
        $progress_to,
        $status_from,
        $status_to,
        $fabrication_phase,
        $qc_status,
        $notes,
        $_SESSION['user_id']
    );

    return $stmt->execute();
}
?>