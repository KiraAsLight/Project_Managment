<?php

/**
 * Division Tasks - Custom interface untuk semua divisi
 * dengan Material List CRUD untuk Engineering dan Purchase Orders untuk Purchasing
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['pon_id']) || empty($_GET['pon_id'])) {
    die("Invalid PON ID");
}

$pon_id = (int)$_GET['pon_id'];
$division = $_GET['division'] ?? 'Engineering'; // Default ke Engineering

$conn = getDBConnection();

// Get PON data
$stmt = $conn->prepare("SELECT * FROM pon WHERE pon_id = ?");
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$result = $stmt->get_result();
$pon = $result->fetch_assoc();

if (!$pon) {
    die("PON tidak ditemukan");
}

// Division configurations
$division_configs = [
    'Engineering' => [
        'theme_color' => 'blue',
        'icon' => 'fas fa-calculator',
        'title' => 'Material Lists & Drawings Management',
        'tabs' => ['material', 'drawings', 'tasks']
    ],
    'Purchasing' => [
        'theme_color' => 'green',
        'icon' => 'fas fa-shopping-cart',
        'title' => 'Purchase Orders & Supplier Management',
        'tabs' => ['orders', 'suppliers', 'deliveries']
    ],
    'Fabrikasi' => [
        'theme_color' => 'orange',
        'icon' => 'fas fa-hammer',
        'title' => 'Fabrication Progress & Workshop Management',
        'tabs' => ['workshop', 'progress', 'qc_checks']
    ],
    'Logistik' => [
        'theme_color' => 'purple',
        'icon' => 'fas fa-truck',
        'title' => 'Shipping & Delivery Tracking',
        'tabs' => ['shipping', 'tracking', 'deliveries']
    ],
    'QC' => [
        'theme_color' => 'red',
        'icon' => 'fas fa-clipboard-check',
        'title' => 'Quality Control & Inspection Management',
        'tabs' => ['inspections', 'documents', 'compliance']
    ]
];

$config = $division_configs[$division] ?? $division_configs['Engineering'];

// Get tasks untuk divisi yang dipilih
$tasks_query = "SELECT 
                t.*,
                u.full_name as assigned_to_name
              FROM tasks t
              LEFT JOIN users u ON t.assigned_to = u.user_id
              WHERE t.pon_id = ? AND t.responsible_division = ?
              ORDER BY t.start_date ASC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("is", $pon_id, $division);
$stmt->execute();
$tasks_result = $stmt->get_result();
$tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $tasks[] = $row;
}

// Get material items untuk Purchasing
$material_items = [];
if ($division === 'Purchasing') {
    $materials_query = "SELECT material_id, assy_marking, name, quantity, dimensions 
                       FROM material_lists 
                       WHERE pon_id = ? 
                       ORDER BY assy_marking, name";
    $stmt = $conn->prepare($materials_query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $materials_result = $stmt->get_result();

    // Debug: Cek hasil query
    $material_count = $materials_result->num_rows;
    error_log("Purchasing Material Query: Found {$material_count} materials for PON {$pon_id}");

    while ($row = $materials_result->fetch_assoc()) {
        $material_items[] = $row;
    }

    // Debug: Log material data
    error_log("Material Items: " . json_encode($material_items));
}

// Pastikan JSON encode berhasil
$material_items_json = json_encode($material_items ?: []);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON Encode Error: " . json_last_error_msg());
    $material_items_json = '[]';
}

$page_title = $division . " Tasks - " . $pon['pon_number'];
include '../../includes/header.php';
?>

<script>
    // Global variables
    // const materialItems = <?php echo $material_items_json; ?>;
    const currentDivision = '<?php echo $division; ?>';
    const themeColor = '<?php echo $config['theme_color']; ?>';

    // ==============================================
    // COMMON FUNCTIONS
    // ==============================================

    // Tab Management
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.add('hidden');
        });

        // Remove active state from all tabs
        document.querySelectorAll('[id^="tab-"]').forEach(tab => {
            tab.classList.remove('border-' + themeColor + '-500', 'text-white');
            tab.classList.add('border-transparent', 'text-gray-400');
        });

        // Show selected tab content
        const contentElement = document.getElementById('content-' + tabName);
        if (contentElement) {
            contentElement.classList.remove('hidden');
        }

        // Activate selected tab
        const tabElement = document.getElementById('tab-' + tabName);
        if (tabElement) {
            tabElement.classList.add('border-' + themeColor + '-500', 'text-white');
            tabElement.classList.remove('border-transparent', 'text-gray-400');
        }
    }

    // ==============================================
    // ENGINEERING FUNCTIONS
    // ==============================================

    // Material CRUD Functions
    function showAddMaterialModal() {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to manage materials");
            return;
        <?php endif; ?>

        document.getElementById("modalTitle").textContent = "Add New Material";
        document.getElementById("saveButtonText").textContent = "Save Material";
        document.getElementById("materialForm").reset();
        document.getElementById("material_id").value = "";
        document.getElementById("materialModal").classList.remove("hidden");
    }

    function editMaterial(materialId) {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to edit materials");
            return;
        <?php endif; ?>

        // Fetch material data via AJAX
        fetch(`material_ajax.php?action=get&id=${materialId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("modalTitle").textContent = "Edit Material";
                    document.getElementById("saveButtonText").textContent = "Update Material";
                    document.getElementById("material_id").value = data.material.material_id;
                    document.getElementById("assy_marking").value = data.material.assy_marking || "";
                    document.getElementById("rv").value = data.material.rv || "";
                    document.getElementById("name").value = data.material.name;
                    document.getElementById("quantity").value = data.material.quantity;
                    document.getElementById("dimensions").value = data.material.dimensions || "";
                    document.getElementById("length_mm").value = data.material.length_mm || "";
                    document.getElementById("weight_kg").value = data.material.weight_kg || "";
                    document.getElementById("remarks").value = data.material.remarks || "";
                    document.getElementById("materialModal").classList.remove("hidden");
                } else {
                    alert("Error loading material data");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading material data");
            });
    }

    function deleteMaterial(materialId) {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to delete materials");
            return;
        <?php endif; ?>

        if (confirm("Are you sure you want to delete this material?")) {
            fetch(`material_ajax.php?action=delete&id=${materialId}`, {
                    method: "DELETE"
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Error deleting material: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Error deleting material");
                });
        }
    }

    function saveMaterial(event) {
        event.preventDefault();

        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to save materials");
            return;
        <?php endif; ?>

        const formData = new FormData(event.target);

        fetch("material_ajax.php?action=save", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeMaterialModal();
                    location.reload();
                } else {
                    alert("Error saving material: " + data.message);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error saving material");
            });
    }

    function closeMaterialModal() {
        document.getElementById("materialModal").classList.add("hidden");
    }

    // Import Modal Functions
    function showImportModal() {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to import materials");
            return;
        <?php endif; ?>

        document.getElementById("importForm").reset();
        document.getElementById("importModal").classList.remove("hidden");
    }

    function closeImportModal() {
        document.getElementById("importModal").classList.add("hidden");
    }

    function importExcel(event) {
        event.preventDefault();

        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to import materials");
            return;
        <?php endif; ?>

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
        submitBtn.disabled = true;

        fetch("material_ajax.php?action=import", {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    closeImportModal();
                    alert("✅ " + data.message);
                    location.reload();
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                alert("❌ Import failed: " + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function downloadExcelTemplate() {
        const csvContent = `assy_marking,rv,name,quantity,dimensions,length_mm,weight_kg,remarks
A001,A,Plate 10mm,5,1000x2000,6000,25.5,Main structure plate
B001,B,Beam H200x200,3,200x200,5500,45.2,Support beam  
C001,,Angle Bar 50x50x5,10,50x50x5,6000,15.8,Bracing angle
D001,C,Pipe Ø50x3,8,Ø50x3,6000,12.3,Structural pipe
E001,,Channel 100x50x5,6,100x50x5,6000,18.7,Support channel`;

        const blob = new Blob([csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'material_import_template.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        alert('📥 Template CSV downloaded! Buka dengan Excel atau text editor.');
    }

    function downloadCsvTemplate() {
        const csvContent = `assy_marking,rv,name,quantity,dimensions,length_mm,weight_kg,remarks
A001,A,Plate 10mm,5,1000x2000,6000,25.5,Main structure plate
B001,B,Beam H200x200,3,200x200,5500,45.2,Support beam  
C001,,Angle Bar 50x50x5,10,50x50x5,6000,15.8,Bracing angle
D001,C,Pipe Ø50x3,8,Ø50x3,6000,12.3,Structural pipe
E001,,Channel 100x50x5,6,100x50x5,6000,18.7,Support channel`;

        const blob = new Blob([csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'material_import_template.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Drawing Functions
    function showDrawingUpload() {
        alert("🔄 Fitur Upload Drawing dalam pengembangan");
    }

    function updateProgress() {
        alert("📊 Fitur Update Progress dalam pengembangan");
    }

    // Progress Update Functions
    function updateTaskProgress(taskId) {
        // Fetch task data via AJAX
        fetch(`progress_ajax.php?action=get&id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showProgressUpdateModal(data.task);
                } else {
                    alert("Error loading task data");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading task data");
            });
    }

    function showProgressUpdateModal(task) {
        document.getElementById("progress_task_id").value = task.task_id;
        document.getElementById("progress_slider").value = task.progress || 0;
        document.getElementById("progress_status").value = task.status || 'Not Started';
        document.getElementById("progress_notes").value = task.notes || '';

        updateProgressValue(task.progress || 0);
        document.getElementById("progressUpdateModal").classList.remove("hidden");
    }

    function closeProgressUpdateModal() {
        document.getElementById("progressUpdateModal").classList.add("hidden");
    }

    function updateProgressValue(value) {
        document.getElementById("progress_value_display").textContent = value + '%';

        // Update slider color based on value
        const slider = document.getElementById("progress_slider");
        const percentage = (value - slider.min) / (slider.max - slider.min) * 100;
        slider.style.background = `linear-gradient(to right, #3b82f6 ${percentage}%, #374151 ${percentage}%)`;
    }

    function updateTaskProgressSubmit(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        fetch("progress_ajax.php?action=update", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeProgressUpdateModal();
                    alert("✅ " + data.message);
                    refreshTasksList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                alert("❌ Update failed: " + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function refreshTasksList() {
        location.reload();
    }

    // ==============================================
    // PURCHASING FUNCTIONS
    // ==============================================

    // Purchasing Order Functions
    function showAddOrderModal() {
        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to manage purchase orders");
            return;
        <?php endif; ?>

        <?php if (count($material_items) === 0): ?>
            alert("⚠️ No material items found in Engineering. You can still create custom material order.");
        <?php endif; ?>

        document.getElementById("orderModalTitle").textContent = "Create Purchase Order";
        document.getElementById("saveOrderButtonText").textContent = "Save PO";
        document.getElementById("orderForm").reset();
        document.getElementById("order_id").value = "";
        document.getElementById("order_date").value = "<?php echo date('Y-m-d'); ?>";

        // Reset to default selection
        document.getElementById("material_id").value = "";
        document.getElementById("customMaterialFields").classList.add("hidden");
        document.getElementById("materialInfo").classList.add("hidden");

        document.getElementById("orderModal").classList.remove("hidden");
    }

    function closeOrderModal() {
        document.getElementById("orderModal").classList.add("hidden");
    }

    // Material selection handler
    function onMaterialSelected(materialId) {
        const customFields = document.getElementById('customMaterialFields');
        const materialInfo = document.getElementById('materialInfo');

        if (materialId === 'custom') {
            // Show custom material fields
            customFields.classList.remove('hidden');
            materialInfo.classList.add('hidden');

            // Clear material info
            document.getElementById('material_type').required = true;
            document.getElementById('custom_material_name').required = true;
        } else if (materialId && materialId !== '') {
            // Hide custom fields, show material info
            customFields.classList.add('hidden');
            materialInfo.classList.remove('hidden');

            // Get selected option data dari attributes
            const selectedOption = document.querySelector(`#material_id option[value="${materialId}"]`);

            if (selectedOption) {
                const assy = selectedOption.getAttribute('data-assy') || '-';
                const name = selectedOption.getAttribute('data-name') || '-';
                const quantity = selectedOption.getAttribute('data-quantity') || '-';
                const dimensions = selectedOption.getAttribute('data-dimensions') || '-';

                // Update material info display
                document.getElementById('info_assy').textContent = assy;
                document.getElementById('info_name').textContent = name;
                document.getElementById('info_quantity').textContent = quantity;
                document.getElementById('info_dimensions').textContent = dimensions;

                // Auto-fill order quantity dengan required quantity
                document.getElementById('order_quantity').value = quantity !== '-' ? quantity : '';

                // Not required for custom fields
                document.getElementById('material_type').required = false;
                document.getElementById('custom_material_name').required = false;
            }
        } else {
            // Nothing selected
            customFields.classList.add('hidden');
            materialInfo.classList.add('hidden');
            document.getElementById('material_type').required = false;
            document.getElementById('custom_material_name').required = false;
        }
    }

    function viewOrder(orderId) {
        // Show detailed order view modal
        const modalHtml = `
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">Order Details - PO-${orderId.toString().padStart(4, '0')}</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-gray-400 text-sm">Order Date</label>
                            <p class="text-white">Loading...</p>
                        </div>
                        <div>
                            <label class="text-gray-400 text-sm">Status</label>
                            <p class="text-white">Loading...</p>
                        </div>
                    </div>
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-green-400 mb-2"></i>
                        <p class="text-gray-400">Loading order details...</p>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button onclick="closeModal()" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;

        // Create and show modal
        const modal = document.createElement('div');
        modal.innerHTML = modalHtml;
        modal.id = 'orderDetailModal';
        document.body.appendChild(modal);

        // Fetch order details via AJAX
        fetch(`order_ajax.php?action=get&id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateOrderDetailModal(data.order);
                }
            });
    }


    function editOrder(orderId) {
        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to edit purchase orders");
            return;
        <?php endif; ?>

        // Fetch order data via AJAX
        fetch(`order_ajax.php?action=get&id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEditOrderModal(data.order);
                } else {
                    alert("Error loading order data: " + data.message);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading order data");
            });
    }

    function showEditOrderModal(order) {
        document.getElementById("orderModalTitle").textContent = "Edit Purchase Order";
        document.getElementById("saveOrderButtonText").textContent = "Update PO";
        document.getElementById("order_id").value = order.order_id;
        document.getElementById("supplier_name").value = order.supplier_name || '';
        document.getElementById("order_quantity").value = order.quantity || '';
        document.getElementById("order_unit").value = order.unit || '';
        document.getElementById("order_date").value = order.order_date || '';
        document.getElementById("expected_receiving_date").value = order.expected_receiving_date || '';
        document.getElementById("specifications").value = order.specifications || '';
        document.getElementById("notes").value = order.notes || '';

        // Handle material selection - TIDAK PERLU populate ulang dropdown
        if (order.material_id) {
            document.getElementById("material_id").value = order.material_id;
            onMaterialSelected(order.material_id);
        } else {
            document.getElementById("material_id").value = "custom";
            onMaterialSelected("custom");
            document.getElementById("material_type").value = order.material_type || '';
            document.getElementById("custom_material_name").value = order.material_name || 'Custom Material';
        }

        document.getElementById("orderModal").classList.remove("hidden");
    }

    function updateOrderStatus(orderId) {
        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to update order status");
            return;
        <?php endif; ?>

        const newStatus = prompt("Update order status:\n\n- Ordered\n- Partial Received\n- Received\n- Cancelled", "Received");

        if (newStatus && ['Ordered', 'Partial Received', 'Received', 'Cancelled'].includes(newStatus)) {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', newStatus);

            fetch("order_ajax.php?action=update_status", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("✅ " + data.message);
                        refreshOrdersList();
                    } else {
                        alert("❌ " + data.message);
                    }
                })
                .catch(error => {
                    console.error('Status update error:', error);
                    alert("❌ Status update failed");
                });
        }
    }

    function updateOrderDetailModal(order) {
        const modal = document.getElementById('orderDetailModal');
        if (!modal) return;

        const statusColors = {
            'Ordered': 'text-yellow-400',
            'Partial Received': 'text-blue-400',
            'Received': 'text-green-400',
            'Cancelled': 'text-red-400'
        };

        modal.querySelector('.space-y-4').innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-gray-400 text-sm">Order Date</label>
                <p class="text-white">${order.order_date || 'Not set'}</p>
            </div>
            <div>
                <label class="text-gray-400 text-sm">Status</label>
                <p class="${statusColors[order.status] || 'text-white'} font-semibold">${order.status}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-gray-400 text-sm">Supplier</label>
                <p class="text-white">${order.supplier_name}</p>
            </div>
            <div>
                <label class="text-gray-400 text-sm">Material</label>
                <p class="text-white">${order.material_type}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-gray-400 text-sm">Quantity</label>
                <p class="text-white">${order.quantity || '0'} ${order.unit || ''}</p>
            </div>
            <div>
                <label class="text-gray-400 text-sm">Expected Delivery</label>
                <p class="text-white">${order.expected_receiving_date || 'Not set'}</p>
            </div>
        </div>
        ${order.specifications ? `
        <div>
            <label class="text-gray-400 text-sm">Specifications</label>
            <p class="text-white">${order.specifications}</p>
        </div>
        ` : ''}
        ${order.notes ? `
        <div>
            <label class="text-gray-400 text-sm">Notes</label>
            <p class="text-white">${order.notes}</p>
        </div>
        ` : ''}
    `;
    }

    function deleteOrder(orderId) {
        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to delete orders");
            return;
        <?php endif; ?>

        if (!confirm("Are you sure you want to delete this purchase order?")) return;

        fetch(`order_ajax.php?action=delete&id=${orderId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("✅ " + data.message);
                    refreshOrdersList();
                } else {
                    alert("❌ " + data.message);
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert("❌ Delete failed");
            });
    }

    function saveOrder(event) {
        event.preventDefault();

        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to save purchase orders");
            return;
        <?php endif; ?>

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        fetch("order_ajax.php?action=save", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeOrderModal();
                    alert("✅ " + data.message);
                    refreshOrdersList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                alert("❌ Save failed: " + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    // Refresh orders list
    function refreshOrdersList() {
        location.reload();
    }

    // Stub functions for other purchasing actions
    function showDeliveryTracking() {
        alert("📦 Delivery tracking feature in development");
    }

    function showSupplierManagement() {
        alert("🏢 Supplier management feature in development");
    }

    function showOrderReports() {
        alert("📊 Generating purchase order reports...\n\n" +
            "• Total Orders: " + document.querySelectorAll('#content-orders tbody tr').length + "\n" +
            "• Pending: " + document.querySelectorAll('.bg-yellow-500').length + "\n" +
            "• Completed: " + document.querySelectorAll('.bg-green-500').length + "\n" +
            "• Reports feature in development");

        // Bisa redirect ke reporting page atau show modal reports
        // window.open('purchasing_reports.php?pon_id=<?php echo $pon_id; ?>', '_blank');
    }

    // Supplier Management Functions
    function showAddSupplierModal() {
        alert("🏢 Add Supplier Modal\n\n" +
            "• Supplier Name & Contact\n" +
            "• Address & Contact Info\n" +
            "• Material Specialization\n" +
            "• Performance Rating\n" +
            "Feature in development");
    }

    function refreshDeliveries() {
        alert("🔄 Refreshing delivery data...");
        // AJAX call to refresh deliveries data
        // fetch('delivery_ajax.php?action=refresh&pon_id=<?php echo $pon_id; ?>')
        // .then(response => response.json())
        // .then(data => {
        //     updateDeliveriesUI(data);
        // });

        // Simple reload for now
        location.reload();
    }

    function closeModal() {
        const modal = document.getElementById('orderDetailModal');
        if (modal) {
            modal.remove();
        }
    }

    // ==============================================
    // FABRIKASI FUNCTIONS - FOKUS MATERIAL PROGRESS
    // ==============================================

    function updateMaterialFabrication(materialId) {
        fetch(`fabrication_ajax.php?action=get_fabrication_materials&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const material = data.materials.find(m => m.material_id == materialId);
                    if (material) {
                        showMaterialFabricationModal(material);
                    }
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading material data");
            });
    }

    function showMaterialFabricationModal(material) {
        document.getElementById("fabrication_material_id").value = material.material_id;
        document.getElementById("fabrication_material_name").textContent = material.material_name;
        document.getElementById("fabrication_assy_marking").textContent = material.assy_marking || 'N/A';
        document.getElementById("fabrication_progress").value = material.fabrication_progress || 0;
        document.getElementById("fabrication_status").value = material.fabrication_status || 'Pending';
        document.getElementById("fabrication_phase").value = getFabricationPhaseByProgress(material.fabrication_progress);
        document.getElementById("qc_status").value = 'Pending';
        document.getElementById("fabrication_notes").value = '';

        updateFabricationProgressValue(material.fabrication_progress || 0);
        document.getElementById("materialFabricationModal").classList.remove("hidden");
    }

    function getFabricationPhaseByProgress(progress) {
        if (progress >= 80) return 'Final Assembly & Finishing';
        if (progress >= 60) return 'Welding & Joining';
        if (progress >= 40) return 'Component Assembly';
        if (progress >= 20) return 'Cutting & Preparation';
        return 'Material Preparation';
    }

    function updateFabricationProgressValue(value) {
        document.getElementById("fabrication_progress_display").textContent = value + '%';

        const slider = document.getElementById("fabrication_progress");
        const percentage = (value - slider.min) / (slider.max - slider.min) * 100;
        slider.style.background = `linear-gradient(to right, #f97316 ${percentage}%, #374151 ${percentage}%)`;
    }

    function updateMaterialFabricationSubmit(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        fetch("fabrication_ajax.php?action=update_material_progress", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeMaterialFabricationModal();
                    alert("✅ " + data.message);
                    refreshFabricationData();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                alert("❌ Update failed: " + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function closeMaterialFabricationModal() {
        document.getElementById("materialFabricationModal").classList.add("hidden");
    }

    function showMaterialFabricationModal() {
        fetch(`fabrication_ajax.php?action=get_fabrication_materials&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pendingMaterials = data.materials.filter(m => m.fabrication_status === 'Pending');

                    if (pendingMaterials.length === 0) {
                        alert('✅ All materials have fabrication process started');
                        return;
                    }

                    let materialList = 'Select material to start fabrication:\n\n';
                    pendingMaterials.forEach((material, index) => {
                        materialList += `${index + 1}. ${material.material_name} (${material.assy_marking || 'N/A'})\n`;
                    });

                    const choice = prompt(materialList + '\nEnter material number:');
                    if (choice && choice >= 1 && choice <= pendingMaterials.length) {
                        const selectedMaterial = pendingMaterials[choice - 1];
                        startMaterialFabrication(selectedMaterial.material_id);
                    }
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading materials");
            });
    }

    function startMaterialFabrication(materialId) {
        const fabricationPhase = prompt('Enter initial fabrication phase:', 'Material Preparation');
        if (fabricationPhase) {
            const notes = prompt('Enter initial notes:', 'Starting fabrication process');

            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('fabrication_phase', fabricationPhase);
            formData.append('notes', notes);

            fetch('fabrication_ajax.php?action=start_fabrication', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('🚀 ' + data.message);
                        refreshFabricationData();
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Start Fabrication Error:', error);
                    alert('❌ Failed to start fabrication');
                });
        }
    }

    function showMaterialProgressUpdate() {
        alert('📊 Select a material from the list to update its fabrication progress');
        switchTab('workshop');
    }

    function showCompletionModal() {
        fetch(`fabrication_ajax.php?action=get_fabrication_materials&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const inProgressMaterials = data.materials.filter(m =>
                        m.fabrication_status === 'In Progress' && m.fabrication_progress >= 80
                    );

                    if (inProgressMaterials.length === 0) {
                        alert('ℹ️ No materials ready for completion (need ≥80% progress)');
                        return;
                    }

                    let materialList = 'Select material to complete:\n\n';
                    inProgressMaterials.forEach((material, index) => {
                        materialList += `${index + 1}. ${material.material_name} (Progress: ${material.fabrication_progress}%)\n`;
                    });

                    const choice = prompt(materialList + '\nEnter material number:');
                    if (choice && choice >= 1 && choice <= inProgressMaterials.length) {
                        const selectedMaterial = inProgressMaterials[choice - 1];
                        completeMaterialFabrication(selectedMaterial.material_id);
                    }
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Error loading materials");
            });
    }

    function completeMaterialFabrication(materialId) {
        const qcStatus = prompt('Enter QC Status (Passed/Failed/Rework):', 'Passed');
        if (qcStatus) {
            const notes = prompt('Enter completion notes:', 'Fabrication process completed successfully');

            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('qc_status', qcStatus);
            formData.append('notes', notes);

            fetch('fabrication_ajax.php?action=complete_fabrication', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('🎉 ' + data.message);
                        refreshFabricationData();
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Completion Error:', error);
                    alert('❌ Failed to complete fabrication');
                });
        }
    }

    function performQCCheck(materialId) {
        if (confirm('Mark this material as QC Passed?')) {
            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('qc_status', 'Passed');
            formData.append('notes', 'QC Check passed - ready for delivery');

            fetch('fabrication_ajax.php?action=complete_fabrication', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        refreshFabricationData();
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('QC Error:', error);
                    alert('❌ QC check failed');
                });
        }
    }

    function showQCIssue(materialId) {
        const issueNotes = prompt('Enter QC issue details:');
        if (issueNotes) {
            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('qc_status', 'Failed');
            formData.append('notes', 'QC Failed: ' + issueNotes);

            fetch('fabrication_ajax.php?action=update_material_progress', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('⚠️ ' + data.message);
                        refreshFabricationData();
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('QC Issue Error:', error);
                    alert('❌ Failed to record QC issue');
                });
        }
    }

    function refreshFabricationData() {
        location.reload();
    }

    // ==============================================
    // PRODUCTION TRACKING FUNCTIONS
    // ==============================================

    function refreshProductionData() {
        fetch(`fabrication_ajax.php?action=get_production_tracking&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProductionDashboard(data.production_data);
                }
            })
            .catch(error => {
                console.error("Production data error:", error);
            });
    }

    function updateProductionDashboard(productionData) {
        // Update statistics
        document.getElementById('activeMaterials').textContent = productionData.length;

        const avgProgress = productionData.length > 0 ?
            Math.round(productionData.reduce((sum, item) => sum + item.progress_percent, 0) / productionData.length) :
            0;
        document.getElementById('avgProgress').textContent = avgProgress + '%';

        // Update production feed
        const feedContainer = document.getElementById('productionFeed');
        if (productionData.length === 0) {
            feedContainer.innerHTML = '<div class="text-center py-8 text-gray-500">No active production</div>';
        } else {
            feedContainer.innerHTML = productionData.map(item => `
            <div class="flex items-center justify-between p-3 bg-gray-750 rounded-lg">
                <div class="flex items-center space-x-3">
                    <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                    <div>
                        <div class="text-white font-semibold text-sm">${item.material_name}</div>
                        <div class="text-gray-400 text-xs">${item.fabrication_phase} • ${item.workstation}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-white font-bold">${item.progress_percent}%</div>
                    <div class="text-gray-400 text-xs">${item.operator_name}</div>
                </div>
            </div>
        `).join('');
        }
    }

    function showWorkshopActivityModal() {
        // Implementation for workshop activity modal
        alert('🏭 Workshop Activity Log\n\nFitur pencatatan aktivitas workshop');
    }

    // ==============================================
    // ADVANCED QC FUNCTIONS
    // ==============================================

    function generateQCReport() {
        alert('📊 Generating QC Report...');
        // Implementation for QC report generation
    }

    function showBulkQCCheck() {
        alert('✅ Bulk QC Check\n\nFitur QC check multiple materials sekaligus');
    }

    function showQCIssueTracker() {
        alert('⚠️ QC Issue Tracker\n\nMelacak dan memonitor issue kualitas');
    }

    // ==============================================
    // FABRICATION REPORTS FUNCTIONS
    // ==============================================

    function generateFabricationReport() {
        fetch(`fabrication_ajax.php?action=generate_fabrication_report&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateReportDashboard(data.report, data.material_details);
                }
            })
            .catch(error => {
                console.error("Report generation error:", error);
                alert('❌ Failed to generate report');
            });
    }

    function updateReportDashboard(report, materialDetails) {
        // Update summary cards
        document.getElementById('reportTotalMaterials').textContent = report.total_materials || 0;
        document.getElementById('reportCompletionRate').textContent =
            report.total_materials ? Math.round((report.completed_count / report.total_materials) * 100) + '%' : '0%';
        document.getElementById('reportTotalWeight').textContent = report.total_weight ? Math.round(report.total_weight) : 0;
        document.getElementById('reportAvgDuration').textContent = report.avg_duration || '-';

        // Update report table
        const tableBody = document.getElementById('reportTableBody');
        if (materialDetails.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No fabrication data available</td></tr>';
        } else {
            tableBody.innerHTML = materialDetails.map(item => `
            <tr class="hover:bg-gray-750">
                <td class="px-4 py-3">
                    <div class="text-white font-semibold">${item.material_name}</div>
                    <div class="text-gray-400 text-xs">${item.assy_marking || 'N/A'}</div>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${getStatusColor(item.status)}">${item.status}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex flex-col items-center">
                        <span class="text-white font-bold">${item.progress_percent}%</span>
                        <div class="w-16 bg-gray-700 rounded-full h-2 mt-1">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: ${item.progress_percent}%"></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-center text-gray-300">
                    ${item.days_in_production || '-'} days
                </td>
                <td class="px-4 py-3 text-center text-gray-300">
                    ${item.total_weight_kg ? Math.round(item.total_weight_kg) + ' kg' : '-'}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${item.status === 'Completed' ? 'bg-green-500' : 'bg-gray-500'} text-white">
                        ${item.status === 'Completed' ? 'Ready' : 'Pending'}
                    </span>
                </td>
            </tr>
        `).join('');
        }
    }

    function getStatusColor(status) {
        const colors = {
            'Completed': 'bg-green-500',
            'In Progress': 'bg-orange-500',
            'Pending': 'bg-gray-500',
            'Rejected': 'bg-red-500'
        };
        return colors[status] || 'bg-gray-500';
    }

    // Auto-refresh production data every 30 seconds
    setInterval(() => {
        if (currentDivision === 'Fabrikasi') {
            refreshProductionData();
        }
    }, 30000);

    // ==============================================
    // INITIALIZATION
    // ==============================================

    // Initialize first tab
    document.addEventListener('DOMContentLoaded', function() {
        switchTab('<?php echo $config['tabs'][0]; ?>');
    });
</script>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Dynamic Header berdasarkan divisi -->
        <div class="border-l-4 border-<?php echo $config['theme_color']; ?>-500 bg-<?php echo $config['theme_color']; ?>-900 bg-opacity-20 p-6 rounded-xl mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <i class="<?php echo $config['icon']; ?> text-<?php echo $config['theme_color']; ?>-400 text-4xl"></i>
                    <div>
                        <h1 class="text-2xl font-bold text-white">
                            <?php echo $division; ?> Division - <?php echo $pon['pon_number']; ?>
                        </h1>
                        <p class="text-<?php echo $config['theme_color']; ?>-300">
                            <?php echo $config['title']; ?>
                        </p>
                    </div>
                </div>
                <a href="pon_tasks.php?pon_id=<?php echo $pon_id; ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Overview</span>
                </a>
            </div>
        </div>

        <!-- Quick Actions - Custom per divisi -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <?php echo getDivisionQuickActions($division, $config['theme_color']); ?>
        </div>

        <!-- Tabs - Custom per divisi -->
        <div class="mb-6">
            <div class="border-b border-gray-700">
                <nav class="flex space-x-8">
                    <?php echo getDivisionTabs($division, $config, $tasks); ?>
                </nav>
            </div>
        </div>

        <!-- Tab Contents -->
        <?php
        if ($division === 'Engineering') {
            echo getEngineeringTabContent($pon_id, $tasks, $config);
        } elseif ($division === 'Purchasing') {
            echo getPurchasingTabContent($pon_id, $tasks, $config, $material_items);
        } elseif ($division === 'Fabrikasi') {
            echo getFabricationTabContent($pon_id, $tasks, $config);
        } else {
            echo getDivisionTabContent($division, $pon_id, $tasks, $config);
        }
        ?>

    </main>
</div>

<!-- Add/Edit Material Modal -->
<div id="materialModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white" id="modalTitle">Add New Material</h3>
            <button onclick="closeMaterialModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="materialForm" onsubmit="saveMaterial(event)">
            <input type="hidden" id="material_id" name="material_id" value="">
            <input type="hidden" name="pon_id" value="<?php echo $pon_id; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-gray-300 font-medium mb-2">Assy Marking</label>
                    <input type="text" id="assy_marking" name="assy_marking" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="EX: A001">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Revision</label>
                    <input type="text" id="rv" name="rv" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="A, B, C, etc.">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Material Name *</label>
                    <input type="text" id="name" name="name" required class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="Plate 10mm / Beam H200x200">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" required step="1" min="1" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="10">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Dimensions</label>
                    <input type="text" id="dimensions" name="dimensions" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="200x200 / Ø50">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Length (mm)</label>
                    <input type="number" id="length_mm" name="length_mm" step="0.1" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="6000">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Weight per Unit (kg)</label>
                    <input type="number" id="weight_kg" name="weight_kg" step="0.001" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="25.5">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500" placeholder="Additional notes..."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeMaterialModal()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span id="saveButtonText">Save Material</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Import Excel Modal -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-2xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Import Material Data from Excel</h3>
            <button onclick="closeImportModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="importForm" onsubmit="importExcel(event)" enctype="multipart/form-data">
            <input type="hidden" name="pon_id" value="<?php echo $pon_id; ?>">

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Excel File *</label>
                <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                <p class="text-gray-400 text-sm mt-1">Supported formats: .xlsx, .xls, .csv</p>
            </div>

            <!-- Template Section -->
            <div class="mb-4 p-4 bg-blue-900 bg-opacity-20 rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-blue-300 text-sm font-semibold">📋 Excel Template Format</p>
                    <div class="flex space-x-2">
                        <button type="button" onclick="downloadExcelTemplate()" class="text-blue-400 hover:text-blue-300 text-xs flex items-center space-x-1">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel Template</span>
                        </button>
                        <button type="button" onclick="downloadCsvTemplate()" class="text-green-400 hover:text-green-300 text-xs flex items-center space-x-1">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV Template</span>
                        </button>
                    </div>
                </div>

                <div class="text-xs text-gray-300 bg-black p-3 rounded overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="p-2 border border-gray-600">A: assy_marking</th>
                                <th class="p-2 border border-gray-600">B: rv</th>
                                <th class="p-2 border border-gray-600">C: name</th>
                                <th class="p-2 border border-gray-600">D: quantity</th>
                                <th class="p-2 border border-gray-600">E: dimensions</th>
                                <th class="p-2 border border-gray-600">F: length_mm</th>
                                <th class="p-2 border border-gray-600">G: weight_kg</th>
                                <th class="p-2 border border-gray-600">H: remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="p-2 border border-gray-700">A001</td>
                                <td class="p-2 border border-gray-700">A</td>
                                <td class="p-2 border border-gray-700">Plate 10mm</td>
                                <td class="p-2 border border-gray-700">5</td>
                                <td class="p-2 border border-gray-700">1000x2000</td>
                                <td class="p-2 border border-gray-700">6000</td>
                                <td class="p-2 border border-gray-700">25.5</td>
                                <td class="p-2 border border-gray-700">Main structure plate</td>
                            </tr>
                            <tr>
                                <td class="p-2 border border-gray-700">B001</td>
                                <td class="p-2 border border-gray-700">B</td>
                                <td class="p-2 border border-gray-700">Beam H200x200</td>
                                <td class="p-2 border border-gray-700">3</td>
                                <td class="p-2 border border-gray-700">200x200</td>
                                <td class="p-2 border border-gray-700">5500</td>
                                <td class="p-2 border border-gray-700">45.2</td>
                                <td class="p-2 border border-gray-700">Support beam</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-xs text-gray-400">
                    <p><strong>💡 Format Excel (.xlsx, .xls):</strong></p>
                    <ul class="list-disc list-inside space-y-1 mt-1">
                        <li><strong>Kolom A:</strong> assy_marking - Kode perakitan</li>
                        <li><strong>Kolom B:</strong> rv - Revisi</li>
                        <li><strong>Kolom C:</strong> name - Nama material (<span class="text-red-400">Wajib</span>)</li>
                        <li><strong>Kolom D:</strong> quantity - Jumlah (<span class="text-red-400">Wajib</span>)</li>
                        <li><strong>Kolom E:</strong> dimensions - Dimensi material</li>
                        <li><strong>Kolom F:</strong> length_mm - Panjang (mm)</li>
                        <li><strong>Kolom G:</strong> weight_kg - Berat per unit (kg)</li>
                        <li><strong>Kolom H:</strong> remarks - Keterangan</li>
                    </ul>
                    <p class="mt-2 text-yellow-400">✅ <strong>total_weight_kg</strong> akan dihitung otomatis</p>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeImportModal()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-file-import"></i>
                    <span>Import from Excel</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Progress Update Modal -->
<div id="progressUpdateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Update Task Progress</h3>
            <button onclick="closeProgressUpdateModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="progressUpdateForm" onsubmit="updateTaskProgressSubmit(event)">
            <input type="hidden" id="progress_task_id" name="task_id" value="">

            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-3">Progress (%)</label>

                <!-- Progress Slider -->
                <div class="mb-4">
                    <input type="range" id="progress_slider" name="progress" min="0" max="100" step="1" value="0"
                        class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer slider"
                        oninput="updateProgressValue(this.value)">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>0%</span>
                        <span>25%</span>
                        <span>50%</span>
                        <span>75%</span>
                        <span>100%</span>
                    </div>
                </div>

                <!-- Progress Value Display -->
                <div class="text-center">
                    <span id="progress_value_display" class="text-3xl font-bold text-blue-400">0%</span>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">Status</label>
                <select id="progress_status" name="status"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                    <option value="Not Started">Not Started</option>
                    <option value="In Progress">In Progress</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Completed">Completed</option>
                    <option value="Delayed">Delayed</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">Notes</label>
                <textarea id="progress_notes" name="notes" rows="3"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    placeholder="Progress update notes..."></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeProgressUpdateModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Update Progress</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Order Modal -->
<div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white" id="orderModalTitle">Create Purchase Order</h3>
            <button onclick="closeOrderModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="orderForm" onsubmit="saveOrder(event)">
            <input type="hidden" id="order_id" name="order_id" value="">
            <input type="hidden" name="pon_id" value="<?php echo $pon_id; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Material Selection -->
                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Select Material Item *</label>
                    <select id="material_id" name="material_id"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        onchange="onMaterialSelected(this.value)">
                        <option value="">-- Select from Material List --</option>

                        <!-- PHP LOOP LANGSUNG -->
                        <?php foreach ($material_items as $material): ?>
                            <option value="<?php echo $material['material_id']; ?>"
                                data-quantity="<?php echo $material['quantity']; ?>"
                                data-name="<?php echo htmlspecialchars($material['name']); ?>"
                                data-assy="<?php echo htmlspecialchars($material['assy_marking'] ?? ''); ?>"
                                data-dimensions="<?php echo htmlspecialchars($material['dimensions'] ?? ''); ?>">
                                <?php
                                $displayText = ($material['assy_marking'] ?: 'N/A') . ' - ' . $material['name'];
                                if ($material['quantity']) {
                                    $displayText .= ' (Qty: ' . $material['quantity'] . ')';
                                }
                                echo htmlspecialchars($displayText);
                                ?>
                            </option>
                        <?php endforeach; ?>

                        <option value="custom">-- Custom Material (Not in List) --</option>
                    </select>
                    <p class="text-gray-400 text-sm mt-1" id="materialCountText">
                        <?php echo count($material_items); ?> material items available
                    </p>
                </div>

                <!-- Custom Material Fields (hidden by default) -->
                <div id="customMaterialFields" class="md:col-span-2 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-750 rounded-lg">
                        <div>
                            <label class="block text-gray-300 font-medium mb-2">Material Type *</label>
                            <select id="material_type" name="material_type"
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500">
                                <option value="">Select Material Type</option>
                                <option value="Steel Material">Steel Material</option>
                                <option value="Bolt/Nut/Washers">Bolt/Nut/Washers</option>
                                <option value="Anchorage">Anchorage</option>
                                <option value="Bearing Pads">Bearing Pads</option>
                                <option value="Steel Deck Plate/Bondeck">Steel Deck Plate/Bondeck</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-300 font-medium mb-2">Custom Material Name</label>
                            <input type="text" id="custom_material_name" name="custom_material_name"
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                                placeholder="Enter material name">
                        </div>
                    </div>
                </div>

                <!-- Material Info Display -->
                <div id="materialInfo" class="md:col-span-2 hidden">
                    <div class="p-4 bg-green-900 bg-opacity-20 rounded-lg border border-green-700">
                        <h4 class="text-green-300 font-semibold mb-2">Selected Material Info</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-400">Assy Marking:</span>
                                <span id="info_assy" class="text-white ml-2">-</span>
                            </div>
                            <div>
                                <span class="text-gray-400">Material Name:</span>
                                <span id="info_name" class="text-white ml-2">-</span>
                            </div>
                            <div>
                                <span class="text-gray-400">Required Qty:</span>
                                <span id="info_quantity" class="text-white ml-2">-</span>
                            </div>
                            <div>
                                <span class="text-gray-400">Dimensions:</span>
                                <span id="info_dimensions" class="text-white ml-2">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Supplier Name *</label>
                    <input type="text" id="supplier_name" name="supplier_name" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Supplier company name">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Order Quantity *</label>
                    <input type="number" id="order_quantity" name="order_quantity" step="0.01" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="0.00">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Unit *</label>
                    <input type="text" id="order_unit" name="order_unit" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="kg, pcs, m, etc." value="pcs">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Order Date</label>
                    <input type="date" id="order_date" name="order_date"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Expected Delivery</label>
                    <input type="date" id="expected_receiving_date" name="expected_receiving_date"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Specifications</label>
                    <textarea id="specifications" name="specifications" rows="3"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Material specifications, grade, standards..."></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Notes</label>
                    <textarea id="notes" name="notes" rows="2"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Additional notes..."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeOrderModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span id="saveOrderButtonText">Save PO</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Material Fabrication Progress Modal -->
<div id="materialFabricationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Update Material Fabrication</h3>
            <button onclick="closeMaterialFabricationModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="materialFabricationForm" onsubmit="updateMaterialFabricationSubmit(event)">
            <input type="hidden" id="fabrication_material_id" name="material_id" value="">

            <div class="mb-4 p-4 bg-orange-900 bg-opacity-20 rounded-lg border border-orange-700">
                <h4 class="text-orange-300 font-semibold mb-2">Material Info</h4>
                <p class="text-white font-semibold" id="fabrication_material_name">-</p>
                <p class="text-gray-300 text-sm">Assy: <span id="fabrication_assy_marking">-</span></p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Fabrication Phase</label>
                <select id="fabrication_phase" name="fabrication_phase" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                    <option value="Material Preparation">Material Preparation</option>
                    <option value="Cutting & Preparation">Cutting & Preparation</option>
                    <option value="Component Assembly">Component Assembly</option>
                    <option value="Welding & Joining">Welding & Joining</option>
                    <option value="Final Assembly & Finishing">Final Assembly & Finishing</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Progress (%)</label>
                <input type="range" id="fabrication_progress" name="progress_percent" min="0" max="100" step="1" value="0"
                    class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer slider"
                    oninput="updateFabricationProgressValue(this.value)">
                <div class="text-center mt-2">
                    <span id="fabrication_progress_display" class="text-2xl font-bold text-orange-400">0%</span>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Status</label>
                <select id="fabrication_status" name="status" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">QC Status</label>
                <select id="qc_status" name="qc_status" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Passed">Passed</option>
                    <option value="Failed">Failed</option>
                    <option value="Rework">Rework</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Notes</label>
                <textarea id="fabrication_notes" name="notes" rows="3" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500" placeholder="Fabrication progress notes..."></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeMaterialFabricationModal()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Update Progress</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Custom Slider Styling */
    .slider::-webkit-slider-thumb {
        appearance: none;
        height: 20px;
        width: 20px;
        border-radius: 50%;
        background: #3b82f6;
        cursor: pointer;
        border: 2px solid #1e40af;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .slider::-moz-range-thumb {
        height: 20px;
        width: 20px;
        border-radius: 50%;
        background: #3b82f6;
        cursor: pointer;
        border: 2px solid #1e40af;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .slider::-webkit-slider-track {
        width: 100%;
        height: 8px;
        cursor: pointer;
        background: #374151;
        border-radius: 4px;
    }

    .slider::-moz-range-track {
        width: 100%;
        height: 8px;
        cursor: pointer;
        background: #374151;
        border-radius: 4px;
    }
