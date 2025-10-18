<?php

/**
 * Form untuk update progress fabrikasi (di-load via AJAX)
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
$stmt = $conn->prepare("SELECT ml.*, mp.status, mp.progress_percent, mp.notes 
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
?>

<form id="fabrikasiUpdateForm" class="space-y-4">
    <input type="hidden" name="material_id" value="<?php echo $material_id; ?>">
    <input type="hidden" name="division" value="Fabrikasi">

    <div class="bg-gray-800 rounded-lg p-4">
        <h4 class="text-white font-semibold mb-2">Material Information</h4>
        <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($material['name']); ?></p>
        <p class="text-gray-400 text-xs">
            Marking: <?php echo htmlspecialchars($material['assy_marking'] ?? '-'); ?> |
            Qty: <?php echo $material['quantity']; ?> pcs |
            Weight: <?php echo number_format($material['total_weight_kg'], 2); ?> kg
        </p>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Production Status</label>
        <select name="status" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-orange-500">
            <option value="Pending" <?php echo $material['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="In Progress" <?php echo $material['status'] == 'In Progress' ? 'selected' : ''; ?>>In Production</option>
            <option value="Completed" <?php echo $material['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
        </select>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">
            Production Progress: <span id="progressValueDisplay" class="text-orange-400 font-bold"><?php echo $material['progress_percent']; ?>%</span>
        </label>
        <input type="range" name="progress_percent" id="progressSlider"
            min="0" max="100" value="<?php echo $material['progress_percent']; ?>"
            class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-orange-600"
            oninput="updateProgressDisplay(this.value)">
        <div class="flex justify-between text-xs text-gray-500 mt-1">
            <span>0%</span>
            <span>25%</span>
            <span>50%</span>
            <span>75%</span>
            <span>100%</span>
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Production Steps</label>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="material_check" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Material Check</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="cutting" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Cutting</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="bending" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Bending</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="welding" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Welding</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="assembly" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Assembly</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="grinding" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Grinding</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="surface_treatment" class="mr-2 accent-orange-600">
                <span class="text-gray-300">Surface Treatment</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="steps[]" value="qc_inspection" class="mr-2 accent-orange-600">
                <span class="text-gray-300">QC Inspection</span>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Production Start</label>
            <input type="datetime-local" name="production_start"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-orange-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Production Finish</label>
            <input type="datetime-local" name="production_finish"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-orange-500">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Operator Name</label>
            <input type="text" name="operator_name" placeholder="Operator name"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-orange-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Machine Used</label>
            <input type="text" name="machine_used" placeholder="Machine number/name"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-orange-500">
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Production Notes</label>
        <textarea name="notes" rows="3" placeholder="Production notes, issues, or special instructions..."
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-orange-500"><?php echo htmlspecialchars($material['notes'] ?? ''); ?></textarea>
    </div>

    <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="hideProductionModal()"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded">
            Cancel
        </button>
        <button type="submit"
            class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded">
            Update Production
        </button>
    </div>
</form>

<script>
    function updateProgressDisplay(value) {
        document.getElementById('progressValueDisplay').textContent = value + '%';

        // Auto-update checkboxes based on progress
        const checkboxes = document.querySelectorAll('input[name="steps[]"]');
        const stepCount = checkboxes.length;
        const stepsToCheck = Math.floor((value / 100) * stepCount);

        checkboxes.forEach((checkbox, index) => {
            checkbox.checked = index < stepsToCheck;
        });
    }

    // Update progress when checkboxes are changed
    document.querySelectorAll('input[name="steps[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('input[name="steps[]"]:checked').length;
            const totalCount = document.querySelectorAll('input[name="steps[]"]').length;
            const progress = Math.round((checkedCount / totalCount) * 100);

            document.getElementById('progressSlider').value = progress;
            updateProgressDisplay(progress);
        });
    });

    document.getElementById('fabrikasiUpdateForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('fabrikasi_update_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Production progress updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating production progress');
            });
    });
</script>

<?php closeDBConnection($conn); ?>