<?php
// modules/material/material_edit_form.php
/**
 * Edit form untuk material
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Material ID");
}

$material_id = (int)$_GET['id'];
$conn = getDBConnection();

// Get material data
$query = "SELECT ml.*, p.pon_number, p.project_name 
          FROM material_lists ml 
          JOIN pon p ON ml.pon_id = p.pon_id 
          WHERE ml.material_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    die("Material tidak ditemukan");
}
?>

<form id="editMaterialForm" class="space-y-4">
    <input type="hidden" name="material_id" value="<?php echo $material_id; ?>">

    <div class="bg-blue-900 bg-opacity-20 border border-blue-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <i class="fas fa-info-circle text-blue-400 mt-1"></i>
            <div>
                <p class="text-blue-300 font-semibold">Editing Material</p>
                <p class="text-blue-200 text-sm">
                    PON: <?php echo htmlspecialchars($material['pon_number']); ?> -
                    <?php echo htmlspecialchars($material['project_name']); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Basic Information -->
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Material Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($material['name']); ?>"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Assembly Marking</label>
            <input type="text" name="assy_marking" value="<?php echo htmlspecialchars($material['assy_marking'] ?? ''); ?>"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Revision</label>
            <input type="text" name="rv" value="<?php echo htmlspecialchars($material['rv'] ?? ''); ?>"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Quantity *</label>
            <input type="number" name="quantity" value="<?php echo $material['quantity']; ?>" min="1"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Dimensions</label>
            <input type="text" name="dimensions" value="<?php echo htmlspecialchars($material['dimensions'] ?? ''); ?>"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Length (mm)</label>
            <input type="number" step="0.01" name="length_mm" value="<?php echo $material['length_mm'] ?? ''; ?>"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Unit Weight (kg) *</label>
            <input type="number" step="0.01" name="weight_kg" value="<?php echo $material['weight_kg']; ?>" min="0"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500" required>
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Remarks</label>
        <textarea name="remarks" rows="3"
            class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500"><?php echo htmlspecialchars($material['remarks'] ?? ''); ?></textarea>
    </div>

    <!-- Engineering Progress -->
    <div class="border-t border-gray-700 pt-4">
        <h4 class="text-white font-semibold mb-3">Engineering Progress</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Status</label>
                <select name="eng_status" class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Progress (%)</label>
                <input type="number" name="eng_progress" min="0" max="100" value="0"
                    class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500">
            </div>
        </div>

        <div class="mt-3">
            <label class="block text-gray-300 text-sm font-medium mb-2">Engineering Notes</label>
            <textarea name="eng_notes" rows="2"
                class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500"
                placeholder="Add notes about engineering progress..."></textarea>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-700">
        <button type="button" onclick="hideEditModal()"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
            Cancel
        </button>
        <button type="submit"
            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
            <i class="fas fa-save"></i>
            <span>Update Material</span>
        </button>
    </div>
</form>

<script>
    document.getElementById('editMaterialForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span> Updating...</span>';
        submitBtn.disabled = true;

        fetch('material_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    hideEditModal();
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Update error: ' + error);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    });

    // Set initial values dari PHP
    document.querySelector('select[name="eng_status"]').value = '<?php echo $material['eng_status'] ?? 'Pending'; ?>';
    document.querySelector('input[name="eng_progress"]').value = '<?php echo $material['eng_progress'] ?? 0; ?>';
</script>