</style>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';

// Helper functions
function getDivisionQuickActions($division, $theme_color)
{
    $actions = [
        'Engineering' => [
            ['icon' => 'fa-upload', 'title' => 'Upload Drawing', 'subtitle' => 'PDF, DWG, DXF', 'onclick' => 'showDrawingUpload()'],
            ['icon' => 'fa-list-alt', 'title' => 'Add Material', 'subtitle' => 'New material item', 'onclick' => 'showAddMaterialModal()'],
            ['icon' => 'fa-file-import', 'title' => 'Import Excel', 'subtitle' => 'Bulk import', 'onclick' => 'showImportModal()'],
            ['icon' => 'fa-sync-alt', 'title' => 'Update Progress', 'subtitle' => 'Task completion', 'onclick' => 'updateProgress()']
        ],
        'Purchasing' => [
            ['icon' => 'fa-file-invoice', 'title' => 'Create PO', 'subtitle' => 'New purchase order', 'onclick' => 'showAddOrderModal()'],
            ['icon' => 'fa-truck-loading', 'title' => 'Track Delivery', 'subtitle' => 'Update delivery status', 'onclick' => 'showDeliveryTracking()'],
            ['icon' => 'fa-address-book', 'title' => 'Supplier Info', 'subtitle' => 'Manage suppliers', 'onclick' => 'showSupplierManagement()'],
            ['icon' => 'fa-chart-line', 'title' => 'Order Reports', 'subtitle' => 'View purchase reports', 'onclick' => 'showOrderReports()']
        ],
        'Fabrikasi' => [
            ['icon' => 'fa-play-circle', 'title' => 'Start Fabrication', 'subtitle' => 'Begin material processing', 'onclick' => 'showMaterialFabricationModal()'],
            ['icon' => 'fa-chart-line', 'title' => 'Production Tracking', 'subtitle' => 'Real-time monitoring', 'onclick' => 'switchTab(\'progress\')'],
            ['icon' => 'fa-clipboard-check', 'title' => 'QC Dashboard', 'subtitle' => 'Quality inspection', 'onclick' => 'switchTab(\'qc_checks\')'],
            ['icon' => 'fa-chart-bar', 'title' => 'Generate Report', 'subtitle' => 'Fabrication analytics', 'onclick' => 'switchTab(\'reports\')']
        ],
        // ... other divisions
    ];

    $div_actions = $actions[$division] ?? $actions['Engineering'];
    $html = '';

    foreach ($div_actions as $action) {
        $onclick = $action['onclick'] ?? '';
        $html .= '
        <button onclick="' . $onclick . '" class="bg-' . $theme_color . '-600 hover:bg-' . $theme_color . '-700 text-white p-4 rounded-lg flex items-center space-x-3 transition">
            <i class="fas ' . $action['icon'] . ' text-xl"></i>
            <div class="text-left">
                <div class="font-semibold">' . $action['title'] . '</div>
                <div class="text-' . $theme_color . '-200 text-sm">' . $action['subtitle'] . '</div>
            </div>
        </button>';
    }

    return $html;
}

