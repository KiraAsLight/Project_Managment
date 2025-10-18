<?php

/**
 * Form untuk update progress procurement (di-load via AJAX)
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
                       LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Purchasing'
                       WHERE ml.material_id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    die("Material tidak ditemukan");
}
?>

<form id="updateProgressForm" class="space-y-4">
    <input type="hidden" name="material_id" value="<?php echo $material_id; ?>">
    <input type="hidden" name="division" value="Purchasing">

    <div class="bg-gray-800 rounded-lg p-4">
        <h4 class="text-white font-semibold mb-2">Material Information</h4>
        <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($material['name']); ?></p>
        <p class="text-gray-400 text-xs">Marking: <?php echo htmlspecialchars($material['assy_marking'] ?? '-'); ?></p>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Procurement Status</label>
        <select name="status" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
            <option value="Pending" <?php echo $material['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="In Progress" <?php echo $material['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
            <option value="Completed" <?php echo $material['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
        </select>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">
            Progress: <span id="progressValueDisplay" class="text-blue-400 font-bold"><?php echo $material['progress_percent']; ?>%</span>
        </label>
        <input type="range" name="progress_percent" id="progressSlider"
            min="0" max="100" value="<?php echo $material['progress_percent']; ?>"
            class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-600"
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
        <label class="block text-gray-300 text-sm font-medium mb-2">Procurement Steps</label>
        <div class="space-y-2 text-sm">
            <label class="flex items-center">
                <input type="checkbox" name="steps[]" value="quoted" class="mr-2 accent-blue-600">
                <span class="text-gray-300">Material Quoted</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="steps[]" value="po_created" class="mr-2 accent-blue-600">
                <span class="text-gray-300">PO Created</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="steps[]" value="supplier_confirmed" class="mr-2 accent-blue-600">
                <span class="text-gray-300">Supplier Confirmed</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="steps[]" value="delivery_scheduled" class="mr-2 accent-blue-600">
                <span class="text-gray-300">Delivery Scheduled</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="steps[]" value="material_received" class="mr-2 accent-blue-600">
                <span class="text-gray-300">Material Received</span>
            </label>
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Supplier Information</label>
        <input type="text" name="supplier_name" placeholder="Supplier Name"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500 mb-2">
        <input type="text" name="po_number" placeholder="PO Number"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500 mb-2">
        <input type="date" name="expected_delivery"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Notes</label>
        <textarea name="notes" rows="3" placeholder="Procurement notes..."
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500"><?php echo htmlspecialchars($material['notes'] ?? ''); ?></textarea>
    </div>

    <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="hideUpdateModal()"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded">
            Cancel
        </button>
        <button type="submit"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
            Update Progress
        </button>
    </div>
</form>

<script>
    function updateProgressDisplay(value) {
        document.getElementById('progressValueDisplay').textContent = value + '%';
    }

    document.getElementById('updateProgressForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('update_progress_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Progress updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating progress');
            });
    });
</script>