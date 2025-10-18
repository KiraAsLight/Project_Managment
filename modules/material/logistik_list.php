<?php

/**
 * Logistik Material List - Logistik Division
 * Untuk melihat dan mengelola material yang sudah selesai diproduksi
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Hanya Logistik dan Admin yang bisa akses
require_role(['Admin', 'Logistik']);

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$pon_id = isset($_GET['pon_id']) ? (int)$_GET['pon_id'] : 0;
$location_filter = isset($_GET['location']) ? sanitize_input($_GET['location']) : 'all';
$conn = getDBConnection();

// Build filter conditions - hanya material yang fabrikasi-nya completed
$where_conditions = ["mp_fab.status = 'Completed'"];
$params = [];
$types = "";

if ($task_id > 0) {
    $where_conditions[] = "ml.task_id = ?";
    $params[] = $task_id;
    $types .= "i";

    // Get task info
    $task_stmt = $conn->prepare("SELECT t.*, p.pon_number, p.project_name 
                                FROM tasks t 
                                JOIN pon p ON t.pon_id = p.pon_id 
                                WHERE t.task_id = ?");
    $task_stmt->bind_param("i", $task_id);
    $task_stmt->execute();
    $task_result = $task_stmt->get_result();
    $task = $task_result->fetch_assoc();
}

if ($pon_id > 0) {
    $where_conditions[] = "ml.pon_id = ?";
    $params[] = $pon_id;
    $types .= "i";

    // Get PON info
    $pon_stmt = $conn->prepare("SELECT * FROM pon WHERE pon_id = ?");
    $pon_stmt->bind_param("i", $pon_id);
    $pon_stmt->execute();
    $pon_result = $pon_stmt->get_result();
    $pon = $pon_result->fetch_assoc();
}

// Status filter untuk logistik
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "mp_log.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Location filter
if (!empty($location_filter) && $location_filter != 'all') {
    $where_conditions[] = "ml.current_location = ?";
    $params[] = $location_filter;
    $types .= "s";
}

// Get material lists dengan fabrikasi completed dan logistik progress
$query = "SELECT 
            ml.material_id,
            ml.assy_marking,
            ml.rv,
            ml.name,
            ml.quantity,
            ml.dimensions,
            ml.length_mm,
            ml.weight_kg,
            ml.total_weight_kg,
            ml.remarks,
            ml.current_location,
            ml.destination_site,
            mp_log.status as logistik_status,
            mp_log.progress_percent as logistik_progress,
            mp_log.notes as logistik_notes,
            mp_log.started_at as logistik_started,
            mp_log.completed_at as logistik_completed,
            mp_fab.completed_at as fabrication_completed,
            u.full_name as created_by_name,
            p.pon_number,
            p.project_name,
            t.task_name,
            p.contract_address
          FROM material_lists ml
          JOIN pon p ON ml.pon_id = p.pon_id
          JOIN tasks t ON ml.task_id = t.task_id
          LEFT JOIN users u ON ml.created_by = u.user_id
          LEFT JOIN material_progress mp_fab ON ml.material_id = mp_fab.material_id AND mp_fab.division = 'Fabrikasi'
          LEFT JOIN material_progress mp_log ON ml.material_id = mp_log.material_id AND mp_log.division = 'Logistik'
          WHERE " . implode(" AND ", $where_conditions) . "
          ORDER BY mp_fab.completed_at ASC, ml.material_id ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$materials = [];
$total_weight = 0;
$total_items = 0;
$workshop_weight = 0;
$site_weight = 0;

while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
    $total_weight += $row['total_weight_kg'];
    $total_items++;

    if ($row['current_location'] == 'workshop') {
        $workshop_weight += $row['total_weight_kg'];
    } elseif ($row['current_location'] == 'site') {
        $site_weight += $row['total_weight_kg'];
    }
}

// Calculate statistics
$pending_count = count(array_filter($materials, function ($m) {
    return $m['logistik_status'] == 'Pending' || $m['logistik_status'] == null;
}));
$in_progress_count = count(array_filter($materials, function ($m) {
    return $m['logistik_status'] == 'In Progress';
}));
$completed_count = count(array_filter($materials, function ($m) {
    return $m['logistik_status'] == 'Completed';
}));

// Location statistics
$workshop_count = count(array_filter($materials, function ($m) {
    return $m['current_location'] == 'workshop';
}));
$site_count = count(array_filter($materials, function ($m) {
    return $m['current_location'] == 'site';
}));
$in_transit_count = count(array_filter($materials, function ($m) {
    return $m['current_location'] == 'in_transit';
}));

$page_title = "Logistik Material List";
include '../../includes/header.php';
?>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Logistik Material Management</h1>
                    <div class="text-gray-400">
                        <?php if (isset($task)): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($task['pon_number']); ?></span> •
                            <?php echo htmlspecialchars($task['task_name']); ?> •
                        <?php elseif (isset($pon)): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($pon['pon_number']); ?></span> •
                        <?php endif; ?>
                        <span class="text-purple-400">Logistik Division</span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <?php if (isset($task)): ?>
                        <a href="../tasks/division_tasks.php?pon_id=<?php echo $task['pon_id']; ?>&division=Logistik"
                            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Tasks</span>
                        </a>
                    <?php else: ?>
                        <a href="../tasks/manage.php"
                            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Task List</span>
                        </a>
                    <?php endif; ?>

                    <!-- Shipping Plan -->
                    <button onclick="showShippingPlan()"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Shipping Plan</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Location Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Ready</p>
                        <p class="text-2xl font-bold text-white"><?php echo $total_items; ?></p>
                        <p class="text-purple-400 text-sm"><?php echo number_format($total_weight, 2); ?> kg</p>
                    </div>
                    <i class="fas fa-boxes text-purple-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">In Workshop</p>
                        <p class="text-2xl font-bold text-white"><?php echo $workshop_count; ?></p>
                        <p class="text-orange-400 text-sm"><?php echo number_format($workshop_weight, 2); ?> kg</p>
                    </div>
                    <i class="fas fa-warehouse text-orange-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">In Transit</p>
                        <p class="text-2xl font-bold text-white"><?php echo $in_transit_count; ?></p>
                        <p class="text-blue-400 text-sm">-</p>
                    </div>
                    <i class="fas fa-truck-moving text-blue-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">At Site</p>
                        <p class="text-2xl font-bold text-white"><?php echo $site_count; ?></p>
                        <p class="text-green-400 text-sm"><?php echo number_format($site_weight, 2); ?> kg</p>
                    </div>
                    <i class="fas fa-map-marker-alt text-green-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Progress Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Pending Delivery</p>
                        <p class="text-2xl font-bold text-white"><?php echo $pending_count; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">In Delivery</p>
                        <p class="text-2xl font-bold text-white"><?php echo $in_progress_count; ?></p>
                    </div>
                    <i class="fas fa-shipping-fast text-blue-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Delivered</p>
                        <p class="text-2xl font-bold text-white"><?php echo $completed_count; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-dark-light rounded-xl p-6 shadow-xl mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-1">Delivery Status</label>
                        <select id="statusFilter" onchange="filterByStatus(this.value)"
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-purple-500">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Delivery</option>
                            <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Delivered</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-1">Location</label>
                        <select id="locationFilter" onchange="filterByLocation(this.value)"
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-purple-500">
                            <option value="all" <?php echo $location_filter == 'all' ? 'selected' : ''; ?>>All Locations</option>
                            <option value="workshop" <?php echo $location_filter == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                            <option value="in_transit" <?php echo $location_filter == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                            <option value="site" <?php echo $location_filter == 'site' ? 'selected' : ''; ?>>At Site</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-1">Search Material</label>
                        <input type="text" id="searchInput" placeholder="Search materials..."
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-purple-500 w-64"
                            onkeyup="filterMaterials()">
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-gray-400 text-sm">Delivery Progress</p>
                    <p class="text-white font-bold text-xl">
                        <?php echo $total_items > 0 ? round(($completed_count / $total_items) * 100) : 0; ?>%
                    </p>
                    <p class="text-purple-400 text-sm">
                        <?php echo $completed_count; ?> of <?php echo $total_items; ?> delivered
                    </p>
                </div>
            </div>
        </div>

        <!-- Materials Table -->
        <div class="bg-dark-light rounded-xl shadow-xl">
            <div class="p-6 border-b border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white">
                            Logistics Materials (<?php echo count($materials); ?> items ready)
                        </h2>
                        <p class="text-gray-400 text-sm mt-1">
                            Materials dengan production completed dan siap untuk delivery
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="showBulkUpdateModal()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-edit"></i>
                            <span>Bulk Update</span>
                        </button>
                        <button onclick="exportDeliveryReport()"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-file-excel"></i>
                            <span>Export Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($materials)): ?>
                <div class="p-8 text-center">
                    <i class="fas fa-truck-loading text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400 text-lg">No materials ready for delivery</p>
                    <p class="text-gray-500 text-sm mt-2">
                        Waiting for Fabrikasi division to complete production
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full" id="materialsTable">
                        <thead class="bg-gray-800 text-gray-400 text-sm">
                            <tr>
                                <th class="px-6 py-4 text-left">No</th>
                                <th class="px-6 py-4 text-left">Material Info</th>
                                <th class="px-6 py-4 text-left">Location</th>
                                <th class="px-6 py-4 text-left">Destination</th>
                                <th class="px-6 py-4 text-center">Progress</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($materials as $index => $material): ?>
                                <tr class="hover:bg-gray-800 transition material-row"
                                    data-status="<?php echo strtolower($material['logistik_status'] ?? 'pending'); ?>"
                                    data-location="<?php echo $material['current_location'] ?? 'workshop'; ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($material['name'])); ?>">
                                    <td class="px-6 py-4 text-gray-300"><?php echo $index + 1; ?></td>

                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-white font-semibold">
                                                <?php echo htmlspecialchars($material['name']); ?>
                                            </p>
                                            <div class="text-gray-400 text-sm mt-1">
                                                <?php if (!empty($material['assy_marking'])): ?>
                                                    <span class="mr-3">Marking: <?php echo htmlspecialchars($material['assy_marking']); ?></span>
                                                <?php endif; ?>
                                                <span class="mr-3">Qty: <?php echo $material['quantity']; ?> pcs</span>
                                                <span>Weight: <?php echo number_format($material['total_weight_kg'], 2); ?> kg</span>
                                            </div>
                                            <p class="text-gray-500 text-xs mt-1">
                                                Production completed: <?php echo date('M j, Y', strtotime($material['fabrication_completed'])); ?>
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <?php
                                        $location = $material['current_location'] ?? 'workshop';
                                        $location_icons = [
                                            'workshop' => ['icon' => 'fa-warehouse', 'color' => 'text-orange-400'],
                                            'in_transit' => ['icon' => 'fa-truck-moving', 'color' => 'text-blue-400'],
                                            'site' => ['icon' => 'fa-map-marker-alt', 'color' => 'text-green-400']
                                        ];
                                        $location_labels = [
                                            'workshop' => 'Workshop',
                                            'in_transit' => 'In Transit',
                                            'site' => 'At Site'
                                        ];
                                        ?>
                                        <div class="flex items-center space-x-2">
                                            <i class="fas <?php echo $location_icons[$location]['icon']; ?> <?php echo $location_icons[$location]['color']; ?>"></i>
                                            <span class="text-white font-semibold"><?php echo $location_labels[$location]; ?></span>
                                        </div>
                                        <?php if ($location == 'in_transit'): ?>
                                            <div class="text-gray-400 text-xs mt-1">Estimated arrival</div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <p class="text-white"><?php echo htmlspecialchars($material['destination_site'] ?? $material['contract_address'] ?? 'Site'); ?></p>
                                            <p class="text-gray-400 text-xs mt-1">
                                                <?php echo htmlspecialchars($material['project_name']); ?>
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center">
                                            <span class="text-white font-bold text-lg">
                                                <?php echo $material['logistik_progress'] ?? 0; ?>%
                                            </span>
                                            <div class="w-24 bg-gray-700 rounded-full h-2 mt-1">
                                                <div class="h-2 rounded-full 
                                                    <?php echo ($material['logistik_progress'] ?? 0) == 100 ? 'bg-green-500' : (($material['logistik_progress'] ?? 0) > 0 ? 'bg-purple-500' : 'bg-gray-500'); ?>"
                                                    style="width: <?php echo $material['logistik_progress'] ?? 0; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold 
                                            <?php echo ($material['logistik_status'] ?? 'Pending') == 'Completed' ? 'bg-green-600' : (($material['logistik_status'] ?? 'Pending') == 'In Progress' ? 'bg-blue-600' : 'bg-gray-600'); ?> 
                                            text-white">
                                            <?php echo $material['logistik_status'] ?? 'Pending'; ?>
                                        </span>
                                        <?php if ($material['logistik_completed']): ?>
                                            <div class="text-gray-400 text-xs mt-1">
                                                <?php echo date('M j', strtotime($material['logistik_completed'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <!-- Update Logistics Button -->
                                            <button onclick="showLogisticsModal(<?php echo $material['material_id']; ?>, '<?php echo $material['name']; ?>')"
                                                class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm"
                                                title="Update Logistics">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- View Details -->
                                            <button onclick="showMaterialDetails(<?php echo $material['material_id']; ?>)"
                                                class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded text-sm"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Shipping Documents -->
                                            <button onclick="showShippingDocuments(<?php echo $material['material_id']; ?>)"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm"
                                                title="Shipping Documents">
                                                <i class="fas fa-file-invoice"></i>
                                            </button>

                                            <!-- Quick Actions -->
                                            <div class="relative group">
                                                <button class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded text-sm"
                                                    title="Quick Actions">
                                                    <i class="fas fa-bolt"></i>
                                                </button>
                                                <div class="absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-xl hidden group-hover:block z-10">
                                                    <div class="py-1">
                                                        <button onclick="quickLocationUpdate(<?php echo $material['material_id']; ?>, 'in_transit')"
                                                            class="block w-full text-left px-4 py-2 text-sm text-blue-400 hover:bg-gray-700">
                                                            <i class="fas fa-truck mr-2"></i>Mark In Transit
                                                        </button>
                                                        <button onclick="quickLocationUpdate(<?php echo $material['material_id']; ?>, 'site')"
                                                            class="block w-full text-left px-4 py-2 text-sm text-green-400 hover:bg-gray-700">
                                                            <i class="fas fa-check mr-2"></i>Mark Delivered
                                                        </button>
                                                        <button onclick="showDeliverySchedule(<?php echo $material['material_id']; ?>)"
                                                            <button onclick="showDeliverySchedule(<?php echo $material['material_id']; ?>)"
                                                            class="block w-full text-left px-4 py-2 text-sm text-purple-400 hover:bg-gray-700">
                                                            <i class="fas fa-calendar mr-2"></i>Schedule Delivery
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- Logistics Update Modal -->
<div id="logisticsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Update Logistics Progress</h3>
            <button onclick="hideLogisticsModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="logisticsModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Shipping Documents Modal -->
<div id="shippingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-4xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Shipping Documents</h3>
            <button onclick="hideShippingModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="shippingModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Delivery Schedule Modal -->
<div id="scheduleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Schedule Delivery</h3>
            <button onclick="hideScheduleModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="scheduleModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div id="bulkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Bulk Update</h3>
            <button onclick="hideBulkModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="bulkModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
    // Filter functions
    function filterByStatus(status) {
        const url = new URL(window.location.href);
        if (status && status !== 'all') {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        window.location.href = url.toString();
    }

    function filterByLocation(location) {
        const url = new URL(window.location.href);
        if (location && location !== 'all') {
            url.searchParams.set('location', location);
        } else {
            url.searchParams.delete('location');
        }
        window.location.href = url.toString();
    }

    function filterMaterials() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.material-row');

        rows.forEach(row => {
            const materialName = row.getAttribute('data-name');
            const status = row.getAttribute('data-status');
            const location = row.getAttribute('data-location');
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const locationFilter = document.getElementById('locationFilter').value;

            const nameMatch = materialName.includes(searchTerm);
            const statusMatch = statusFilter === 'all' || status === statusFilter.toLowerCase();
            const locationMatch = locationFilter === 'all' || location === locationFilter;

            if (nameMatch && statusMatch && locationMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Modal functions
    function showLogisticsModal(materialId, materialName) {
        fetch(`logistik_update_form.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('logisticsModalContent').innerHTML = html;
                document.getElementById('logisticsModal').classList.remove('hidden');
                document.getElementById('logisticsModal').classList.add('flex');
            });
    }

    function hideLogisticsModal() {
        document.getElementById('logisticsModal').classList.add('hidden');
        document.getElementById('logisticsModal').classList.remove('flex');
    }

    function showShippingDocuments(materialId) {
        fetch(`shipping_documents.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('shippingModalContent').innerHTML = html;
                document.getElementById('shippingModal').classList.remove('hidden');
                document.getElementById('shippingModal').classList.add('flex');
            });
    }

    function hideShippingModal() {
        document.getElementById('shippingModal').classList.add('hidden');
        document.getElementById('shippingModal').classList.remove('flex');
    }

    function showDeliverySchedule(materialId) {
        fetch(`delivery_schedule.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('scheduleModalContent').innerHTML = html;
                document.getElementById('scheduleModal').classList.remove('hidden');
                document.getElementById('scheduleModal').classList.add('flex');
            });
    }

    function hideScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
        document.getElementById('scheduleModal').classList.remove('flex');
    }

    function showBulkUpdateModal() {
        fetch(`bulk_update_form.php`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('bulkModalContent').innerHTML = html;
                document.getElementById('bulkModal').classList.remove('hidden');
                document.getElementById('bulkModal').classList.add('flex');
            });
    }

    function hideBulkModal() {
        document.getElementById('bulkModal').classList.add('hidden');
        document.getElementById('bulkModal').classList.remove('flex');
    }

    // Quick update functions
    function quickLocationUpdate(materialId, location) {
        if (confirm(`Update location to ${location}?`)) {
            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('location', location);

            fetch('update_location.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }

    // Report functions
    function showShippingPlan() {
        const params = new URLSearchParams(window.location.search);
        window.open(`shipping_plan.php?${params.toString()}`, '_blank');
    }

    function exportDeliveryReport() {
        const params = new URLSearchParams(window.location.search);
        window.open(`export_delivery_report.php?${params.toString()}`, '_blank');
    }

    // Close modals when clicking outside
    document.getElementById('logisticsModal').addEventListener('click', function(e) {
        if (e.target === this) hideLogisticsModal();
    });

    document.getElementById('shippingModal').addEventListener('click', function(e) {
        if (e.target === this) hideShippingModal();
    });

    document.getElementById('scheduleModal').addEventListener('click', function(e) {
        if (e.target === this) hideScheduleModal();
    });

    document.getElementById('bulkModal').addEventListener('click', function(e) {
        if (e.target === this) hideBulkModal();
    });
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>