function getDivisionTabs($division, $config, $tasks)
{
    $tab_configs = [
        'Engineering' => [
            ['id' => 'material', 'icon' => 'fa-list-ol', 'label' => 'Material List', 'count' => ''],
            ['id' => 'drawings', 'icon' => 'fa-drafting-compass', 'label' => 'Engineering Drawings', 'count' => ''],
            ['id' => 'tasks', 'icon' => 'fa-tasks', 'label' => 'Engineering Tasks', 'count' => count($tasks)]
        ],
        'Purchasing' => [
            ['id' => 'orders', 'icon' => 'fa-file-invoice', 'label' => 'Purchase Orders', 'count' => ''],
            ['id' => 'suppliers', 'icon' => 'fa-address-book', 'label' => 'Suppliers', 'count' => ''],
            ['id' => 'deliveries', 'icon' => 'fa-truck-loading', 'label' => 'Deliveries', 'count' => '']
        ],
        'Fabrikasi' => [
            ['id' => 'workshop', 'icon' => 'fa-tools', 'label' => 'Workshop Progress', 'count' => ''],
            ['id' => 'progress', 'icon' => 'fa-chart-line', 'label' => 'Production Tracking', 'count' => ''],
            ['id' => 'qc_checks', 'icon' => 'fa-clipboard-check', 'label' => 'QC Checks', 'count' => ''],
            ['id' => 'reports', 'icon' => 'fa-chart-bar', 'label' => 'Reports', 'count' => '']
        ],
        'Logistik' => [
            ['id' => 'shipping', 'icon' => 'fa-shipping-fast', 'label' => 'Shipping Status', 'count' => ''],
            ['id' => 'tracking', 'icon' => 'fa-map-marker-alt', 'label' => 'Live Tracking', 'count' => ''],
            ['id' => 'deliveries', 'icon' => 'fa-clipboard-list', 'label' => 'Delivery Notes', 'count' => '']
        ],
        'QC' => [
            ['id' => 'inspections', 'icon' => 'fa-search', 'label' => 'Inspections', 'count' => ''],
            ['id' => 'documents', 'icon' => 'fa-file-medical', 'label' => 'QC Documents', 'count' => ''],
            ['id' => 'compliance', 'icon' => 'fa-check-double', 'label' => 'Compliance', 'count' => '']
        ]
    ];

    $tabs = $tab_configs[$division] ?? $tab_configs['Engineering'];
    $html = '';

    foreach ($tabs as $tab) {
        $count_html = $tab['count'] ? ' (' . $tab['count'] . ')' : '';
        $html .= '
        <button 
            id="tab-' . $tab['id'] . '" 
            class="py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-white flex items-center space-x-2"
            onclick="switchTab(\'' . $tab['id'] . '\')">
            <i class="fas ' . $tab['icon'] . '"></i>
            <span>' . $tab['label'] . $count_html . '</span>
        </button>';
    }

    return $html;
}

