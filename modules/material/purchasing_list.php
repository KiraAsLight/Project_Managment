<?php

/**
 * Purchasing Material List - Purchasing Division
 * Untuk melihat dan mengelola material list dari Engineering
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Hanya Purchasing dan Admin yang bisa akses
require_role(['Admin', 'Purchasing']);

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$pon_id = isset($_GET['pon_id']) ? (int)$_GET['pon_id'] : 0;
$conn = getDBConnection();

// Build filter conditions
$where_conditions = ["1=1"];
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

// Status filter
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "mp.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get material lists with purchasing progress
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
            ml.created_at,
            mp.status as purchasing_status,
            mp.progress_percent as purchasing_progress,
            mp.notes as purchasing_notes,
            mp.started_at as purchasing_started,
            mp.completed_at as purchasing_completed,
            u.full_name as created_by_name,
            p.pon_number,
            p.project_name,
            t.task_name
          FROM material_lists ml
          JOIN pon p ON ml.pon_id = p.pon_id
          JOIN tasks t ON ml.task_id = t.task_id
          LEFT JOIN users u ON ml.created_by = u.user_id
          LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Purchasing'
          WHERE " . implode(" AND ", $where_conditions) . "
          ORDER BY ml.material_id ASC";

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

while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
    $total_weight += $row['total_weight_kg'];
    $total_items++;
}

// Calculate statistics
$pending_count = count(array_filter($materials, function ($m) {
    return $m['purchasing_status'] == 'Pending';
}));
$in_progress_count = count(array_filter($materials, function ($m) {
    return $m['purchasing_status'] == 'In Progress';
}));
$completed_count = count(array_filter($materials, function ($m) {
    return $m['purchasing_status'] == 'Completed';
}));

$page_title = "Purchasing Material List";
include '../../includes/header.php';
?>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Purchasing Material List</h1>
                    <div class="text-gray-400">
                        <?php if (isset($task)): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($task['pon_number']); ?></span> •
                            <?php echo htmlspecialchars($task['task_name']); ?> •
                        <?php elseif (isset($pon)): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($pon['pon_number']); ?></span> •
                        <?php endif; ?>
                        <span class="text-green-400">Purchasing Division</span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <?php if (isset($task)): ?>
                        <a href="../tasks/division_tasks.php?pon_id=<?php echo $task['pon_id']; ?>&division=Purchasing"
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

                    <!-- Export Button -->
                    <button onclick="exportToExcel()"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Items</p>
                        <p class="text-2xl font-bold text-white"><?php echo $total_items; ?></p>
                    </div>
                    <i class="fas fa-cubes text-blue-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Pending</p>
                        <p class="text-2xl font-bold text-white"><?php echo $pending_count; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">In Progress</p>
                        <p class="text-2xl font-bold text-white"><?php echo $in_progress_count; ?></p>
                    </div>
                    <i class="fas fa-spinner text-orange-400 text-2xl"></i>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Completed</p>
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
                        <label class="block text-gray-300 text-sm font-medium mb-1">Status Filter</label>
                        <select id="statusFilter" onchange="filterByStatus(this.value)"
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-1">Search</label>
                        <input type="text" id="searchInput" placeholder="Search materials..."
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500 w-64"
                            onkeyup="filterMaterials()">
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-gray-400 text-sm">Total Weight</p>
                    <p class="text-white font-bold text-xl"><?php echo number_format($total_weight, 2); ?> kg</p>
                </div>
            </div>
        </div>

        <!-- Materials Table -->
        <div class="bg-dark-light rounded-xl shadow-xl">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">
                    Material List (<?php echo count($materials); ?> items)
                </h2>
            </div>

            <?php if (empty($materials)): ?>
                <div class="p-8 text-center">
                    <i class="fas fa-box-open text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400 text-lg">No materials found</p>
                    <p class="text-gray-500 text-sm mt-2">Engineering division needs to upload material list first</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full" id="materialsTable">
                        <thead class="bg-gray-800 text-gray-400 text-sm">
                            <tr>
                                <th class="px-6 py-4 text-left">No</th>
                                <th class="px-6 py-4 text-left">Material Info</th>
                                <th class="px-6 py-4 text-left">Quantity</th>
                                <th class="px-6 py-4 text-left">Weight</th>
                                <th class="px-6 py-4 text-center">Progress</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($materials as $index => $material): ?>
                                <tr class="hover:bg-gray-800 transition material-row"
                                    data-status="<?php echo strtolower($material['purchasing_status']); ?>"
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
                                                <?php if (!empty($material['dimensions'])): ?>
                                                    <span class="mr-3">Dim: <?php echo htmlspecialchars($material['dimensions']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($material['length_mm'])): ?>
                                                    <span>Length: <?php echo number_format($material['length_mm']); ?> mm</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($material['remarks'])): ?>
                                                <p class="text-gray-500 text-xs mt-1"><?php echo htmlspecialchars($material['remarks']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="text-white font-semibold"><?php echo $material['quantity']; ?> pcs</span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <div>Unit: <?php echo number_format($material['weight_kg'], 2); ?> kg</div>
                                            <div class="text-blue-400 font-semibold">
                                                Total: <?php echo number_format($material['total_weight_kg'], 2); ?> kg
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center">
                                            <span class="text-white font-bold text-lg">
                                                <?php echo $material['purchasing_progress']; ?>%
                                            </span>
                                            <div class="w-24 bg-gray-700 rounded-full h-2 mt-1">
                                                <div class="h-2 rounded-full 
                                                    <?php echo $material['purchasing_progress'] == 100 ? 'bg-green-500' : ($material['purchasing_progress'] > 0 ? 'bg-blue-500' : 'bg-gray-500'); ?>"
                                                    style="width: <?php echo $material['purchasing_progress']; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold 
                                            <?php echo $material['purchasing_status'] == 'Completed' ? 'bg-green-600' : ($material['purchasing_status'] == 'In Progress' ? 'bg-yellow-600' : 'bg-gray-600'); ?> 
                                            text-white">
                                            <?php echo $material['purchasing_status']; ?>
                                        </span>
                                        <?php if ($material['purchasing_completed']): ?>
                                            <div class="text-gray-400 text-xs mt-1">
                                                <?php echo date('M j', strtotime($material['purchasing_completed'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <!-- Update Progress Button -->
                                            <button onclick="showUpdateModal(<?php echo $material['material_id']; ?>, '<?php echo $material['name']; ?>')"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm"
                                                title="Update Progress">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- View Details -->
                                            <button onclick="showMaterialDetails(<?php echo $material['material_id']; ?>)"
                                                class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded text-sm"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Quick Actions -->
                                            <div class="relative group">
                                                <button class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-sm"
                                                    title="Quick Actions">
                                                    <i class="fas fa-bolt"></i>
                                                </button>
                                                <div class="absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-xl hidden group-hover:block z-10">
                                                    <div class="py-1">
                                                        <button onclick="quickUpdate(<?php echo $material['material_id']; ?>, 'In Progress')"
                                                            class="block w-full text-left px-4 py-2 text-sm text-yellow-400 hover:bg-gray-700">
                                                            <i class="fas fa-play mr-2"></i>Start Progress
                                                        </button>
                                                        <button onclick="quickUpdate(<?php echo $material['material_id']; ?>, 'Completed')"
                                                            class="block w-full text-left px-4 py-2 text-sm text-green-400 hover:bg-gray-700">
                                                            <i class="fas fa-check mr-2"></i>Mark Complete
                                                        </button>
                                                        <button onclick="showProcurementModal(<?php echo $material['material_id']; ?>)"
                                                            class="block w-full text-left px-4 py-2 text-sm text-blue-400 hover:bg-gray-700">
                                                            <i class="fas fa-shopping-cart mr-2"></i>Procurement Info
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

<!-- Update Progress Modal -->
<div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Update Procurement Progress</h3>
            <button onclick="hideUpdateModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Material Details Modal -->
<div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Material Details</h3>
            <button onclick="hideDetailsModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailsContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Procurement Info Modal -->
<div id="procurementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Procurement Information</h3>
            <button onclick="hideProcurementModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="procurementContent">
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

    function filterMaterials() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.material-row');

        rows.forEach(row => {
            const materialName = row.getAttribute('data-name');
            const status = row.getAttribute('data-status');
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();

            const nameMatch = materialName.includes(searchTerm);
            const statusMatch = statusFilter === 'all' || status === statusFilter.toLowerCase();

            if (nameMatch && statusMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Modal functions
    function showUpdateModal(materialId, materialName) {
        fetch(`update_progress_form.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalContent').innerHTML = html;
                document.getElementById('updateModal').classList.remove('hidden');
                document.getElementById('updateModal').classList.add('flex');
            });
    }

    function hideUpdateModal() {
        document.getElementById('updateModal').classList.add('hidden');
        document.getElementById('updateModal').classList.remove('flex');
    }

    function showMaterialDetails(materialId) {
        fetch(`material_details.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('detailsContent').innerHTML = html;
                document.getElementById('detailsModal').classList.remove('hidden');
                document.getElementById('detailsModal').classList.add('flex');
            });
    }

    function hideDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }

    function showProcurementModal(materialId) {
        fetch(`procurement_info.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('procurementContent').innerHTML = html;
                document.getElementById('procurementModal').classList.remove('hidden');
                document.getElementById('procurementModal').classList.add('flex');
            });
    }

    function hideProcurementModal() {
        document.getElementById('procurementModal').classList.add('hidden');
        document.getElementById('procurementModal').classList.remove('flex');
    }

    // Quick update functions
    function quickUpdate(materialId, status) {
        if (confirm(`Set status to ${status}?`)) {
            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('status', status);
            formData.append('progress_percent', status === 'Completed' ? 100 : 50);

            fetch('quick_update.php', {
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

    // Export to Excel
    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        window.open(`export_purchasing.php?${params.toString()}`, '_blank');
    }

    // Close modals when clicking outside
    document.getElementById('updateModal').addEventListener('click', function(e) {
        if (e.target === this) hideUpdateModal();
    });

    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) hideDetailsModal();
    });

    document.getElementById('procurementModal').addEventListener('click', function(e) {
        if (e.target === this) hideProcurementModal();
    });
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>