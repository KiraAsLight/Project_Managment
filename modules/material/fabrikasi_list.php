<?php

/**
 * Fabrikasi Material List - Fabrikasi Division
 * Untuk melihat dan mengelola material list yang sudah di-procure
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Hanya Fabrikasi dan Admin yang bisa akses
require_role(['Admin', 'Fabrikasi']);

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$pon_id = isset($_GET['pon_id']) ? (int)$_GET['pon_id'] : 0;
$conn = getDBConnection();

// Build filter conditions - hanya material yang procurement-nya completed
$where_conditions = ["mp_pur.status = 'Completed'"];
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

// Status filter untuk fabrikasi
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "mp_fab.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get material lists dengan procurement completed dan fabrikasi progress
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
            mp_fab.status as fabrikasi_status,
            mp_fab.progress_percent as fabrikasi_progress,
            mp_fab.notes as fabrikasi_notes,
            mp_fab.started_at as fabrikasi_started,
            mp_fab.completed_at as fabrikasi_completed,
            mp_pur.completed_at as procurement_completed,
            u.full_name as created_by_name,
            p.pon_number,
            p.project_name,
            t.task_name
          FROM material_lists ml
          JOIN pon p ON ml.pon_id = p.pon_id
          JOIN tasks t ON ml.task_id = t.task_id
          LEFT JOIN users u ON ml.created_by = u.user_id
          LEFT JOIN material_progress mp_pur ON ml.material_id = mp_pur.material_id AND mp_pur.division = 'Purchasing'
          LEFT JOIN material_progress mp_fab ON ml.material_id = mp_fab.material_id AND mp_fab.division = 'Fabrikasi'
          WHERE " . implode(" AND ", $where_conditions) . "
          ORDER BY mp_pur.completed_at ASC, ml.material_id ASC";

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
    return $m['fabrikasi_status'] == 'Pending' || $m['fabrikasi_status'] == null;
}));
$in_progress_count = count(array_filter($materials, function ($m) {
    return $m['fabrikasi_status'] == 'In Progress';
}));
$completed_count = count(array_filter($materials, function ($m) {
    return $m['fabrikasi_status'] == 'Completed';
}));

$page_title = "Fabrikasi Material List";
include '../../includes/header.php';
?>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Fabrikasi Material List</h1>
                    <div class="text-gray-400">
                        <?php if (isset($task)): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($task['pon_number']); ?></span> •
                            <?php echo htmlspecialchars($task['task_name']); ?> •
                        <?php elseif (isset($pon)): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($pon['pon_number']); ?></span> •
                        <?php endif; ?>
                        <span class="text-orange-400">Fabrikasi Division</span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <?php if (isset($task)): ?>
                        <a href="../tasks/division_tasks.php?pon_id=<?php echo $task['pon_id']; ?>&division=Fabrikasi"
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

                    <!-- Production Report -->
                    <button onclick="showProductionReport()"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-chart-bar"></i>
                        <span>Production Report</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-dark-light rounded-xl p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Ready for Production</p>
                        <p class="text-2xl font-bold text-white"><?php echo $total_items; ?></p>
                    </div>
                    <i class="fas fa-industry text-blue-400 text-2xl"></i>
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
                        <p class="text-gray-400 text-sm">In Production</p>
                        <p class="text-2xl font-bold text-white"><?php echo $in_progress_count; ?></p>
                    </div>
                    <i class="fas fa-tools text-orange-400 text-2xl"></i>
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
                        <label class="block text-gray-300 text-sm font-medium mb-1">Production Status</label>
                        <select id="statusFilter" onchange="filterByStatus(this.value)"
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-orange-500">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Production</option>
                            <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 text-sm font-medium mb-1">Search Material</label>
                        <input type="text" id="searchInput" placeholder="Search materials..."
                            class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-orange-500 w-64"
                            onkeyup="filterMaterials()">
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-gray-400 text-sm">Total Production Weight</p>
                    <p class="text-white font-bold text-xl"><?php echo number_format($total_weight, 2); ?> kg</p>
                    <p class="text-orange-400 text-sm">
                        <?php echo $completed_count; ?> of <?php echo $total_items; ?> items completed
                    </p>
                </div>
            </div>
        </div>

        <!-- Production Workflow -->
        <div class="bg-dark-light rounded-xl p-6 shadow-xl mb-8">
            <h3 class="text-white font-semibold mb-4">Production Workflow Status</h3>
            <div class="grid grid-cols-5 gap-4 text-center">
                <?php
                $workflow_steps = [
                    ['icon' => 'fa-box', 'label' => 'Material Ready', 'count' => $total_items],
                    ['icon' => 'fa-ruler', 'label' => 'Cutting', 'count' => 0],
                    ['icon' => 'fa-fire', 'label' => 'Welding', 'count' => 0],
                    ['icon' => 'fa-puzzle-piece', 'label' => 'Assembly', 'count' => 0],
                    ['icon' => 'fa-check-double', 'label' => 'QC Passed', 'count' => $completed_count]
                ];

                foreach ($workflow_steps as $index => $step):
                ?>
                    <div class="bg-gray-800 rounded-lg p-4 <?php echo $index <= 0 ? 'border-l-4 border-orange-500' : ''; ?>">
                        <i class="fas <?php echo $step['icon']; ?> text-orange-400 text-2xl mb-2"></i>
                        <p class="text-white font-semibold text-lg"><?php echo $step['count']; ?></p>
                        <p class="text-gray-400 text-sm"><?php echo $step['label']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Materials Table -->
        <div class="bg-dark-light rounded-xl shadow-xl">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">
                    Production Materials (<?php echo count($materials); ?> items ready)
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    Materials dengan procurement completed dan siap untuk produksi
                </p>
            </div>

            <?php if (empty($materials)): ?>
                <div class="p-8 text-center">
                    <i class="fas fa-tools text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400 text-lg">No materials ready for production</p>
                    <p class="text-gray-500 text-sm mt-2">
                        Waiting for Purchasing division to complete procurement
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full" id="materialsTable">
                        <thead class="bg-gray-800 text-gray-400 text-sm">
                            <tr>
                                <th class="px-6 py-4 text-left">No</th>
                                <th class="px-6 py-4 text-left">Material Info</th>
                                <th class="px-6 py-4 text-left">Production Specs</th>
                                <th class="px-6 py-4 text-center">Progress</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($materials as $index => $material): ?>
                                <tr class="hover:bg-gray-800 transition material-row"
                                    data-status="<?php echo strtolower($material['fabrikasi_status'] ?? 'pending'); ?>"
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
                                                Procurement completed: <?php echo date('M j, Y', strtotime($material['procurement_completed'])); ?>
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <?php if (!empty($material['dimensions'])): ?>
                                                <div class="text-gray-300">Dimensions: <?php echo htmlspecialchars($material['dimensions']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($material['length_mm'])): ?>
                                                <div class="text-gray-300">Length: <?php echo number_format($material['length_mm']); ?> mm</div>
                                            <?php endif; ?>
                                            <?php if (!empty($material['remarks'])): ?>
                                                <div class="text-gray-400 text-xs mt-1">Note: <?php echo htmlspecialchars($material['remarks']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center">
                                            <span class="text-white font-bold text-lg">
                                                <?php echo $material['fabrikasi_progress'] ?? 0; ?>%
                                            </span>
                                            <div class="w-24 bg-gray-700 rounded-full h-2 mt-1">
                                                <div class="h-2 rounded-full 
                                                    <?php echo ($material['fabrikasi_progress'] ?? 0) == 100 ? 'bg-green-500' : (($material['fabrikasi_progress'] ?? 0) > 0 ? 'bg-orange-500' : 'bg-gray-500'); ?>"
                                                    style="width: <?php echo $material['fabrikasi_progress'] ?? 0; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold 
                                            <?php echo ($material['fabrikasi_status'] ?? 'Pending') == 'Completed' ? 'bg-green-600' : (($material['fabrikasi_status'] ?? 'Pending') == 'In Progress' ? 'bg-orange-600' : 'bg-gray-600'); ?> 
                                            text-white">
                                            <?php echo $material['fabrikasi_status'] ?? 'Pending'; ?>
                                        </span>
                                        <?php if ($material['fabrikasi_completed']): ?>
                                            <div class="text-gray-400 text-xs mt-1">
                                                <?php echo date('M j', strtotime($material['fabrikasi_completed'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <!-- Update Production Button -->
                                            <button onclick="showProductionModal(<?php echo $material['material_id']; ?>, '<?php echo $material['name']; ?>')"
                                                class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 rounded text-sm"
                                                title="Update Production">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- View Details -->
                                            <button onclick="showMaterialDetails(<?php echo $material['material_id']; ?>)"
                                                class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded text-sm"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Production Steps -->
                                            <button onclick="showProductionSteps(<?php echo $material['material_id']; ?>)"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm"
                                                title="Production Steps">
                                                <i class="fas fa-list-check"></i>
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
                                                            class="block w-full text-left px-4 py-2 text-sm text-orange-400 hover:bg-gray-700">
                                                            <i class="fas fa-play mr-2"></i>Start Production
                                                        </button>
                                                        <button onclick="quickUpdate(<?php echo $material['material_id']; ?>, 'Completed')"
                                                            class="block w-full text-left px-4 py-2 text-sm text-green-400 hover:bg-gray-700">
                                                            <i class="fas fa-check mr-2"></i>Mark Complete
                                                        </button>
                                                        <button onclick="showQCCheck(<?php echo $material['material_id']; ?>)"
                                                            class="block w-full text-left px-4 py-2 text-sm text-yellow-400 hover:bg-gray-700">
                                                            <i class="fas fa-clipboard-check mr-2"></i>QC Check
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

<!-- Production Update Modal -->
<div id="productionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Update Production Progress</h3>
            <button onclick="hideProductionModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="productionModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Production Steps Modal -->
<div id="stepsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Production Steps</h3>
            <button onclick="hideStepsModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="stepsModalContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<!-- QC Check Modal -->
<div id="qcModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Quality Control Check</h3>
            <button onclick="hideQcModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="qcModalContent">
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
    function showProductionModal(materialId, materialName) {
        fetch(`fabrikasi_update_form.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('productionModalContent').innerHTML = html;
                document.getElementById('productionModal').classList.remove('hidden');
                document.getElementById('productionModal').classList.add('flex');
            });
    }

    function hideProductionModal() {
        document.getElementById('productionModal').classList.add('hidden');
        document.getElementById('productionModal').classList.remove('flex');
    }

    function showProductionSteps(materialId) {
        fetch(`production_steps.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('stepsModalContent').innerHTML = html;
                document.getElementById('stepsModal').classList.remove('hidden');
                document.getElementById('stepsModal').classList.add('flex');
            });
    }

    function hideStepsModal() {
        document.getElementById('stepsModal').classList.add('hidden');
        document.getElementById('stepsModal').classList.remove('flex');
    }

    function showQCCheck(materialId) {
        fetch(`qc_check_form.php?material_id=${materialId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('qcModalContent').innerHTML = html;
                document.getElementById('qcModal').classList.remove('hidden');
                document.getElementById('qcModal').classList.add('flex');
            });
    }

    function hideQcModal() {
        document.getElementById('qcModal').classList.add('hidden');
        document.getElementById('qcModal').classList.remove('flex');
    }

    // Quick update functions
    function quickUpdate(materialId, status) {
        if (confirm(`Set production status to ${status}?`)) {
            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('status', status);
            formData.append('progress_percent', status === 'Completed' ? 100 : 50);
            formData.append('division', 'Fabrikasi');

            fetch('fabrikasi_quick_update.php', {
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

    // Production Report
    function showProductionReport() {
        const params = new URLSearchParams(window.location.search);
        window.open(`production_report.php?${params.toString()}`, '_blank');
    }

    // Close modals when clicking outside
    document.getElementById('productionModal').addEventListener('click', function(e) {
        if (e.target === this) hideProductionModal();
    });

    document.getElementById('stepsModal').addEventListener('click', function(e) {
        if (e.target === this) hideStepsModal();
    });

    document.getElementById('qcModal').addEventListener('click', function(e) {
        if (e.target === this) hideQcModal();
    });
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>