function getDivisionTabContent($division, $pon_id, $tasks, $config)
{
    // Basic template untuk divisi lain
    $html = '';

    $tab_configs = [
        'Engineering' => ['material', 'drawings', 'tasks'],
        'Purchasing' => ['orders', 'suppliers', 'deliveries'],
        'Fabrikasi' => ['workshop', 'progress', 'qc_checks'],
        'Logistik' => ['shipping', 'tracking', 'deliveries'],
        'QC' => ['inspections', 'documents', 'compliance']
    ];

    $tabs = $tab_configs[$division] ?? $tab_configs['Engineering'];

    foreach ($tabs as $index => $tab) {
        $active_class = $index === 0 ? '' : ' hidden';
        $html .= '
        <div id="content-' . $tab . '" class="tab-content' . $active_class . '">
            <div class="bg-dark-light rounded-xl shadow-xl p-8">
                <div class="text-center py-12">
                    <i class="' . $config['icon'] . ' text-6xl text-' . $config['theme_color'] . '-600 mb-4"></i>
                    <h3 class="text-2xl font-bold text-white mb-2">' . $division . ' - ' . ucfirst($tab) . '</h3>
                    <p class="text-gray-400 mb-6">' . ucfirst($tab) . ' management for ' . $division . ' division</p>
                    <p class="text-' . $config['theme_color'] . '-300 text-sm">This section is under development</p>
                </div>
            </div>
        </div>';
    }

    return $html;
}

