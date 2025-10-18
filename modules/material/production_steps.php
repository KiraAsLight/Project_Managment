<?php

/**
 * Production steps modal (di-load via AJAX)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['material_id']) || empty($_GET['material_id'])) {
    die("Invalid Material ID");
}

$material_id = (int)$_GET['material_id'];
$conn = getDBConnection();

// Get material data
$stmt = $conn->prepare("SELECT ml.name, ml.assy_marking, ml.quantity, mp.notes 
                       FROM material_lists ml
                       LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
                       WHERE ml.material_id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    die("Material tidak ditemukan");
}

$production_steps = [
    [
        'step' => 'Material Preparation',
        'description' => 'Check material availability and quality',
        'status' => 'completed',
        'duration' => '1-2 hours',
        'operator' => 'Storekeeper'
    ],
    [
        'step' => 'Cutting & Shearing',
        'description' => 'Cut material to required dimensions',
        'status' => 'in_progress',
        'duration' => '2-4 hours',
        'operator' => 'CNC Operator'
    ],
    [
        'step' => 'Bending & Forming',
        'description' => 'Bend material as per design specifications',
        'status' => 'pending',
        'duration' => '3-5 hours',
        'operator' => 'Press Brake Operator'
    ],
    [
        'step' => 'Welding & Assembly',
        'description' => 'Weld components and assemble structure',
        'status' => 'pending',
        'duration' => '4-8 hours',
        'operator' => 'Welder'
    ],
    [
        'step' => 'Grinding & Finishing',
        'description' => 'Grind welds and finish surfaces',
        'status' => 'pending',
        'duration' => '2-3 hours',
        'operator' => 'Grinder'
    ],
    [
        'step' => 'Surface Treatment',
        'description' => 'Apply primer/paint or other treatments',
        'status' => 'pending',
        'duration' => '1-2 days',
        'operator' => 'Painter'
    ],
    [
        'step' => 'Quality Control',
        'description' => 'Final inspection and quality check',
        'status' => 'pending',
        'duration' => '1-2 hours',
        'operator' => 'QC Inspector'
    ],
    [
        'step' => 'Ready for Logistics',
        'description' => 'Material ready for delivery to site',
        'status' => 'pending',
        'duration' => '-',
        'operator' => 'Logistics'
    ]
];
?>

<div class="space-y-6">
    <!-- Material Header -->
    <div class="bg-gray-800 rounded-lg p-4">
        <h4 class="text-white font-semibold text-lg"><?php echo htmlspecialchars($material['name']); ?></h4>
        <div class="grid grid-cols-3 gap-4 mt-2 text-sm">
            <div>
                <span class="text-gray-400">Marking:</span>
                <span class="text-white ml-2"><?php echo htmlspecialchars($material['assy_marking'] ?? '-'); ?></span>
            </div>
            <div>
                <span class="text-gray-400">Quantity:</span>
                <span class="text-white ml-2"><?php echo $material['quantity']; ?> pcs</span>
            </div>
            <div>
                <span class="text-gray-400">Status:</span>
                <span class="text-orange-400 ml-2">In Production</span>
            </div>
        </div>
    </div>

    <!-- Production Timeline -->
    <div class="space-y-4">
        <h4 class="text-white font-semibold">Production Workflow</h4>

        <?php foreach ($production_steps as $index => $step): ?>
            <div class="flex items-start space-x-4 p-4 bg-gray-800 rounded-lg border-l-4 
                <?php echo $step['status'] == 'completed' ? 'border-green-500' : ($step['status'] == 'in_progress' ? 'border-orange-500' : 'border-gray-500'); ?>">

                <!-- Step Number -->
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center 
                    <?php echo $step['status'] == 'completed' ? 'bg-green-500' : ($step['status'] == 'in_progress' ? 'bg-orange-500' : 'bg-gray-600'); ?>">
                    <span class="text-white text-sm font-bold"><?php echo $index + 1; ?></span>
                </div>

                <!-- Step Content -->
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h5 class="text-white font-semibold"><?php echo $step['step']; ?></h5>
                        <span class="px-2 py-1 rounded text-xs 
                            <?php echo $step['status'] == 'completed' ? 'bg-green-600' : ($step['status'] == 'in_progress' ? 'bg-orange-600' : 'bg-gray-600'); ?> 
                            text-white">
                            <?php echo ucfirst(str_replace('_', ' ', $step['status'])); ?>
                        </span>
                    </div>

                    <p class="text-gray-400 text-sm mt-1"><?php echo $step['description']; ?></p>

                    <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                        <span><i class="fas fa-clock mr-1"></i> <?php echo $step['duration']; ?></span>
                        <span><i class="fas fa-user mr-1"></i> <?php echo $step['operator']; ?></span>
                    </div>

                    <!-- Action Buttons -->
                    <?php if ($step['status'] == 'in_progress'): ?>
                        <div class="mt-3 flex space-x-2">
                            <button onclick="updateStepStatus(<?php echo $material_id; ?>, '<?php echo $step['step']; ?>', 'completed')"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">
                                <i class="fas fa-check mr-1"></i>Mark Complete
                            </button>
                            <button onclick="showStepDetails(<?php echo $material_id; ?>, '<?php echo $step['step']; ?>')"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs">
                                <i class="fas fa-edit mr-1"></i>Update Details
                            </button>
                        </div>
                    <?php elseif ($step['status'] == 'pending'): ?>
                        <button onclick="updateStepStatus(<?php echo $material_id; ?>, '<?php echo $step['step']; ?>', 'in_progress')"
                            class="mt-3 bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-xs">
                            <i class="fas fa-play mr-1"></i>Start Step
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Production Summary -->
    <div class="bg-gray-800 rounded-lg p-4">
        <h4 class="text-white font-semibold mb-3">Production Summary</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-400">Total Steps:</span>
                <span class="text-white ml-2"><?php echo count($production_steps); ?></span>
            </div>
            <div>
                <span class="text-gray-400">Completed:</span>
                <span class="text-green-400 ml-2">
                    <?php echo count(array_filter($production_steps, function ($step) {
                        return $step['status'] == 'completed';
                    })); ?>
                </span>
            </div>
            <div>
                <span class="text-gray-400">In Progress:</span>
                <span class="text-orange-400 ml-2">
                    <?php echo count(array_filter($production_steps, function ($step) {
                        return $step['status'] == 'in_progress';
                    })); ?>
                </span>
            </div>
            <div>
                <span class="text-gray-400">Pending:</span>
                <span class="text-gray-400 ml-2">
                    <?php echo count(array_filter($production_steps, function ($step) {
                        return $step['status'] == 'pending';
                    })); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="flex justify-end space-x-3 pt-4">
        <button onclick="hideStepsModal()"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded">
            Close
        </button>
        <button onclick="printProductionSteps()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
            <i class="fas fa-print mr-2"></i>Print
        </button>
    </div>
</div>

<script>
    function updateStepStatus(materialId, stepName, status) {
        const formData = new FormData();
        formData.append('material_id', materialId);
        formData.append('step_name', stepName);
        formData.append('status', status);

        fetch('update_production_step.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Step status updated!');
                    hideStepsModal();
                    showProductionSteps(materialId);
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }

    function showStepDetails(materialId, stepName) {
        // Implement step details modal
        alert('Step details for: ' + stepName);
    }

    function printProductionSteps() {
        window.print();
    }
</script>

<?php closeDBConnection($conn); ?>