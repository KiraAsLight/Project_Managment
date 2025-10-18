<?php
// modules/material/material_detail.php

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

// Debug: log semua parameters
error_log("Material Detail - GET parameters: " . print_r($_GET, true));
error_log("Material Detail - POST parameters: " . print_r($_POST, true));

if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Show detailed error info
    echo "<div style='background:red; color:white; padding:20px;'>";
    echo "<h2>Invalid Material ID</h2>";
    echo "<p>No ID parameter provided</p>";
    echo "<p>GET parameters: " . print_r($_GET, true) . "</p>";
    echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
    echo "</div>";
    exit;
}

$material_id = (int)$_GET['id'];

// Validate ID
if ($material_id <= 0) {
    echo "<div style='background:red; color:white; padding:20px;'>";
    echo "<h2>Invalid Material ID</h2>";
    echo "<p>ID must be positive integer</p>";
    echo "<p>Provided ID: " . $_GET['id'] . "</p>";
    echo "<p>Converted to int: " . $material_id . "</p>";
    echo "</div>";
    exit;
}

$conn = getDBConnection();

// Debug query
error_log("Material Detail - Querying for material_id: " . $material_id);

// Get material data dengan detail lengkap
$query = "SELECT 
            ml.*,
            p.pon_number,
            p.project_name,
            p.client_name,
            t.task_name,
            t.phase,
            u.full_name as created_by_name,
            mp_eng.status as eng_status,
            mp_eng.progress_percent as eng_progress,
            mp_eng.notes as eng_notes,
            mp_pur.status as pur_status,
            mp_pur.progress_percent as pur_progress,
            mp_pur.notes as pur_notes,
            mp_fab.status as fab_status,
            mp_fab.progress_percent as fab_progress,
            mp_fab.notes as fab_notes,
            mp_log.status as log_status,
            mp_log.progress_percent as log_progress,
            mp_log.notes as log_notes
          FROM material_lists ml
          JOIN pon p ON ml.pon_id = p.pon_id
          JOIN tasks t ON ml.task_id = t.task_id
          LEFT JOIN users u ON ml.created_by = u.user_id
          LEFT JOIN material_progress mp_eng ON ml.material_id = mp_eng.material_id AND mp_eng.division = 'Engineering'
          LEFT JOIN material_progress mp_pur ON ml.material_id = mp_pur.material_id AND mp_pur.division = 'Purchasing'
          LEFT JOIN material_progress mp_fab ON ml.material_id = mp_fab.material_id AND mp_fab.division = 'Fabrikasi'
          LEFT JOIN material_progress mp_log ON ml.material_id = mp_log.material_id AND mp_log.division = 'Logistik'
          WHERE ml.material_id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    echo "<div style='background:orange; color:white; padding:20px;'>";
    echo "<h2>Material Not Found</h2>";
    echo "<p>Material ID: " . $material_id . " tidak ditemukan dalam database</p>";
    echo "<p>Query executed: " . $query . "</p>";
    echo "<p><a href='javascript:history.back()'>Kembali</a></p>";
    echo "</div>";
    exit;
}

// Debug success
error_log("Material Detail - Found material: " . $material['name']);