function getEngineeringTaskId($conn, $pon_id)
{
    $stmt = $conn->prepare("SELECT task_id FROM tasks WHERE pon_id = ? AND responsible_division = 'Engineering' LIMIT 1");
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $task = $result->fetch_assoc();
        return $task['task_id'];
    }

    // Jika tidak ada, buat task Engineering baru
    $task_name = "Engineering Design & Material Planning";
    $description = "Material list management and engineering design";
    $start_date = date('Y-m-d');
    $finish_date = date('Y-m-d', strtotime('+30 days'));
    $progress = 0.00;
    $status = 'In Progress';

    $stmt = $conn->prepare("INSERT INTO tasks (
        pon_id, phase, responsible_division, task_name, description, 
        start_date, finish_date, progress, status
    ) VALUES (?, 'Engineering', 'Engineering', ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "isssdss",
        $pon_id,
        $task_name,
        $description,
        $start_date,
        $finish_date,
        $progress,
        $status
    );

    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        // Fallback jika gagal create task
        return 0;
    }
}

function getEngineeringTabContent($pon_id, $tasks, $config)
{
    global $conn;

    // Get material lists
    $material_query = "SELECT * FROM material_lists WHERE pon_id = ? ORDER BY assy_marking, name";
    $stmt = $conn->prepare($material_query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $material_result = $stmt->get_result();
    $materials = [];
    while ($row = $material_result->fetch_assoc()) {
        $materials[] = $row;
    }

    $total_weight = 0;
    foreach ($materials as $material) {
        $quantity = $material['quantity'] ?? 0;
        $weight_kg = $material['weight_kg'] ?? 0;

        // Pastikan nilai numeric dan handle null
        $quantity = is_numeric($quantity) ? (float)$quantity : 0;
        $weight_kg = is_numeric($weight_kg) ? (float)$weight_kg : 0;

        $item_weight = $quantity * $weight_kg;
        $total_weight += $item_weight;
    }

    $html = '';

    // Material List Tab
    $html .= '
    <div id="content-material" class="tab-content">
        <div class="bg-dark-light rounded-xl shadow-xl mb-6">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-list-ol text-blue-400 mr-2"></i>
                    Bill of Materials (' . count($materials) . ' items)
                </h2>
                ' . (canManageMaterial() ? '
                <div class="flex items-center space-x-3">
                    <button onclick="showImportModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-file-import"></i>
                        <span>Import Excel</span>
                    </button>
                    <button onclick="showAddMaterialModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Material</span>
                    </button>
                </div>' : '<span class="text-gray-400 text-sm">Read-only access</span>') . '
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-blue-600 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">No.</th>
                            <th class="px-4 py-3 text-left font-semibold">Assy Marking</th>
                            <th class="px-4 py-3 text-left font-semibold">Rv</th>
                            <th class="px-4 py-3 text-left font-semibold">Name</th>
                            <th class="px-4 py-3 text-center font-semibold">Qty</th>
                            <th class="px-4 py-3 text-left font-semibold">Dimensions</th>
                            <th class="px-4 py-3 text-center font-semibold">Length (mm)</th>
                            <th class="px-4 py-3 text-center font-semibold">Weight (kg)</th>
                            <th class="px-4 py-3 text-center font-semibold">T. Weight (kg)</th>
                            <th class="px-4 py-3 text-left font-semibold">Remarks</th>
                            ' . (canManageMaterial() ? '<th class="px-4 py-3 text-center font-semibold">Actions</th>' : '') . '
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">';

    if (empty($materials)) {
        $html .= '
                        <tr>
                            <td colspan="' . (canManageMaterial() ? '11' : '10') . '" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3 opacity-50"></i>
                                <p>No material data found</p>
                                ' . (canManageMaterial() ? '
                                <button onclick="showAddMaterialModal()" class="text-blue-400 hover:text-blue-300 mt-2">
                                    <i class="fas fa-plus-circle mr-1"></i>Add first material
                                </button>' : '') . '
                            </td>
                        </tr>';
    } else {
        $no = 1;
        foreach ($materials as $material) {
            $quantity = $material['quantity'] ?? 0;
            $weight_kg = $material['weight_kg'] ?? 0;

            // Pastikan nilai numeric
            $quantity = is_numeric($quantity) ? (float)$quantity : 0;
            $weight_kg = is_numeric($weight_kg) ? (float)$weight_kg : 0;

            $total_weight_item = $quantity * $weight_kg;
            $html .= '
                        <tr class="hover:bg-gray-800 transition">
                            <td class="px-4 py-3 text-gray-300 text-sm">' . $no++ . '</td>
                            <td class="px-4 py-3">
                                <span class="text-white font-mono text-sm">' . htmlspecialchars($material['assy_marking'] ?? '-') . '</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-gray-400 text-sm">' . htmlspecialchars($material['rv'] ?? '-') . '</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-white text-sm">' . htmlspecialchars($material['name']) . '</span>
                            </td>
                            <td class="px-4 py-3 text-center text-white font-semibold">
                                ' . number_format($material['quantity']) . '
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-gray-400 text-sm">' . htmlspecialchars($material['dimensions'] ?? '-') . '</span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300">
                                ' . ($material['length_mm'] ? number_format($material['length_mm'], 0) : '-') . '
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300">
                                ' . ($material['weight_kg'] ? number_format($material['weight_kg'], 2) : '-') . '
                            </td>
                            <td class="px-4 py-3 text-center text-white font-bold">
                                ' . number_format($total_weight_item, 2) . '
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-gray-400 text-xs">' . htmlspecialchars($material['remarks'] ?? '-') . '</span>
                            </td>
                            ' . (canManageMaterial() ? '
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick="editMaterial(' . $material['material_id'] . ')" class="text-blue-400 hover:text-blue-300" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteMaterial(' . $material['material_id'] . ')" class="text-red-400 hover:text-red-300" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>' : '') . '
                        </tr>';
        }

        $html .= '
                        <!-- Total Row -->
                        <tr class="bg-blue-900 bg-opacity-20 font-bold">
                            <td colspan="' . (canManageMaterial() ? '8' : '7') . '" class="px-4 py-3 text-right text-white">TOTAL WEIGHT:</td>
                            <td class="px-4 py-3 text-center text-white text-lg">
                                ' . number_format($total_weight, 2) . ' kg
                            </td>
                            <td colspan="' . (canManageMaterial() ? '2' : '1') . '"></td>
                        </tr>';
    }

    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';

    // Drawings Tab
    $html .= '
    <div id="content-drawings" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl p-8">
            <div class="text-center py-12">
                <i class="fas fa-drafting-compass text-6xl text-blue-600 mb-4"></i>
                <h3 class="text-2xl font-bold text-white mb-2">Engineering Drawings</h3>
                <p class="text-gray-400 mb-6">Upload and manage technical drawings</p>
                <p class="text-blue-300 text-sm">Drawing management feature coming soon</p>
            </div>
        </div>
    </div>';

    // Tasks Tab
    $html .= '
    <div id="content-tasks" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-tasks text-blue-400 mr-2"></i>
                    Engineering Tasks (' . count($tasks) . ')
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800 text-gray-400 text-sm">
                        <tr>
                            <th class="px-6 py-4 text-left">Task Name</th>
                            <th class="px-6 py-4 text-left">Phase</th>
                            <th class="px-6 py-4 text-left">PIC</th>
                            <th class="px-6 py-4 text-center">Progress</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        ' . (empty($tasks) ? '
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                No engineering tasks found
                            </td>
                        </tr>' : '') . '
                        ' . (function () use ($tasks) {
        $tasks_html = '';
        foreach ($tasks as $task) {
            $tasks_html .= '
                                <tr class="hover:bg-gray-800 transition">
                                    <td class="px-6 py-4">
                                        <p class="text-white font-semibold">' . htmlspecialchars($task['task_name']) . '</p>
                                        ' . (!empty($task['description']) ? '
                                        <p class="text-gray-500 text-sm mt-1">' . htmlspecialchars(substr($task['description'], 0, 60)) . '...</p>' : '') . '
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs px-3 py-1 rounded-full bg-purple-900 text-purple-200 font-semibold">
                                            ' . $task['phase'] . '
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-300 text-sm">
                                        ' . htmlspecialchars($task['pic_internal'] ?? $task['assigned_to_name'] ?? '-') . '
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-white font-bold text-lg">' . round($task['progress']) . '%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold ' . get_status_color($task['status']) . ' text-white">
                                            ' . $task['status'] . '
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="updateTaskProgress(' . $task['task_id'] . ')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                                            <i class="fas fa-edit mr-1"></i>Update Progress
                                        </button>
                                    </td>
                                </tr>';
        }
        return $tasks_html;
    })() . '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';

    return $html;
}

