<?php

/**
 * QC Check form untuk fabrikasi (di-load via AJAX)
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
$stmt = $conn->prepare("SELECT ml.name, ml.assy_marking, ml.dimensions, ml.quantity 
                       FROM material_lists ml WHERE ml.material_id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    die("Material tidak ditemukan");
}
?>

<form id="qcCheckForm" class="space-y-4">
    <input type="hidden" name="material_id" value="<?php echo $material_id; ?>">

    <div class="bg-gray-800 rounded-lg p-4">
        <h4 class="text-white font-semibold mb-2">QC Inspection</h4>
        <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($material['name']); ?></p>
        <p class="text-gray-400 text-xs">
            Marking: <?php echo htmlspecialchars($material['assy_marking'] ?? '-'); ?> |
            Dim: <?php echo htmlspecialchars($material['dimensions'] ?? '-'); ?>
        </p>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">QC Status</label>
        <select name="qc_status" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-yellow-500">
            <option value="pending">Pending Inspection</option>
            <option value="passed">Passed</option>
            <option value="passed_with_notes">Passed with Notes</option>
            <option value="failed">Failed - Rework Required</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Inspection Checklist</label>
        <div class="space-y-2 text-sm">
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="checks[]" value="dimensions" class="mr-2 accent-yellow-600">
                <span class="text-gray-300">Dimensions Accuracy</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="checks[]" value="welding" class="mr-2 accent-yellow-600">
                <span class="text-gray-300">Welding Quality</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="checks[]" value="surface" class="mr-2 accent-yellow-600">
                <span class="text-gray-300">Surface Finish</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="checks[]" value="assembly" class="mr-2 accent-yellow-600">
                <span class="text-gray-300">Assembly Fit</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="checks[]" value="paint" class="mr-2 accent-yellow-600">
                <span class="text-gray-300">Paint/Coating</span>
            </label>
            <label class="flex items-center bg-gray-800 p-2 rounded">
                <input type="checkbox" name="checks[]" value="safety" class="mr-2 accent-yellow-600">
                <span class="text-gray-300">Safety Standards</span>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Inspector Name</label>
            <input type="text" name="inspector_name" placeholder="Inspector name"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-yellow-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Inspection Date</label>
            <input type="datetime-local" name="inspection_date"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-yellow-500">
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Measurement Results</label>
        <textarea name="measurements" rows="2" placeholder="Key measurement results..."
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-yellow-500"></textarea>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">QC Notes & Findings</label>
        <textarea name="qc_notes" rows="3" placeholder="Inspection findings, issues, or recommendations..."
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-yellow-500"></textarea>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Corrective Actions</label>
        <textarea name="corrective_actions" rows="2" placeholder="Required corrective actions if any..."
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-yellow-500"></textarea>
    </div>

    <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="hideQcModal()"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded">
            Cancel
        </button>
        <button type="submit"
            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded">
            Submit QC Report
        </button>
    </div>
</form>

<script>
    document.getElementById('qcCheckForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('submit_qc_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('QC report submitted successfully!');
                    hideQcModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error submitting QC report');
            });
    });
</script>

<?php closeDBConnection($conn); ?>