$page_title = "Material Detail - " . $material['name'];
include '../../includes/header.php';
?>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Material Detail</h1>
                    <div class="text-gray-400">
                        <span class="text-blue-400"><?php echo htmlspecialchars($material['pon_number']); ?></span> •
                        <?php echo htmlspecialchars($material['project_name']); ?> •
                        <?php echo htmlspecialchars($material['client_name']); ?>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="javascript:history.back()"
                        class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                    <button onclick="showEditModal(<?php echo $material_id; ?>)"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit Material</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Material Information -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

            <!-- Basic Information -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    Basic Information
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-gray-400 text-sm">Material Name</label>
                        <p class="text-white font-semibold text-lg"><?php echo htmlspecialchars($material['name']); ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-gray-400 text-sm">Assembly Marking</label>
                            <p class="text-white"><?php echo htmlspecialchars($material['assy_marking'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <label class="text-gray-400 text-sm">Revision</label>
                            <p class="text-white"><?php echo htmlspecialchars($material['rv'] ?? '-'); ?></p>
                        </div>
                    </div>

                    <div>
                        <label class="text-gray-400 text-sm">Task</label>
                        <p class="text-white"><?php echo htmlspecialchars($material['task_name']); ?></p>
                    </div>

                    <div>
                        <label class="text-gray-400 text-sm">Phase</label>
                        <span class="px-2 py-1 bg-purple-600 text-white rounded-full text-xs">
                            <?php echo $material['phase']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quantity & Dimensions -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-ruler-combined text-green-400 mr-2"></i>
                    Specifications
                </h2>

                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-gray-400 text-sm">Quantity</label>
                            <p class="text-white font-semibold text-xl"><?php echo $material['quantity']; ?> pcs</p>
                        </div>
                        <div>
                            <label class="text-gray-400 text-sm">Dimensions</label>
                            <p class="text-white"><?php echo htmlspecialchars($material['dimensions'] ?? '-'); ?></p>
                        </div>
                    </div>

                    <div>
                        <label class="text-gray-400 text-sm">Length</label>
                        <p class="text-white">
                            <?php echo $material['length_mm'] ? number_format($material['length_mm']) . ' mm' : '-'; ?>
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-gray-400 text-sm">Unit Weight</label>
                            <p class="text-white"><?php echo number_format($material['weight_kg'], 2); ?> kg</p>
                        </div>
                        <div>
                            <label class="text-gray-400 text-sm">Total Weight</label>
                            <p class="text-green-400 font-semibold text-lg">
                                <?php echo number_format($material['total_weight_kg'], 2); ?> kg
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-chart-line text-yellow-400 mr-2"></i>
                    Progress Overview
                </h2>

                <div class="space-y-4">
                    <?php
                    $divisions = [
                        'Engineering' => ['status' => $material['eng_status'], 'progress' => $material['eng_progress'], 'color' => 'blue'],
                        'Purchasing' => ['status' => $material['pur_status'], 'progress' => $material['pur_progress'], 'color' => 'purple'],
                        'Fabrikasi' => ['status' => $material['fab_status'], 'progress' => $material['fab_progress'], 'color' => 'orange'],
                        'Logistik' => ['status' => $material['log_status'], 'progress' => $material['log_progress'], 'color' => 'green']
                    ];

                    foreach ($divisions as $div => $data):
                    ?>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-300 text-sm"><?php echo $div; ?></span>
                                <span class="text-white font-semibold"><?php echo $data['progress']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="h-2 rounded-full bg-<?php echo $data['color']; ?>-500"
                                    style="width: <?php echo $data['progress']; ?>%"></div>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-400 text-xs">Status:</span>
                                <span class="text-<?php echo $data['color']; ?>-300 text-xs font-semibold">
                                    <?php echo $data['status'] ?? 'Pending'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Remarks & Notes -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-sticky-note text-red-400 mr-2"></i>
                    Remarks & Notes
                </h2>

                <div class="space-y-4">
                    <div>
                        <label class="text-gray-400 text-sm">Remarks</label>
                        <p class="text-white bg-gray-800 p-3 rounded-lg mt-1">
                            <?php echo !empty($material['remarks']) ? nl2br(htmlspecialchars($material['remarks'])) : '<span class="text-gray-500">No remarks</span>'; ?>
                        </p>
                    </div>

                    <!-- Engineering Notes -->
                    <?php if (!empty($material['eng_notes'])): ?>
                        <div>
                            <label class="text-gray-400 text-sm">Engineering Notes</label>
                            <p class="text-white bg-blue-900 bg-opacity-30 p-3 rounded-lg mt-1">
                                <?php echo nl2br(htmlspecialchars($material['eng_notes'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-database text-indigo-400 mr-2"></i>
                    System Information
                </h2>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Created By</span>
                        <span class="text-white"><?php echo htmlspecialchars($material['created_by_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Created At</span>
                        <span class="text-white"><?php echo date('M j, Y H:i', strtotime($material['created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Material ID</span>
                        <span class="text-white">#<?php echo $material_id; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">PON ID</span>
                        <span class="text-white">#<?php echo $material['pon_id']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Task ID</span>
                        <span class="text-white">#<?php echo $material['task_id']; ?></span>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-dark-light rounded-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">Edit Material</h3>
            <button onclick="hideEditModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="editModalContent">
            <!-- Content akan diisi via JavaScript -->
        </div>
    </div>
</div>

<script>
    function showEditModal(materialId) {
        fetch('material_edit_form.php?id=' + materialId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('editModalContent').innerHTML = html;
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('editModal').classList.add('flex');
            })
            .catch(error => {
                console.error('Error loading edit form:', error);
                alert('Error loading edit form');
            });
    }

    function hideEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) hideEditModal();
    });
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>