function getPurchasingTabContent($pon_id, $tasks, $config, $material_items)
{
    global $conn;

    // Get material orders data
    $orders_query = "SELECT mo.*, u.full_name as created_by_name 
                     FROM material_orders mo 
                     LEFT JOIN users u ON mo.created_by = u.user_id 
                     WHERE mo.pon_id = ? 
                     ORDER BY mo.order_date DESC";
    $stmt = $conn->prepare($orders_query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    $orders = [];
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }

    $html = '';

    // Orders Tab
    $html .= '
    <div id="content-orders" class="tab-content">
        <div class="bg-dark-light rounded-xl shadow-xl mb-6">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-file-invoice text-green-400 mr-2"></i>
                    Purchase Orders (' . count($orders) . ' orders)
                </h2>
                ' . (canManagePurchasing() ? '
                <button onclick="showAddOrderModal()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Create PO</span>
                </button>' : '<span class="text-gray-400 text-sm">Read-only access</span>') . '
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-green-600 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 text-left">PO Ref</th>
                            <th class="px-4 py-3 text-left">Material Type</th>
                            <th class="px-4 py-3 text-left">Supplier</th>
                            <th class="px-4 py-3 text-center">Qty</th>
                            <th class="px-4 py-3 text-center">Order Date</th>
                            <th class="px-4 py-3 text-center">Expected Delivery</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">';

    if (empty($orders)) {
        $html .= '
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-file-invoice text-4xl mb-3 opacity-50"></i>
                                <p>No purchase orders found</p>
                                ' . (canManagePurchasing() ? '
                                <button onclick="showAddOrderModal()" class="text-green-400 hover:text-green-300 mt-2">
                                    <i class="fas fa-plus-circle mr-1"></i>Create first PO
                                </button>' : '') . '
                            </td>
                        </tr>';
    } else {
        foreach ($orders as $order) {
            $status_colors = [
                'Ordered' => 'bg-yellow-500',
                'Partial Received' => 'bg-blue-500',
                'Received' => 'bg-green-500',
                'Cancelled' => 'bg-red-500'
            ];

            $html .= '
                        <tr class="hover:bg-gray-800 transition">
                            <td class="px-4 py-3">
                                <span class="text-white font-mono text-sm">PO-' . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . '</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-white">' . htmlspecialchars($order['material_type']) . '</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-gray-300">' . htmlspecialchars($order['supplier_name']) . '</span>
                            </td>
                            <td class="px-4 py-3 text-center text-white font-semibold">
                                ' . ($order['quantity'] ? number_format($order['quantity'], 2) : '-') . ' ' . ($order['unit'] ?: '') . '
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300">
                                ' . ($order['order_date'] ? format_date_indo($order['order_date']) : '-') . '
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300">
                                ' . ($order['expected_receiving_date'] ? format_date_indo($order['expected_receiving_date']) : '-') . '
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold ' . $status_colors[$order['status']] . ' text-white">
                                    ' . $order['status'] . '
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick="viewOrder(' . $order['order_id'] . ')" 
                                            class="text-green-400 hover:text-green-300" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ' . (canManagePurchasing() ? '
                                    <button onclick="editOrder(' . $order['order_id'] . ')" 
                                            class="text-blue-400 hover:text-blue-300" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="updateOrderStatus(' . $order['order_id'] . ')" 
                                            class="text-yellow-400 hover:text-yellow-300" title="Update Status">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button onclick="deleteOrder(' . $order['order_id'] . ')" 
                                            class="text-red-400 hover:text-red-300" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>' : '') . '
                                </div>
                            </td>
                        </tr>';
        }
    }

    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';

    // Suppliers Tab
    $html .= '
    <div id="content-suppliers" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-address-book text-green-400 mr-2"></i>
                    Supplier Management
                </h2>
                <button onclick="showAddSupplierModal()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Add Supplier</span>
                </button>
            </div>

            <div class="p-6">
                <!-- Supplier Statistics - FIXED -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-800 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-white">' . count($material_items) . '</div>
                        <div class="text-gray-400 text-sm">Total Materials</div>
                    </div>
                    <div class="bg-gray-800 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-white">' . count($orders) . '</div>
                        <div class="text-gray-400 text-sm">Total POs</div>
                    </div>
                    <div class="bg-gray-800 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-yellow-400">' . count(array_filter($orders, function ($o) {
        return $o['status'] === 'Ordered';
    })) . '</div>
                        <div class="text-gray-400 text-sm">Pending Delivery</div>
                    </div>
                    <div class="bg-gray-800 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-400">' . count(array_filter($orders, function ($o) {
        return $o['status'] === 'Received';
    })) . '</div>
                        <div class="text-gray-400 text-sm">Completed</div>
                    </div>
                </div>

                <!-- Supplier List -->
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Frequent Suppliers</h3>
                    <div class="space-y-3" id="suppliersList">
                        <!-- Dynamic suppliers will be loaded here -->
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-address-book text-4xl mb-3 opacity-50"></i>
                            <p>Supplier management feature in development</p>
                            <button onclick="showAddSupplierModal()" class="text-green-400 hover:text-green-300 mt-2">
                                <i class="fas fa-plus-circle mr-1"></i>Add first supplier
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    // Deliveries Tab  
    $html .= '
    <div id="content-deliveries" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-truck-loading text-green-400 mr-2"></i>
                    Delivery Tracking
                </h2>
                <div class="flex items-center space-x-3">
                    <span class="text-gray-400 text-sm">' . count($orders) . ' orders total</span>
                    <button onclick="refreshDeliveries()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- Delivery Status Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-yellow-900 bg-opacity-20 p-4 rounded-lg border border-yellow-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-yellow-400">' . count(array_filter($orders, function ($o) {
        return $o['status'] === 'Ordered';
    })) . '</div>
                                <div class="text-yellow-300 text-sm">Ordered</div>
                            </div>
                            <i class="fas fa-clock text-yellow-400 text-2xl"></i>
                        </div>
                    </div>
                    <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-blue-400">' . count(array_filter($orders, function ($o) {
        return $o['status'] === 'Partial Received';
    })) . '</div>
                                <div class="text-blue-300 text-sm">Partial Received</div>
                            </div>
                            <i class="fas fa-truck-loading text-blue-400 text-2xl"></i>
                        </div>
                    </div>
                    <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-green-400">' . count(array_filter($orders, function ($o) {
        return $o['status'] === 'Received';
    })) . '</div>
                                <div class="text-green-300 text-sm">Received</div>
                            </div>
                            <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Deliveries -->
                <div class="bg-gray-800 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Upcoming Deliveries</h3>
                    <div class="space-y-3" id="upcomingDeliveries">' .
        getUpcomingDeliveriesHTML($orders) .
        '</div>
                </div>

                <!-- Recent Deliveries -->
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Recent Deliveries</h3>
                    <div class="space-y-3" id="recentDeliveries">' .
        getRecentDeliveriesHTML($orders) .
        '</div>
                </div>
            </div>
        </div>
    </div>';

    return $html;
}

