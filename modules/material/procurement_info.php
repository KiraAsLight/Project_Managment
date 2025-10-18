<?php

/**
 * Procurement information modal (di-load via AJAX)
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

// Get material and procurement data
$query = "SELECT 
            ml.name,
            ml.quantity,
            ml.total_weight_kg,
            mp.notes,
            mp.started_at,
            mp.completed_at
          FROM material_lists ml
          LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Purchasing'
          WHERE ml.material_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Material tidak ditemukan");
}
?>

<form id="procurementForm" class="space-y-4">
    <input type="hidden" name="material_id" value="<?php echo $material_id; ?>">

    <div class="bg-gray-800 rounded-lg p-4">
        <h4 class="text-white font-semibold mb-2">Material Summary</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-400">Name:</span>
                <span class="text-white ml-2"><?php echo htmlspecialchars($data['name']); ?></span>
            </div>
            <div>
                <span class="text-gray-400">Quantity:</span>
                <span class="text-white ml-2"><?php echo $data['quantity']; ?> pcs</span>
            </div>
            <div>
                <span class="text-gray-400">Total Weight:</span>
                <span class="text-white ml-2"><?php echo number_format($data['total_weight_kg'], 2); ?> kg</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Supplier Name</label>
            <input type="text" name="supplier_name" placeholder="Enter supplier name"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Contact Person</label>
            <input type="text" name="contact_person" placeholder="Contact person"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">PO Number</label>
            <input type="text" name="po_number" placeholder="Purchase order number"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">PO Date</label>
            <input type="date" name="po_date"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Unit Price</label>
            <input type="number" name="unit_price" step="0.01" placeholder="0.00"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Total Price</label>
            <input type="number" name="total_price" step="0.01" placeholder="0.00"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Expected Delivery</label>
            <input type="date" name="expected_delivery"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Actual Delivery</label>
            <input type="date" name="actual_delivery"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-2">Procurement Notes</label>
        <textarea name="procurement_notes" rows="3" placeholder="Additional procurement notes..."
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500"><?php echo htmlspecialchars($data['notes'] ?? ''); ?></textarea>
    </div>

    <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="hideProcurementModal()"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded">
            Cancel
        </button>
        <button type="submit"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
            Save Procurement Info
        </button>
    </div>
</form>

<script>
    document.getElementById('procurementForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('save_procurement_info.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Procurement information saved!');
                    hideProcurementModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error saving procurement information');
            });
    });

    // Auto-calculate total price
    document.querySelector('input[name="unit_price"]').addEventListener('input', function() {
        const unitPrice = parseFloat(this.value) || 0;
        const quantity = <?php echo $data['quantity']; ?>;
        const totalPrice = unitPrice * quantity;
        document.querySelector('input[name="total_price"]').value = totalPrice.toFixed(2);
    });
</script>

<?php closeDBConnection($conn); ?>