// Helper functions untuk deliveries
function getUpcomingDeliveriesHTML($orders)
{
    $upcoming = array_filter($orders, function ($order) {
        return $order['expected_receiving_date'] &&
            $order['status'] !== 'Received' &&
            $order['status'] !== 'Cancelled';
    });

    usort($upcoming, function ($a, $b) {
        return strtotime($a['expected_receiving_date']) - strtotime($b['expected_receiving_date']);
    });

    $upcoming = array_slice($upcoming, 0, 5);

    if (empty($upcoming)) {
        return '<div class="text-center py-4 text-gray-500">
                    <i class="fas fa-calendar-day text-2xl mb-2"></i>
                    <p>No upcoming deliveries</p>
                </div>';
    }

    $html = '';
    foreach ($upcoming as $order) {
        $days_left = floor((strtotime($order['expected_receiving_date']) - time()) / (60 * 60 * 24));
        $status_class = $days_left < 0 ? 'text-red-400' : ($days_left <= 3 ? 'text-yellow-400' : 'text-green-400');

        $html .= '
        <div class="flex items-center justify-between p-3 bg-gray-750 rounded-lg">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <div>
                    <div class="text-white font-semibold">PO-' . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . '</div>
                    <div class="text-gray-400 text-sm">' . htmlspecialchars($order['supplier_name']) . '</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-white font-semibold">' . ($order['quantity'] ? number_format($order['quantity'], 2) : '0') . ' ' . ($order['unit'] ?: '') . '</div>
                <div class="' . $status_class . ' text-sm">
                    ' . ($order['expected_receiving_date'] ? format_date_indo($order['expected_receiving_date']) : 'No date') . '
                    ' . ($days_left < 0 ? ' (Overdue)' : ($days_left == 0 ? ' (Today)' : ($days_left == 1 ? ' (1 day)' : " ($days_left days)"))) . '
                </div>
            </div>
        </div>';
    }
    return $html;
}

function getRecentDeliveriesHTML($orders)
{
    $recent = array_filter($orders, function ($order) {
        return $order['status'] === 'Received' && $order['actual_receiving_date'];
    });

    usort($recent, function ($a, $b) {
        return strtotime($b['actual_receiving_date']) - strtotime($a['actual_receiving_date']);
    });

    $recent = array_slice($recent, 0, 5);

    if (empty($recent)) {
        return '<div class="text-center py-4 text-gray-500">
                    <i class="fas fa-truck text-2xl mb-2"></i>
                    <p>No recent deliveries</p>
                </div>';
    }

    $html = '';
    foreach ($recent as $order) {
        $html .= '
        <div class="flex items-center justify-between p-3 bg-gray-750 rounded-lg">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <div>
                    <div class="text-white font-semibold">PO-' . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . '</div>
                    <div class="text-gray-400 text-sm">' . htmlspecialchars($order['supplier_name']) . '</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-white font-semibold">' . ($order['quantity'] ? number_format($order['quantity'], 2) : '0') . ' ' . ($order['unit'] ?: '') . '</div>
                <div class="text-green-400 text-sm">
                    Delivered: ' . ($order['actual_receiving_date'] ? format_date_indo($order['actual_receiving_date']) : 'Unknown') . '
                </div>
            </div>
        </div>';
    }
    return $html;
}

// ==============================================
// FABRIKASI HELPER FUNCTIONS
// ==============================================

function getFabricationTabContent($pon_id, $tasks, $config)
{
    global $conn;

    // Get materials dengan fabrication progress
    $materials = getFabricationMaterialsData($conn, $pon_id);

    $html = '';

    // Workshop Progress Tab - FOKUS MATERIAL FABRICATION
    $html .= '
    <div id="content-workshop" class="tab-content">
        <div class="bg-dark-light rounded-xl shadow-xl mb-6">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-tools text-orange-400 mr-2"></i>
                    Material Fabrication Progress (' . count($materials) . ' materials)
                </h2>
                <div class="flex items-center space-x-3">
                    <span class="text-orange-300 text-sm">
                        ' . count(array_filter($materials, function ($m) {
        return $m['fabrication_status'] === 'Completed';
    })) . ' completed
                    </span>
                    <button onclick="showMaterialFabricationModal()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-play-circle"></i>
                        <span>Start Fabrication</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-orange-600 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 text-left">Material</th>
                            <th class="px-4 py-3 text-left">Details</th>
                            <th class="px-4 py-3 text-center">Order Status</th>
                            <th class="px-4 py-3 text-center">Fabrication Progress</th>
                            <th class="px-4 py-3 text-center">Fabrication Status</th>
                            <th class="px-4 py-3 text-center">Last Updated</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">';

    if (empty($materials)) {
        $html .= '
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-tools text-4xl mb-3 opacity-50"></i>
                                <p>No materials available for fabrication</p>
                                <p class="text-sm text-orange-300 mt-2">Materials need to be uploaded by Engineering division first</p>
                            </td>
                        </tr>';
    } else {
        foreach ($materials as $material) {
            $order_status_badge = getOrderStatusBadge($material['order_status']);
            $fabrication_status_badge = getFabricationStatusBadge($material['fabrication_status']);

            // FIX: Handle last_updated yang mungkin null
            $last_updated = '-';
            if (!empty($material['last_updated'])) {
                $last_updated = date('d M Y H:i', strtotime($material['last_updated']));
            } elseif (!empty($material['started_at'])) {
                $last_updated = date('d M Y H:i', strtotime($material['started_at'])) . ' (Started)';
            } elseif (!empty($material['created_at'])) {
                $last_updated = date('d M Y H:i', strtotime($material['created_at'])) . ' (Created)';
            }

            $html .= '
            <tr class="hover:bg-gray-800 transition">
                <td class="px-4 py-3">
                    <p class="text-white font-semibold">' . htmlspecialchars($material['material_name']) . '</p>
                    <p class="text-orange-300 text-sm font-mono">' . htmlspecialchars($material['assy_marking'] ?? 'N/A') . '</p>
                </td>
                <td class="px-4 py-3">
                    <p class="text-gray-300 text-sm">Qty: ' . $material['quantity'] . '</p>
                    <p class="text-gray-400 text-xs">' . htmlspecialchars($material['dimensions'] ?? '') . '</p>
                    <p class="text-gray-400 text-xs">' . ($material['total_weight_kg'] ? number_format($material['total_weight_kg'], 2) . ' kg' : '') . '</p>
                </td>
                <td class="px-4 py-3 text-center">
                    ' . $order_status_badge . '
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex flex-col items-center">
                        <span class="text-white font-bold text-lg">' . $material['fabrication_progress'] . '%</span>
                        <div class="w-full bg-gray-700 rounded-full h-2 mt-1">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: ' . $material['fabrication_progress'] . '%"></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    ' . $fabrication_status_badge . '
                </td>
                <td class="px-4 py-3 text-center text-gray-300 text-sm">
                    ' . $last_updated . '
                </td>
                <td class="px-4 py-3 text-center">
                    <button onclick="updateMaterialFabrication(' . $material['material_id'] . ')" class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-sm font-semibold transition">
                        ' . ($material['fabrication_status'] === 'Pending' ? 'Start' : 'Update') . '
                    </button>
                </td>
            </tr>';
        }
    }

    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Fabrication Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-orange-900 bg-opacity-20 p-6 rounded-lg border border-orange-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-orange-400">' . count($materials) . '</div>
                        <div class="text-orange-300 text-sm">Total Materials</div>
                    </div>
                    <i class="fas fa-boxes text-orange-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-orange-900 bg-opacity-20 p-6 rounded-lg border border-orange-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-orange-400">' . count(array_filter($materials, function ($m) {
        return $m['fabrication_status'] === 'Completed';
    })) . '</div>
                        <div class="text-orange-300 text-sm">Completed</div>
                    </div>
                    <i class="fas fa-check-circle text-orange-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-orange-900 bg-opacity-20 p-6 rounded-lg border border-orange-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-orange-400">' . count(array_filter($materials, function ($m) {
        return $m['fabrication_status'] === 'In Progress';
    })) . '</div>
                        <div class="text-orange-300 text-sm">In Progress</div>
                    </div>
                    <i class="fas fa-tools text-orange-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-orange-900 bg-opacity-20 p-6 rounded-lg border border-orange-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-orange-400">' . calculateTotalFabricationWeight($materials) . ' kg</div>
                        <div class="text-orange-300 text-sm">Total Weight</div>
                    </div>
                    <i class="fas fa-weight-hanging text-orange-400 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>';

    // Production Tracking Tab - UPDATE
    $html .= '
    <div id="content-progress" class="tab-content hidden">
        ' . generateProductionTrackingContent($pon_id) . '
    </div>';

    // QC Checks Tab - UPDATE  
    $html .= '
    <div id="content-qc_checks" class="tab-content hidden">
        ' . generateAdvancedQCChecksContent($pon_id) . '
    </div>';

    // TAMBAHKAN Tab Baru - Fabrication Reports
    $html .= '
    <div id="content-reports" class="tab-content hidden">
        ' . generateFabricationReportsContent($pon_id) . '
    </div>';

    return $html;
}

function getFabricationMaterialsData($conn, $pon_id)
{
    $query = "SELECT 
                ml.material_id,
                ml.assy_marking,
                ml.name as material_name,
                ml.quantity,
                ml.dimensions,
                ml.length_mm, 
                ml.weight_kg,
                ml.total_weight_kg,
                ml.remarks,
                COALESCE(mp.status, 'Pending') as fabrication_status,
                COALESCE(mp.progress_percent, 0) as fabrication_progress,
                mp.notes as fabrication_notes,
                mp.started_at,
                mp.completed_at,
                COALESCE(mp.updated_at, mp.started_at, ml.created_at) as last_updated, -- FIX: Gunakan field yang ada
                mo.status as order_status,
                mo.supplier_name
              FROM material_lists ml
              LEFT JOIN material_progress mp ON ml.material_id = mp.material_id AND mp.division = 'Fabrikasi'
              LEFT JOIN material_orders mo ON ml.material_id = mo.material_id
              WHERE ml.pon_id = ?
              ORDER BY ml.assy_marking";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }

    return $materials;
}

function getOrderStatusBadge($status)
{
    $statuses = [
        'Ordered' => 'bg-yellow-500',
        'Partial Received' => 'bg-blue-500',
        'Received' => 'bg-green-500',
        'Cancelled' => 'bg-red-500'
    ];

    $color = $statuses[$status] ?? 'bg-gray-500';
    $text = $status ?: 'Not Ordered';
    return '<span class="px-2 py-1 rounded-full text-xs font-semibold ' . $color . ' text-white">' . $text . '</span>';
}

function getFabricationStatusBadge($status)
{
    $statuses = [
        'Completed' => 'bg-green-500',
        'In Progress' => 'bg-orange-500',
        'Rejected' => 'bg-red-500',
        'Pending' => 'bg-gray-500'
    ];

    $color = $statuses[$status] ?? 'bg-gray-500';
    return '<span class="px-2 py-1 rounded-full text-xs font-semibold ' . $color . ' text-white">' . $status . '</span>';
}

function calculateTotalFabricationWeight($materials)
{
    $total = 0;
    foreach ($materials as $material) {
        $total += $material['total_weight_kg'] ?? 0;
    }
    return number_format($total, 2);
}

function generateFabricationTasksContent($tasks)
{
    if (empty($tasks)) {
        return '<div class="p-8 text-center text-gray-500">
                    <i class="fas fa-tasks text-4xl mb-3 opacity-50"></i>
                    <p>No fabrication tasks created yet</p>
                    <p class="text-sm text-orange-300 mt-2">Tasks will be created based on material fabrication progress</p>
                </div>';
    }

    $html = '<div class="p-6">';
    foreach ($tasks as $task) {
        $html .= '
            <div class="bg-gray-800 p-4 rounded-lg mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-white font-semibold">' . htmlspecialchars($task['task_name']) . '</h4>
                        <p class="text-gray-400 text-sm">' . htmlspecialchars($task['description'] ?? '') . '</p>
                    </div>
                    <div class="text-right">
                        <span class="text-white font-bold text-lg">' . $task['progress'] . '%</span>
                        <div class="w-32 bg-gray-700 rounded-full h-2 mt-1">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: ' . $task['progress'] . '%"></div>
                        </div>
                    </div>
                </div>
            </div>';
    }
    $html .= '</div>';

    return $html;
}

function generateQCChecksContent($materials)
{
    $completed_materials = array_filter($materials, function ($m) {
        return $m['fabrication_status'] === 'Completed';
    });

    return '<div class="bg-dark-light rounded-xl shadow-xl">
        <div class="p-6 border-b border-gray-700">
            <h2 class="text-xl font-bold text-white">
                <i class="fas fa-clipboard-check text-orange-400 mr-2"></i>
                Quality Control Checks
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-800 p-6 rounded-lg">
                    <h4 class="text-white font-semibold mb-4">Ready for QC (' . count($completed_materials) . ' materials)</h4>
                    <div class="space-y-3">
                        ' . generateQCReadyList($completed_materials) . '
                    </div>
                </div>
                
                <div class="bg-gray-800 p-6 rounded-lg">
                    <h4 class="text-white font-semibold mb-4">QC Checklist</h4>
                    ' . generateQCChecklist() . '
                </div>
            </div>
        </div>
    </div>';
}

function generateQCReadyList($completed_materials)
{
    if (empty($completed_materials)) {
        return '<div class="text-center py-4 text-gray-500">
                    <i class="fas fa-check-circle text-2xl mb-2 opacity-50"></i>
                    <p>No materials ready for QC</p>
                    <p class="text-xs text-orange-300 mt-1">Complete fabrication first</p>
                </div>';
    }

    $html = '';
    foreach ($completed_materials as $material) {
        $html .= '
            <div class="flex items-center justify-between p-3 bg-gray-750 rounded-lg">
                <div class="flex items-center space-x-3">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <div>
                        <div class="text-white font-semibold text-sm">' . htmlspecialchars($material['material_name']) . '</div>
                        <div class="text-gray-400 text-xs">' . htmlspecialchars($material['assy_marking'] ?? '') . '</div>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="performQCCheck(' . $material['material_id'] . ')" class="text-green-400 hover:text-green-300" title="QC Pass">
                        <i class="fas fa-check"></i>
                    </button>
                    <button onclick="showQCIssue(' . $material['material_id'] . ')" class="text-red-400 hover:text-red-300" title="QC Fail">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>';
    }
    return $html;
}

function generateQCChecklist()
{
    $checklist = [
        'Dimensional Accuracy ±2mm',
        'Welding Quality - No cracks',
        'Surface Preparation - Clean',
        'Material Grade Verification',
        'Safety Standards Compliance',
        'Documentation Complete'
    ];

    $html = '';
    foreach ($checklist as $item) {
        $html .= '
            <div class="flex items-center justify-between p-3 bg-gray-750 rounded mb-2">
                <span class="text-white text-sm">' . $item . '</span>
                <div class="flex space-x-2">
                    <button class="text-green-400 hover:text-green-300 qc-check" data-check="pass">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="text-red-400 hover:text-red-300 qc-check" data-check="fail">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>';
    }
    return $html;
}

// TAMBAHKAN SETELAH function generateQCChecklist()

function generateProductionTrackingContent($pon_id)
{
    $html = '
    <div class="bg-dark-light rounded-xl shadow-xl">
        <div class="p-6 border-b border-gray-700 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">
                <i class="fas fa-chart-line text-orange-400 mr-2"></i>
                Real-time Production Tracking
            </h2>
            <div class="flex items-center space-x-3">
                <button onclick="refreshProductionData()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
                <button onclick="showWorkshopActivityModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Log Activity</span>
                </button>
            </div>
        </div>

        <div class="p-6">
            <!-- Production Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-orange-900 bg-opacity-20 p-4 rounded-lg border border-orange-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-orange-400" id="activeMaterials">0</div>
                            <div class="text-orange-300 text-sm">Active Materials</div>
                        </div>
                        <i class="fas fa-tools text-orange-400"></i>
                    </div>
                </div>
                
                <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-400" id="avgProgress">0%</div>
                            <div class="text-blue-300 text-sm">Avg Progress</div>
                        </div>
                        <i class="fas fa-chart-bar text-blue-400"></i>
                    </div>
                </div>
                
                <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-green-400" id="completedToday">0</div>
                            <div class="text-green-300 text-sm">Completed Today</div>
                        </div>
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                </div>
                
                <div class="bg-purple-900 bg-opacity-20 p-4 rounded-lg border border-purple-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-purple-400" id="totalOperators">0</div>
                            <div class="text-purple-300 text-sm">Operators</div>
                        </div>
                        <i class="fas fa-users text-purple-400"></i>
                    </div>
                </div>
            </div>

            <!-- Live Production Feed -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h4 class="text-white font-semibold mb-4">Live Production Feed</h4>
                <div id="productionFeed" class="space-y-3 max-h-96 overflow-y-auto">
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p>Loading production data...</p>
                    </div>
                </div>
            </div>

            <!-- Workstation Status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h5 class="text-white font-semibold mb-3">Workstation A</h5>
                    <div class="space-y-2" id="workstationA">
                        <!-- Dynamic content -->
                    </div>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h5 class="text-white font-semibold mb-3">Workstation B</h5>
                    <div class="space-y-2" id="workstationB">
                        <!-- Dynamic content -->
                    </div>
                </div>
                <div class="bg-gray-800 p-4 rounded-lg">
                    <h5 class="text-white font-semibold mb-3">Workstation C</h5>
                    <div class="space-y-2" id="workstationC">
                        <!-- Dynamic content -->
                    </div>
                </div>
            </div>
        </div>
    </div>';

    return $html;
}

function generateAdvancedQCChecksContent($pon_id)
{
    $html = '
    <div class="bg-dark-light rounded-xl shadow-xl">
        <div class="p-6 border-b border-gray-700">
            <h2 class="text-xl font-bold text-white">
                <i class="fas fa-clipboard-check text-orange-400 mr-2"></i>
                Advanced Quality Control
            </h2>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- QC Dashboard -->
                <div class="lg:col-span-2 bg-gray-800 rounded-lg p-6">
                    <h4 class="text-white font-semibold mb-4">QC Dashboard</h4>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-green-900 bg-opacity-20 p-4 rounded border border-green-700">
                            <div class="text-2xl font-bold text-green-400" id="qcPassed">0</div>
                            <div class="text-green-300 text-sm">QC Passed</div>
                        </div>
                        <div class="bg-red-900 bg-opacity-20 p-4 rounded border border-red-700">
                            <div class="text-2xl font-bold text-red-400" id="qcFailed">0</div>
                            <div class="text-red-300 text-sm">QC Failed</div>
                        </div>
                    </div>
                    
                    <!-- QC Progress -->
                    <div class="mb-6">
                        <div class="flex justify-between text-white text-sm mb-2">
                            <span>QC Completion</span>
                            <span id="qcCompletion">0%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-3">
                            <div id="qcProgressBar" class="bg-orange-500 h-3 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-gray-800 rounded-lg p-6">
                    <h4 class="text-white font-semibold mb-4">Quick Actions</h4>
                    <div class="space-y-3">
                        <button onclick="generateQCReport()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded flex items-center justify-center space-x-2">
                            <i class="fas fa-file-pdf"></i>
                            <span>Generate QC Report</span>
                        </button>
                        <button onclick="showBulkQCCheck()" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded flex items-center justify-center space-x-2">
                            <i class="fas fa-check-double"></i>
                            <span>Bulk QC Check</span>
                        </button>
                        <button onclick="showQCIssueTracker()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded flex items-center justify-center space-x-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Issue Tracker</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Detailed QC Checklist -->
            <div class="mt-6 bg-gray-800 rounded-lg p-6">
                <h4 class="text-white font-semibold mb-4">Detailed QC Checklist</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="detailedChecklist">
                    <!-- Dynamic checklist -->
                </div>
            </div>
        </div>
    </div>';

    return $html;
}

function generateFabricationReportsContent($pon_id)
{
    $html = '
    <div class="bg-dark-light rounded-xl shadow-xl">
        <div class="p-6 border-b border-gray-700 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">
                <i class="fas fa-chart-bar text-orange-400 mr-2"></i>
                Fabrication Reports & Analytics
            </h2>
            <div class="flex items-center space-x-3">
                <button onclick="generateFabricationReport()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-download"></i>
                    <span>Export Report</span>
                </button>
            </div>
        </div>

        <div class="p-6">
            <!-- Report Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-orange-900 bg-opacity-20 p-4 rounded-lg border border-orange-700">
                    <div class="text-2xl font-bold text-orange-400" id="reportTotalMaterials">-</div>
                    <div class="text-orange-300 text-sm">Total Materials</div>
                </div>
                <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                    <div class="text-2xl font-bold text-green-400" id="reportCompletionRate">-</div>
                    <div class="text-green-300 text-sm">Completion Rate</div>
                </div>
                <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                    <div class="text-2xl font-bold text-blue-400" id="reportTotalWeight">-</div>
                    <div class="text-blue-300 text-sm">Total Weight (kg)</div>
                </div>
                <div class="bg-purple-900 bg-opacity-20 p-4 rounded-lg border border-purple-700">
                    <div class="text-2xl font-bold text-purple-400" id="reportAvgDuration">-</div>
                    <div class="text-purple-300 text-sm">Avg Duration (days)</div>
                </div>
            </div>

            <!-- Charts Placeholder -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-800 p-6 rounded-lg">
                    <h4 class="text-white font-semibold mb-4">Progress Distribution</h4>
                    <div id="progressChart" class="h-64 flex items-center justify-center text-gray-500">
                        <div class="text-center">
                            <i class="fas fa-chart-pie text-3xl mb-2"></i>
                            <p>Progress chart will be displayed here</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-800 p-6 rounded-lg">
                    <h4 class="text-white font-semibold mb-4">Status Overview</h4>
                    <div id="statusChart" class="h-64 flex items-center justify-center text-gray-500">
                        <div class="text-center">
                            <i class="fas fa-chart-bar text-3xl mb-2"></i>
                            <p>Status chart will be displayed here</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Report Table -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h4 class="text-white font-semibold mb-4">Material Fabrication Details</h4>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-700 text-gray-300 text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left">Material</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Progress</th>
                                <th class="px-4 py-3 text-center">Duration</th>
                                <th class="px-4 py-3 text-center">Weight</th>
                                <th class="px-4 py-3 text-center">QC Status</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody" class="divide-y divide-gray-700">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-chart-bar text-2xl mb-2"></i>
                                    <p>Generate report to view details</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';

    return $html;
}
?>