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
    // UTILITY FUNCTIONS 
    // ==============================================

    function formatDateDisplay(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function formatDateTimeDisplay(dateTimeString) {
        if (!dateTimeString) return '-';
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-600' : 
        type === 'error' ? 'bg-red-600' : 
        type === 'warning' ? 'bg-yellow-600' : 'bg-blue-600'
        }`;
        toast.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle' : 
                type === 'error' ? 'fa-exclamation-circle' : 
                type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'
            }"></i>
            <span>${message}</span>
        </div>
        `;

        document.body.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

    function showLoading(message = 'Loading...') {
        let loading = document.getElementById('globalLoading');
        if (!loading) {
            loading = document.createElement('div');
            loading.id = 'globalLoading';
            loading.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loading.innerHTML = `
            <div class="bg-gray-800 rounded-lg p-6 flex items-center space-x-3">
                <i class="fas fa-spinner fa-spin text-blue-400 text-xl"></i>
                <span class="text-white">${message}</span>
            </div>
        `;
            document.body.appendChild(loading);
        }
    }

    function hideLoading() {
        const loading = document.getElementById('globalLoading');
        if (loading && loading.parentNode) {
            loading.parentNode.removeChild(loading);
        }
    }

    function validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('border-red-500');
                isValid = false;
            } else {
                field.classList.remove('border-red-500');
            }
        });

        return isValid;
    }

    // ==============================================
    // COMMON FUNCTIONS
    // ==============================================

    // Tab Management
    function switchTab(tabName) {
        console.log('🔄 Switching to tab:', tabName);

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

            // Initialize specific tab data
            switch (tabName) {
                case 'suppliers':
                    console.log('📋 Loading suppliers...');
                    loadSuppliersList();
                    break;

                case 'delivery':
                    console.log('🚚 Loading delivery tracking...');
                    loadDeliveriesList();
                    break;

                case 'deliveries': // TAMBAHAN UNTUK DELIVERY NOTES
                    console.log('📦 Loading delivery notes...');
                    loadDeliveryNotesList();
                    break;

                case 'drawings':
                    console.log('📐 Loading drawings...');
                    initDrawingsTab();
                    break;

                default:
                    console.log('✅ Tab loaded:', tabName);
            }
        } else {
            console.warn('⚠️ Tab content not found:', 'content-' + tabName);
        }

        // Activate selected tab button
        const tabElement = document.getElementById('tab-' + tabName);
        if (tabElement) {
            tabElement.classList.add('border-' + themeColor + '-500', 'text-white');
            tabElement.classList.remove('border-transparent', 'text-gray-400');
        }

        // Update URL hash (optional, untuk bookmark support)
        if (history.pushState) {
            history.pushState(null, null, '#' + tabName);
        } else {
            window.location.hash = tabName;
        }
    }

    function getStatusColor(status) {
        const colors = {
            'Completed': 'bg-green-500',
            'In Progress': 'bg-orange-500',
            'Pending': 'bg-gray-500',
            'Rejected': 'bg-red-500',
        };
        return colors[status] || 'bg-gray-500';
    }

    function getProgressColor(progress) {
        if (progress >= 90) return 'text-green-400';
        if (progress >= 70) return 'text-green-300';
        if (progress >= 50) return 'text-yellow-400';
        if (progress >= 30) return 'text-orange-400';
        if (progress > 0) return 'text-red-400';
        return 'text-gray-400';
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

    // ==============================================
    // DRAWING MANAGEMENT FUNCTIONS
    // ==============================================

    function showUploadDrawingModal() {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to upload drawings");
            return;
        <?php endif; ?>

        document.getElementById("drawingUploadForm").reset();
        document.getElementById("drawingUploadModal").classList.remove("hidden");

        // Set default values
        document.getElementById("drawing_revision").value = "A";
        document.getElementById("drawing_status").value = "Draft";
        document.getElementById("upload_date").value = "<?php echo date('Y-m-d'); ?>";
    }

    function closeDrawingUploadModal() {
        document.getElementById("drawingUploadModal").classList.add("hidden");
    }

    function uploadDrawing(event) {
        event.preventDefault();

        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to upload drawings");
            return;
        <?php endif; ?>

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        submitBtn.disabled = true;

        fetch("drawing_ajax.php?action=upload", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDrawingUploadModal();
                    alert("✅ " + data.message);
                    refreshDrawingsList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert("❌ Upload failed: " + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function updateDrawingStatistics(drawings) {
        const total = drawings.length;
        const approved = drawings.filter(d => d.status === 'Approved').length;
        const review = drawings.filter(d => d.status === 'For Review').length;
        const rejected = drawings.filter(d => d.status === 'Rejected').length;

        document.getElementById('totalDrawings').textContent = total;
        document.getElementById('approvedDrawings').textContent = approved;
        document.getElementById('reviewDrawings').textContent = review;
        document.getElementById('rejectedDrawings').textContent = rejected;
    }

    // Update refreshDrawingsList function
    function refreshDrawingsList() {
        const drawingsTable = document.getElementById('drawingsTableBody');
        if (drawingsTable) {
            drawingsTable.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p>Loading drawings...</p>
                    </td>
                </tr>
            `;
        }

        fetch(`drawing_ajax.php?action=list&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDrawingsTable(data.drawings);
                    updateDrawingStatistics(data.drawings); // Add this line
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Load drawings error:', error);
                if (drawingsTable) {
                    drawingsTable.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Error loading drawings</p>
                            </td>
                        </tr>
                    `;
                }
            });
    }

    function updateDrawingsTable(drawings) {
        const tbody = document.getElementById('drawingsTableBody');
        if (!tbody) return;

        if (drawings.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-drafting-compass text-4xl mb-3 opacity-50"></i>
                        <p>No drawings uploaded yet</p>
                        <button onclick="showUploadDrawingModal()" class="text-blue-400 hover:text-blue-300 mt-2">
                            <i class="fas fa-plus-circle mr-1"></i>Upload first drawing
                        </button>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        drawings.forEach((drawing, index) => {
            const statusColors = {
                'Draft': 'bg-gray-500',
                'For Review': 'bg-yellow-500',
                'Approved': 'bg-green-500',
                'Rejected': 'bg-red-500'
            };

            const fileTypeIcon = getFileTypeIcon(drawing.file_type);
            const fileSizeMB = drawing.file_size_mb || (drawing.file_size / 1024 / 1024).toFixed(2);

            html += `
                <tr class="hover:bg-gray-800 transition">
                    <td class="px-4 py-3 text-gray-300 text-sm">${index + 1}</td>
                    <td class="px-4 py-3">
                        <span class="text-white font-mono text-sm font-semibold">${drawing.drawing_number}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-white">${escapeHtml(drawing.drawing_name)}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-blue-300 font-semibold">${drawing.revision || 'A'}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <i class="${fileTypeIcon} text-lg ${fileTypeIcon.includes('pdf') ? 'text-red-400' : 'text-blue-400'}"></i>
                        <span class="text-gray-400 text-sm ml-1">${drawing.file_type?.split('/')[1]?.toUpperCase() || 'FILE'}</span>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-300 text-sm">
                        ${fileSizeMB} MB
                    </td>
                    <td class="px-4 py-3 text-center text-gray-300">
                        ${formatDateDisplay(drawing.upload_date)}
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColors[drawing.status]} text-white">
                            ${drawing.status}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center space-x-2">
                            <button onclick="viewDrawing(${drawing.drawing_id})" 
                                    class="text-green-400 hover:text-green-300" title="View/Download">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="downloadDrawing(${drawing.drawing_id})" 
                                    class="text-blue-400 hover:text-blue-300" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <?php if (canManageMaterial()): ?>
                            <button onclick="updateDrawingStatus(${drawing.drawing_id})" 
                                    class="text-yellow-400 hover:text-yellow-300" title="Update Status">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteDrawing(${drawing.drawing_id})" 
                                    class="text-red-400 hover:text-red-300" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function getFileTypeIcon(fileType) {
        if (fileType?.includes('pdf')) return 'fas fa-file-pdf';
        if (fileType?.includes('dwg') || fileType?.includes('dxf')) return 'fas fa-file-code';
        return 'fas fa-file';
    }

    function viewDrawing(drawingId) {
        // Open in new tab for PDF, download for others
        window.open(`drawing_ajax.php?action=view&id=${drawingId}`, '_blank');
    }

    function downloadDrawing(drawingId) {
        window.open(`drawing_ajax.php?action=download&id=${drawingId}`, '_blank');
    }

    function updateDrawingStatus(drawingId) {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to update drawing status");
            return;
        <?php endif; ?>

        // Fetch drawing data first
        fetch(`drawing_ajax.php?action=get&id=${drawingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showDrawingStatusModal(data.drawing);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Error loading drawing:', error);
                alert("Error loading drawing data");
            });
    }

    function updateStatusSelection(selectedInput) {
        // Update all option borders
        document.querySelectorAll('.status-option').forEach(option => {
            option.classList.remove('border-blue-500', 'bg-blue-900', 'bg-opacity-20');
            option.classList.add('border-transparent');

            const radio = option.querySelector('input[type="radio"]');
            const dot = option.querySelector('.w-2.h-2');

            if (radio.checked) {
                option.classList.add('border-blue-500', 'bg-blue-900', 'bg-opacity-20');
                option.classList.remove('border-transparent');
                dot.classList.add('bg-blue-400');
            } else {
                dot.classList.remove('bg-blue-400');
            }
        });

        // Update hidden select
        document.getElementById('drawing_status_select').value = selectedInput.value;
    }

    // Initialize status options on modal show
    function showDrawingStatusModal(drawing) {
        document.getElementById("status_drawing_id").value = drawing.drawing_id;
        document.getElementById("status_drawing_number").textContent = drawing.drawing_number;
        document.getElementById("status_drawing_name").textContent = drawing.drawing_name;
        document.getElementById("status_current_status").textContent = drawing.status;
        document.getElementById("drawing_status_select").value = drawing.status;
        document.getElementById("status_notes").value = drawing.notes || '';

        // Update status options UI
        document.querySelectorAll('.status-option').forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            const dot = option.querySelector('.w-2.h-2');

            if (radio.value === drawing.status) {
                radio.checked = true;
                option.classList.add('border-blue-500', 'bg-blue-900', 'bg-opacity-20');
                dot.classList.add('bg-blue-400');
            } else {
                radio.checked = false;
                option.classList.remove('border-blue-500', 'bg-blue-900', 'bg-opacity-20');
                dot.classList.remove('bg-blue-400');
            }
        });

        document.getElementById("drawingStatusModal").classList.remove("hidden");
    }

    function closeDrawingStatusModal() {
        document.getElementById("drawingStatusModal").classList.add("hidden");
    }

    function submitDrawingStatusUpdate(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        fetch("drawing_ajax.php?action=update_status", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDrawingStatusModal();
                    alert("✅ " + data.message);
                    refreshDrawingsList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Status update error:', error);
                alert("❌ Update failed: " + error.message);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function deleteDrawing(drawingId) {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to delete drawings");
            return;
        <?php endif; ?>

        if (!confirm("Are you sure you want to delete this drawing? This action cannot be undone.")) {
            return;
        }

        fetch(`drawing_ajax.php?action=delete&id=${drawingId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("✅ " + data.message);
                    refreshDrawingsList();
                } else {
                    alert("❌ " + data.message);
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert("❌ Delete failed");
            });
    }

    // Initialize drawings list when tab is shown
    function initDrawingsTab() {
        refreshDrawingsList();
    }

    // Enhanced tab switching to handle drawings initialization
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

            // Initialize drawings tab if it's being shown
            if (tabName === 'drawings') {
                initDrawingsTab();
            }
        }

        // Activate selected tab
        const tabElement = document.getElementById('tab-' + tabName);
        if (tabElement) {
            tabElement.classList.add('border-' + themeColor + '-500', 'text-white');
            tabElement.classList.remove('border-transparent', 'text-gray-400');
        }
    }

    function updateProgress() {
        <?php if (!canManageMaterial()): ?>
            alert("You don't have permission to update progress");
            return;
        <?php endif; ?>

        // Show loading in modal
        document.getElementById("taskSelectionBody").innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-blue-400 mb-2"></i>
                <p class="text-gray-400">Loading engineering tasks...</p>
            </div>
        `;

        document.getElementById("taskSelectionModal").classList.remove("hidden");

        // Fetch engineering tasks
        fetch(`progress_ajax.php?action=get_tasks&pon_id=<?php echo $pon_id; ?>&division=Engineering`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.tasks.length > 0) {
                        updateTaskSelectionList(data.tasks);
                    } else {
                        document.getElementById("taskSelectionBody").innerHTML = `
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-tasks text-3xl mb-3 opacity-50"></i>
                                <p>No engineering tasks found</p>
                                <p class="text-sm text-blue-300 mt-2">Create tasks first in the Tasks tab</p>
                            </div>
                        `;
                    }
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error("Error loading tasks:", error);
                document.getElementById("taskSelectionBody").innerHTML = `
                    <div class="text-center py-8 text-red-500">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <p>Error loading tasks</p>
                        <p class="text-sm">${error.message}</p>
                    </div>
                `;
            });
    }

    function updateTaskSelectionList(tasks) {
        const tbody = document.getElementById("taskSelectionBody");
        let html = '';

        tasks.forEach(task => {
            const progressColor = getProgressColor(task.progress);
            const statusColor = getStatusColor(task.status);

            html += `
                <div class="task-selection-item p-4 bg-gray-750 rounded-lg mb-3 cursor-pointer hover:bg-gray-700 transition border border-gray-600"
                     onclick="selectTaskForProgressUpdate(${task.task_id})">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h4 class="text-white font-semibold text-lg">${escapeHtml(task.task_name)}</h4>
                            <div class="flex items-center space-x-4 mt-2">
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-400 text-sm">Phase:</span>
                                    <span class="text-blue-300 text-sm font-medium">${task.phase}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-400 text-sm">Status:</span>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusColor}">${task.status}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-400 text-sm">Due:</span>
                                    <span class="text-gray-300 text-sm">${formatDateDisplay(task.finish_date)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold ${progressColor}">${task.progress}%</div>
                            <div class="w-24 bg-gray-600 rounded-full h-2 mt-1">
                                <div class="h-2 rounded-full ${progressColor.replace('text-', 'bg-')}" style="width: ${task.progress}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        tbody.innerHTML = html;
    }



    function selectTaskForProgressUpdate(taskId) {
        closeTaskSelectionModal();

        // Fetch task detail dan buka progress update modal
        fetch(`progress_ajax.php?action=get&id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showProgressUpdateModal(data.task);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error("Error loading task:", error);
                alert("Error loading task data");
            });
    }

    function closeTaskSelectionModal() {
        document.getElementById("taskSelectionModal").classList.add("hidden");
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
        document.getElementById("progress_notes").value = '';

        // Update display
        updateProgressValue(task.progress || 0);

        // Update modal title dengan task name
        document.querySelector("#progressUpdateModal h3").textContent = `Update Progress: ${task.task_name}`;

        document.getElementById("progressUpdateModal").classList.remove("hidden");
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

    function closeProgressUpdateModal() {
        document.getElementById("progressUpdateModal").classList.add("hidden");
    }

    function refreshTasksList() {
        // Refresh tasks tab jika sedang aktif
        if (!document.getElementById('content-tasks').classList.contains('hidden')) {
            location.reload(); // Simple refresh untuk sekarang
        }
    }

    // ==============================================
    // PURCHASING FUNCTIONS
    // ==============================================

    // ==============================================
    // SUPPLIER MANAGEMENT FUNCTIONS - COMPLETE
    // ==============================================

    function showAddSupplierModal() {
        document.getElementById("supplierModalTitle").textContent = "Add New Supplier";
        document.getElementById("saveSupplierButtonText").textContent = "Save Supplier";
        document.getElementById("supplierForm").reset();
        document.getElementById("supplier_id").value = "";
        document.getElementById("supplierModal").classList.remove("hidden");
    }

    function editSupplier(supplierId) {
        showLoading('Loading supplier data...');

        fetch(`supplier_ajax.php?action=get&id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    document.getElementById("supplierModalTitle").textContent = "Edit Supplier";
                    document.getElementById("saveSupplierButtonText").textContent = "Update Supplier";
                    document.getElementById("supplier_id").value = data.supplier.supplier_id;
                    document.getElementById("supplier_name").value = data.supplier.supplier_name;
                    document.getElementById("contact_person").value = data.supplier.contact_person;
                    document.getElementById("phone").value = data.supplier.phone;
                    document.getElementById("email").value = data.supplier.email;
                    document.getElementById("address").value = data.supplier.address || '';
                    document.getElementById("city").value = data.supplier.city || '';
                    document.getElementById("country").value = data.supplier.country || 'Indonesia';
                    document.getElementById("tax_number").value = data.supplier.tax_number || '';
                    document.getElementById("bank_account").value = data.supplier.bank_account || '';
                    document.getElementById("payment_terms").value = data.supplier.payment_terms || '';
                    document.getElementById("supplier_notes").value = data.supplier.notes || '';

                    document.getElementById("supplierModal").classList.remove("hidden");
                } else {
                    showToast('Error loading supplier data', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error("Error:", error);
                showToast('Error loading supplier data', 'error');
            });
    }

    function saveSupplier(event) {
        event.preventDefault();

        if (!validateForm(event.target)) {
            showToast('Please fill all required fields', 'warning');
            return;
        }

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        fetch("supplier_ajax.php?action=save", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSupplierModal();
                    showToast(data.message, 'success');
                    refreshSuppliersList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                showToast("Save failed: " + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function deleteSupplier(supplierId) {
        if (!confirm("Are you sure you want to delete this supplier?")) {
            return;
        }

        showLoading('Deleting supplier...');

        fetch(`supplier_ajax.php?action=delete&id=${supplierId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshSuppliersList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Delete error:', error);
                showToast("Delete failed: " + error.message, 'error');
            });
    }

    function closeSupplierModal() {
        document.getElementById("supplierModal").classList.add("hidden");
    }

    function refreshSuppliersList() {
        if (document.getElementById('content-suppliers') &&
            !document.getElementById('content-suppliers').classList.contains('hidden')) {
            loadSuppliersList();
        }
    }

    function loadSuppliersList() {
        showLoading('Loading suppliers...');

        fetch('supplier_ajax.php?action=list')
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    updateSuppliersTable(data.suppliers);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error loading suppliers:', error);
                showToast('Error loading suppliers', 'error');
            });
    }

    function updateSuppliersTable(suppliers) {
        const tbody = document.getElementById('suppliersTableBody');
        if (!tbody) return;

        if (suppliers.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-address-book text-4xl mb-3 opacity-50"></i>
                    <p>No suppliers found</p>
                    <button onclick="showAddSupplierModal()" class="text-green-400 hover:text-green-300 mt-2">
                        <i class="fas fa-plus-circle mr-1"></i>Add first supplier
                    </button>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';
        suppliers.forEach((supplier, index) => {
            html += `
            <tr class="hover:bg-gray-800 transition">
                <td class="px-4 py-3 text-gray-300 text-sm">${index + 1}</td>
                <td class="px-4 py-3">
                    <div class="text-white font-semibold">${escapeHtml(supplier.supplier_name)}</div>
                    <div class="text-gray-400 text-xs">${supplier.order_count || 0} orders</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-white">${escapeHtml(supplier.contact_person)}</div>
                    <div class="text-gray-400 text-xs">${escapeHtml(supplier.phone)}</div>
                    <div class="text-gray-400 text-xs">${escapeHtml(supplier.email)}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-gray-300 text-sm">${escapeHtml(supplier.city || 'N/A')}</div>
                    <div class="text-gray-400 text-xs">${escapeHtml(supplier.tax_number || 'No tax number')}</div>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${supplier.is_active ? 'bg-green-500' : 'bg-gray-500'} text-white">
                        ${supplier.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="px-4 py-3 text-center text-gray-300 text-sm">
                    ${formatDateDisplay(supplier.created_at)}
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center space-x-2">
                        <button onclick="editSupplier(${supplier.supplier_id})" 
                                class="text-blue-400 hover:text-blue-300" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteSupplier(${supplier.supplier_id})" 
                                class="text-red-400 hover:text-red-300" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
    }

    // ==============================================
    // DELIVERY MANAGEMENT FUNCTIONS - COMPLETE
    // ==============================================

    function showAddDeliveryModal(orderId = null) {
        document.getElementById("deliveryModalTitle").textContent = "Schedule Delivery";
        document.getElementById("saveDeliveryButtonText").textContent = "Save Delivery";
        document.getElementById("deliveryForm").reset();
        document.getElementById("delivery_id").value = "";

        // Set default delivery number
        const deliveryNumber = 'DEL-' + new Date().getTime();
        document.getElementById("delivery_number").value = deliveryNumber;
        document.getElementById("delivery_date").value = new Date().toISOString().split('T')[0];

        // Reset order info section
        document.getElementById("deliveryOrderInfo").classList.add("hidden");
        document.getElementById("delivery_order_id").value = "";

        // Jika ada orderId, set dan load order info
        if (orderId) {
            document.getElementById("delivery_order_id").value = orderId;
            loadOrderInfoForDelivery(orderId);
        } else {
            // Jika tidak ada orderId, tampilkan dropdown untuk memilih order
            showOrderSelectionForDelivery();
        }

        document.getElementById("deliveryModal").classList.remove("hidden");
    }

    function showOrderSelectionForDelivery() {
        const orderInfoDiv = document.getElementById("deliveryOrderInfo");
        orderInfoDiv.innerHTML = `
        <h4 class="text-blue-300 font-semibold mb-2">Select Purchase Order</h4>
        <div class="mb-3">
            <label class="block text-gray-300 font-medium mb-2">Purchase Order *</label>
            <select id="order_selection" name="order_id" required
                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                <option value="">-- Select Purchase Order --</option>
            </select>
        </div>
        <div id="selectedOrderInfo" class="hidden mt-3 p-3 bg-blue-900 bg-opacity-20 rounded-lg">
            <h5 class="text-blue-200 font-semibold mb-2">Selected Order Info</h5>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div><span class="text-gray-400">Supplier:</span> <span id="selected_supplier" class="text-white">-</span></div>
                <div><span class="text-gray-400">Material:</span> <span id="selected_material" class="text-white">-</span></div>
                <div><span class="text-gray-400">Quantity:</span> <span id="selected_quantity" class="text-white">-</span></div>
                <div><span class="text-gray-400">Status:</span> <span id="selected_status" class="text-white">-</span></div>
            </div>
        </div>
    `;
        orderInfoDiv.classList.remove("hidden");

        // Load available orders
        loadAvailableOrdersForDelivery();
    }

    function loadAvailableOrdersForDelivery() {
        showLoading('Loading purchase orders...');

        fetch(`order_ajax.php?action=list&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    populateOrderSelection(data.orders);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error loading orders:', error);
                showToast('Error loading purchase orders', 'error');
            });
    }

    function populateOrderSelection(orders) {
        const orderSelect = document.getElementById("order_selection");
        if (!orderSelect) return;

        // Clear existing options except the first one
        orderSelect.innerHTML = '<option value="">-- Select Purchase Order --</option>';

        // Filter orders that are not yet fully delivered
        const availableOrders = orders.filter(order =>
            order.status === 'Ordered' || order.status === 'Partial Received'
        );

        if (availableOrders.length === 0) {
            orderSelect.innerHTML = '<option value="">No available purchase orders</option>';
            return;
        }

        availableOrders.forEach(order => {
            const option = document.createElement('option');
            option.value = order.order_id;
            option.textContent = `PO-${order.order_id.toString().padStart(4, '0')} - ${order.supplier_name} - ${order.material_type}`;
            option.setAttribute('data-order', JSON.stringify(order));
            orderSelect.appendChild(option);
        });

        // Add event listener for order selection change
        orderSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const orderData = JSON.parse(selectedOption.getAttribute('data-order'));
                showSelectedOrderInfo(orderData);
                document.getElementById("delivery_order_id").value = orderData.order_id;
            } else {
                document.getElementById("selectedOrderInfo").classList.add("hidden");
                document.getElementById("delivery_order_id").value = "";
            }
        });
    }

    function showSelectedOrderInfo(orderData) {
        const infoDiv = document.getElementById("selectedOrderInfo");
        document.getElementById("selected_supplier").textContent = orderData.supplier_name;
        document.getElementById("selected_material").textContent = orderData.material_type;
        document.getElementById("selected_quantity").textContent = orderData.quantity + ' ' + (orderData.unit || '');

        const statusColors = {
            'Ordered': 'text-yellow-400',
            'Partial Received': 'text-blue-400',
            'Received': 'text-green-400'
        };

        document.getElementById("selected_status").innerHTML =
            `<span class="${statusColors[orderData.status] || 'text-gray-400'}">${orderData.status}</span>`;

        infoDiv.classList.remove("hidden");
    }

    function editDelivery(deliveryId) {
        showLoading('Loading delivery data...');

        fetch(`delivery_ajax.php?action=get&id=${deliveryId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    document.getElementById("deliveryModalTitle").textContent = "Edit Delivery";
                    document.getElementById("saveDeliveryButtonText").textContent = "Update Delivery";

                    const delivery = data.delivery;
                    document.getElementById("delivery_id").value = delivery.delivery_id;
                    document.getElementById("delivery_order_id").value = delivery.order_id;
                    document.getElementById("delivery_number").value = delivery.delivery_number;
                    document.getElementById("delivery_date").value = delivery.delivery_date;
                    document.getElementById("carrier_name").value = delivery.carrier_name;
                    document.getElementById("driver_name").value = delivery.driver_name || '';
                    document.getElementById("vehicle_number").value = delivery.vehicle_number || '';
                    document.getElementById("tracking_number").value = delivery.tracking_number || '';
                    document.getElementById("delivery_status").value = delivery.status;
                    document.getElementById("delivery_notes").value = delivery.notes || '';

                    // Set datetime values
                    if (delivery.estimated_arrival) {
                        document.getElementById("estimated_arrival").value = delivery.estimated_arrival.replace(' ', 'T').substring(0, 16);
                    }
                    if (delivery.actual_arrival) {
                        // For display only, not editable
                    }

                    // Show order info
                    loadOrderInfoForDelivery(delivery.order_id);

                    document.getElementById("deliveryModal").classList.remove("hidden");
                } else {
                    showToast('Error loading delivery data', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error("Error:", error);
                showToast('Error loading delivery data', 'error');
            });
    }

    function loadOrderInfoForDelivery(orderId) {
        showLoading('Loading order information...');

        fetch(`order_ajax.php?action=get&id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const order = data.order;
                    const orderInfoDiv = document.getElementById("deliveryOrderInfo");
                    orderInfoDiv.innerHTML = `
                    <h4 class="text-blue-300 font-semibold mb-2">Order Information</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Order:</span>
                            <span class="text-white ml-2">PO-${order.order_id.toString().padStart(4, '0')}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Supplier:</span>
                            <span class="text-white ml-2">${escapeHtml(order.supplier_name)}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Material:</span>
                            <span class="text-white ml-2">${escapeHtml(order.material_type)}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Quantity:</span>
                            <span class="text-white ml-2">${order.quantity} ${order.unit || ''}</span>
                        </div>
                    </div>
                `;
                    orderInfoDiv.classList.remove("hidden");
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error loading order info:', error);
                showToast('Error loading order information', 'error');
            });
    }

    function saveDelivery(event) {
        event.preventDefault();

        // Get the order_id from either hidden field or selection
        let orderId = document.getElementById("delivery_order_id").value;

        // If no orderId from hidden field, try to get from selection
        if (!orderId) {
            const orderSelect = document.getElementById("order_selection");
            if (orderSelect) {
                orderId = orderSelect.value;
            }
        }

        // Validate order_id
        if (!orderId) {
            showToast('Please select a purchase order', 'warning');
            return;
        }

        if (!validateForm(event.target)) {
            showToast('Please fill all required fields', 'warning');
            return;
        }

        const formData = new FormData(event.target);

        // Ensure order_id is included in form data
        if (!formData.has('order_id')) {
            formData.append('order_id', orderId);
        }

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        fetch("delivery_ajax.php?action=save", {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeDeliveryModal();
                    showToast(data.message, 'success');
                    refreshDeliveriesList();
                    refreshOrdersList(); // Refresh orders to update status
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                showToast("Save failed: " + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function closeDeliveryModal() {
        document.getElementById("deliveryModal").classList.add("hidden");
    }

    function updateDeliveryStatus(deliveryId, currentStatus) {
        const newStatus = prompt(`Update delivery status from "${currentStatus}":\n\n- Scheduled\n- In Transit\n- Delivered\n- Delayed\n- Cancelled`, currentStatus);

        if (newStatus && newStatus !== currentStatus) {
            const formData = new FormData();
            formData.append('delivery_id', deliveryId);
            formData.append('status', newStatus);

            showLoading('Updating delivery status...');

            fetch("delivery_ajax.php?action=update_status", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast(data.message, 'success');
                        refreshDeliveriesList();
                        refreshOrdersList();
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Status update error:', error);
                    showToast("Status update failed: " + error.message, 'error');
                });
        }
    }

    function showReceiveModal(deliveryId) {
        showLoading('Loading delivery information...');

        fetch(`delivery_ajax.php?action=get&id=${deliveryId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const delivery = data.delivery;
                    document.getElementById("receive_delivery_id").value = delivery.delivery_id;
                    document.getElementById("receive_delivery_number").textContent = delivery.delivery_number;
                    document.getElementById("receive_order_info").textContent = `${delivery.supplier_name} - ${delivery.material_type}`;
                    document.getElementById("receive_previous").textContent = delivery.received_quantity || 0;
                    document.getElementById("received_quantity").value = '';
                    document.getElementById("receive_notes").value = '';

                    document.getElementById("receiveModal").classList.remove("hidden");
                } else {
                    showToast('Error loading delivery data', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error("Error:", error);
                showToast('Error loading delivery data', 'error');
            });
    }

    function receiveItems(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Receiving...';
        submitBtn.disabled = true;

        fetch("delivery_ajax.php?action=receive_items", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeReceiveModal();
                    showToast(data.message, 'success');
                    refreshDeliveriesList();
                    refreshOrdersList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Receive error:', error);
                showToast("Receive failed: " + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function closeReceiveModal() {
        document.getElementById("receiveModal").classList.add("hidden");
    }

    function refreshDeliveriesList() {
        if (document.getElementById('content-delivery') &&
            !document.getElementById('content-delivery').classList.contains('hidden')) {
            loadDeliveriesList();
        }
    }

    function loadDeliveriesList() {
        showLoading('Loading deliveries...');

        fetch(`delivery_ajax.php?action=list&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    updateDeliveriesTable(data.deliveries);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error loading deliveries:', error);
                showToast('Error loading deliveries', 'error');
            });
    }

    function updateDeliveriesTable(deliveries) {
        const tbody = document.getElementById('deliveriesTableBody');
        if (!tbody) return;

        if (deliveries.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-truck text-4xl mb-3 opacity-50"></i>
                    <p>No deliveries found</p>
                    <button onclick="showAddDeliveryModal()" class="text-blue-400 hover:text-blue-300 mt-2">
                        <i class="fas fa-plus-circle mr-1"></i>Schedule first delivery
                    </button>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';
        deliveries.forEach((delivery, index) => {
            const statusColors = {
                'Scheduled': 'bg-blue-500',
                'In Transit': 'bg-yellow-500',
                'Delivered': 'bg-green-500',
                'Delayed': 'bg-orange-500',
                'Cancelled': 'bg-red-500'
            };

            const progressPercentage = delivery.order_quantity > 0 ?
                Math.round((delivery.received_quantity / delivery.order_quantity) * 100) : 0;

            html += `
            <tr class="hover:bg-gray-800 transition">
                <td class="px-4 py-3">
                    <div class="text-white font-semibold">${delivery.delivery_number}</div>
                    <div class="text-gray-400 text-xs">${delivery.tracking_number || 'No tracking'}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-white font-semibold">PO-${delivery.order_id.toString().padStart(4, '0')}</div>
                    <div class="text-gray-400 text-sm">${delivery.supplier_name}</div>
                    <div class="text-gray-400 text-xs">${delivery.material_type}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-white">${delivery.carrier_name}</div>
                    <div class="text-gray-400 text-sm">${delivery.driver_name || 'No driver'}</div>
                    <div class="text-gray-400 text-xs">${delivery.vehicle_number || 'No vehicle'}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-white text-sm">${formatDateDisplay(delivery.delivery_date)}</div>
                    <div class="text-gray-400 text-xs">
                        ${delivery.estimated_arrival ? 'ETA: ' + formatDateTimeDisplay(delivery.estimated_arrival) : 'No ETA'}
                    </div>
                    ${delivery.actual_arrival ? `
                    <div class="text-green-400 text-xs">
                        Delivered: ${formatDateTimeDisplay(delivery.actual_arrival)}
                    </div>
                    ` : ''}
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex flex-col items-center space-y-2">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColors[delivery.status]} text-white">
                            ${delivery.status}
                        </span>
                        ${delivery.status === 'Delivered' ? `
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: ${progressPercentage}%"></div>
                        </div>
                        <div class="text-xs text-gray-400">
                            ${delivery.received_quantity || 0} / ${delivery.order_quantity} (${progressPercentage}%)
                        </div>
                        ` : ''}
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center space-x-2">
                        ${delivery.status !== 'Delivered' ? `
                        <button onclick="showReceiveModal(${delivery.delivery_id})" 
                                class="text-green-400 hover:text-green-300" title="Receive Items">
                            <i class="fas fa-check-circle"></i>
                        </button>
                        ` : ''}
                        <button onclick="editDelivery(${delivery.delivery_id})" 
                                class="text-blue-400 hover:text-blue-300" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="updateDeliveryStatus(${delivery.delivery_id}, '${delivery.status}')" 
                                class="text-yellow-400 hover:text-yellow-300" title="Update Status">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button onclick="deleteDelivery(${delivery.delivery_id})" 
                                class="text-red-400 hover:text-red-300" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
    }

    function deleteDelivery(deliveryId) {
        if (!confirm("Are you sure you want to delete this delivery?")) {
            return;
        }

        showLoading('Deleting delivery...');

        fetch(`delivery_ajax.php?action=delete&id=${deliveryId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshDeliveriesList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Delete error:', error);
                showToast("Delete failed: " + error.message, 'error');
            });
    }

    // Auto-suggest suppliers
    function initSupplierAutocomplete() {
        const supplierInput = document.getElementById('supplier_name');
        if (supplierInput) {
            let timeoutId;

            supplierInput.addEventListener('input', function(e) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    const searchTerm = e.target.value;
                    if (searchTerm.length >= 2) {
                        searchSuppliers(searchTerm);
                    }
                }, 300);
            });
        }
    }

    function searchSuppliers(searchTerm) {
        if (!searchTerm || searchTerm.length < 2) {
            removeSupplierSuggestions();
            return;
        }

        fetch(`supplier_ajax.php?action=search&q=${encodeURIComponent(searchTerm)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSupplierSuggestions(data.suppliers);
                } else {
                    removeSupplierSuggestions();
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                removeSupplierSuggestions();
            });
    }

    function showSupplierSuggestions(suppliers) {
        // Remove existing suggestions dropdown
        removeSupplierSuggestions();

        if (!suppliers || suppliers.length === 0) {
            return;
        }

        const supplierInput = document.getElementById('supplier_name');
        if (!supplierInput) return;

        // Create suggestions dropdown
        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.id = 'supplier-suggestions';
        suggestionsDiv.className = 'absolute z-50 w-full mt-1 bg-gray-800 border border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto';

        suppliers.forEach(supplier => {
            const suggestionItem = document.createElement('div');
            suggestionItem.className = 'px-4 py-3 hover:bg-gray-700 cursor-pointer border-b border-gray-700 last:border-b-0';
            suggestionItem.innerHTML = `
            <div class="text-white font-semibold">${escapeHtml(supplier.supplier_name)}</div>
            <div class="text-gray-400 text-sm">${escapeHtml(supplier.contact_person)} • ${escapeHtml(supplier.phone)}</div>
            <div class="text-gray-400 text-xs">${escapeHtml(supplier.email)}</div>
        `;

            suggestionItem.addEventListener('click', function() {
                selectSupplier(supplier);
                removeSupplierSuggestions();
            });

            suggestionsDiv.appendChild(suggestionItem);
        });

        // Position the dropdown below the input field
        const inputRect = supplierInput.getBoundingClientRect();
        suggestionsDiv.style.width = inputRect.width + 'px';
        suggestionsDiv.style.top = (inputRect.bottom + window.scrollY) + 'px';
        suggestionsDiv.style.left = inputRect.left + 'px';

        document.body.appendChild(suggestionsDiv);

        // Close suggestions when clicking outside
        setTimeout(() => {
            document.addEventListener('click', closeSupplierSuggestionsOnClickOutside);
        }, 100);
    }

    function removeSupplierSuggestions() {
        const existingSuggestions = document.getElementById('supplier-suggestions');
        if (existingSuggestions) {
            existingSuggestions.remove();
        }
        document.removeEventListener('click', closeSupplierSuggestionsOnClickOutside);
    }

    function closeSupplierSuggestionsOnClickOutside(event) {
        const supplierInput = document.getElementById('supplier_name');
        const suggestionsDiv = document.getElementById('supplier-suggestions');

        if (supplierInput && suggestionsDiv &&
            !supplierInput.contains(event.target) &&
            !suggestionsDiv.contains(event.target)) {
            removeSupplierSuggestions();
        }
    }

    function selectSupplier(supplier) {
        const supplierInput = document.getElementById('supplier_name');
        if (supplierInput) {
            supplierInput.value = supplier.supplier_name;

            // Optional: You can also auto-fill other fields if needed
            // document.getElementById('contact_person').value = supplier.contact_person || '';
            // document.getElementById('phone').value = supplier.phone || '';
            // document.getElementById('email').value = supplier.email || '';
        }
    }

    function initSupplierAutocomplete() {
        const supplierInput = document.getElementById('supplier_name');
        if (supplierInput) {
            let timeoutId;

            supplierInput.addEventListener('input', function(e) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    const searchTerm = e.target.value.trim();
                    if (searchTerm.length >= 2) {
                        searchSuppliers(searchTerm);
                    } else {
                        removeSupplierSuggestions();
                    }
                }, 300);
            });

            // Also remove suggestions when input loses focus
            supplierInput.addEventListener('blur', function() {
                setTimeout(removeSupplierSuggestions, 200);
            });

            // Prevent form submit when selecting with Enter
            supplierInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const suggestions = document.getElementById('supplier-suggestions');
                    if (suggestions && suggestions.children.length > 0) {
                        e.preventDefault();
                        suggestions.children[0].click(); // Select first suggestion
                    }
                }
            });
        }
    }

    // Function to create delivery from specific order (called from orders table)
    function createDeliveryForOrder(orderId) {
        showAddDeliveryModal(orderId);
    }

    // Update orders table to include "Create Delivery" button
    function updateOrdersTableWithDelivery(orders) {
        // This function should be called when rendering orders table
        // Add a "Create Delivery" button for orders that can have deliveries
    }

    // Update the order action buttons in your existing orders table
    function getOrderActionButtons(order) {
        const canCreateDelivery = order.status === 'Ordered' || order.status === 'Partial Received';

        return `
        <div class="flex items-center justify-center space-x-2">
            <button onclick="viewOrder(${order.order_id})" 
                    class="text-green-400 hover:text-green-300" title="View">
                <i class="fas fa-eye"></i>
            </button>
            ${canManagePurchasing() ? `
            <button onclick="editOrder(${order.order_id})" 
                    class="text-blue-400 hover:text-blue-300" title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            ${canCreateDelivery ? `
            <button onclick="createDeliveryForOrder(${order.order_id})" 
                    class="text-purple-400 hover:text-purple-300" title="Create Delivery">
                <i class="fas fa-truck"></i>
            </button>
            ` : ''}
            <button onclick="updateOrderStatus(${order.order_id})" 
                    class="text-yellow-400 hover:text-yellow-300" title="Update Status">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button onclick="deleteOrder(${order.order_id})" 
                    class="text-red-400 hover:text-red-300" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
            ` : ''}
        </div>
    `;
    }

    // ==============================================
    // DELIVERY NOTES MANAGEMENT FUNCTIONS
    // ==============================================

    /**
     * Load Delivery Notes List - NEW FUNCTION
     */
    function loadDeliveryNotesList() {
        showLoading('Loading delivery notes...');

        fetch(`delivery_ajax.php?action=list&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    updateDeliveryNotesTable(data.deliveries);
                    updateDeliveryNotesStatistics(data.deliveries);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error loading delivery notes:', error);
                showToast('Error loading delivery notes', 'error');
            });
    }

    /**
     * Update Delivery Notes Table
     */
    function updateDeliveryNotesTable(deliveries) {
        const tbody = document.getElementById('deliveryNotesTableBody');
        if (!tbody) return;

        if (deliveries.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-4xl mb-3 opacity-50"></i>
                    <p>No delivery notes found</p>
                    <button onclick="showAddDeliveryModal()" class="text-blue-400 hover:text-blue-300 mt-2">
                        <i class="fas fa-plus-circle mr-1"></i>Create first delivery
                    </button>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';
        deliveries.forEach((delivery) => {
            const statusColors = {
                'Scheduled': 'bg-blue-500',
                'In Transit': 'bg-yellow-500',
                'Delivered': 'bg-green-500',
                'Delayed': 'bg-orange-500',
                'Cancelled': 'bg-red-500'
            };

            html += `
            <tr class="hover:bg-gray-700 transition">
                <td class="px-4 py-3">
                    <div class="text-white font-semibold">${delivery.delivery_number}</div>
                    <div class="text-gray-400 text-xs">${delivery.carrier_name}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-white">PO-${delivery.order_id.toString().padStart(4, '0')}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-gray-300">${escapeHtml(delivery.supplier_name)}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-gray-300">${escapeHtml(delivery.material_type)}</div>
                </td>
                <td class="px-4 py-3 text-center text-gray-300">
                    ${formatDateDisplay(delivery.delivery_date)}
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="text-white font-semibold">${delivery.received_quantity || 0}</div>
                    <div class="text-gray-400 text-xs">/ ${delivery.order_quantity}</div>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColors[delivery.status]} text-white">
                        ${delivery.status}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center space-x-2">
                        <button onclick="viewDeliveryNote(${delivery.delivery_id})" 
                                class="text-blue-400 hover:text-blue-300" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="downloadDeliveryNote(${delivery.delivery_id})" 
                                class="text-green-400 hover:text-green-300" title="Download POD">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
    }

    /**
     * Update Delivery Notes Statistics
     */
    function updateDeliveryNotesStatistics(deliveries) {
        const totalNotes = deliveries.length;
        const completedNotes = deliveries.filter(d => d.status === 'Delivered').length;
        const inTransitNotes = deliveries.filter(d => d.status === 'In Transit').length;

        // This month deliveries
        const now = new Date();
        const thisMonthNotes = deliveries.filter(d => {
            const deliveryDate = new Date(d.delivery_date);
            return deliveryDate.getMonth() === now.getMonth() &&
                deliveryDate.getFullYear() === now.getFullYear();
        }).length;

        document.getElementById('totalDeliveryNotes').textContent = totalNotes;
        document.getElementById('completedDeliveryNotes').textContent = completedNotes;
        document.getElementById('pendingDeliveryNotes').textContent = inTransitNotes;
        document.getElementById('thisMonthDeliveries').textContent = thisMonthNotes;
    }

    /**
     * View Delivery Note Details
     */
    function viewDeliveryNote(deliveryId) {
        showLoading('Loading delivery note...');

        fetch(`delivery_ajax.php?action=get&id=${deliveryId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showDeliveryNoteModal(data.delivery);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Error loading delivery note', 'error');
            });
    }

    /**
     * Show Delivery Note Modal
     */
    function showDeliveryNoteModal(delivery) {
        const modalHTML = `
        <div id="deliveryNoteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-clipboard-list text-blue-400 mr-2"></i>
                        Delivery Note Details
                    </h3>
                    <button onclick="closeDeliveryNoteModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Delivery Information -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="bg-gray-900 p-4 rounded-lg">
                        <h4 class="text-blue-300 font-semibold mb-3">Delivery Information</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Delivery Number:</span>
                                <span class="text-white font-semibold">${delivery.delivery_number}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Delivery Date:</span>
                                <span class="text-white">${formatDateDisplay(delivery.delivery_date)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Status:</span>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold ${delivery.status === 'Delivered' ? 'bg-green-500' : 'bg-yellow-500'} text-white">
                                    ${delivery.status}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-900 p-4 rounded-lg">
                        <h4 class="text-green-300 font-semibold mb-3">Order Information</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Order Reference:</span>
                                <span class="text-white font-semibold">PO-${delivery.order_id.toString().padStart(4, '0')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Supplier:</span>
                                <span class="text-white">${escapeHtml(delivery.supplier_name)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Material:</span>
                                <span class="text-white">${escapeHtml(delivery.material_type)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carrier & Tracking -->
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-purple-300 font-semibold mb-3">Carrier & Tracking</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Carrier:</span>
                            <span class="text-white ml-2">${escapeHtml(delivery.carrier_name)}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Tracking Number:</span>
                            <span class="text-white ml-2">${delivery.tracking_number || '-'}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Driver:</span>
                            <span class="text-white ml-2">${delivery.driver_name || '-'}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Vehicle:</span>
                            <span class="text-white ml-2">${delivery.vehicle_number || '-'}</span>
                        </div>
                    </div>
                </div>

                <!-- Quantity Information -->
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-yellow-300 font-semibold mb-3">Quantity Information</h4>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-gray-400 text-sm mb-1">Ordered</div>
                            <div class="text-2xl font-bold text-white">${delivery.order_quantity}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-sm mb-1">Received</div>
                            <div class="text-2xl font-bold text-green-400">${delivery.received_quantity || 0}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-sm mb-1">Completion</div>
                            <div class="text-2xl font-bold text-blue-400">
                                ${delivery.order_quantity > 0 ? Math.round((delivery.received_quantity / delivery.order_quantity) * 100) : 0}%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                ${delivery.notes ? `
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-gray-300 font-semibold mb-3">Delivery Notes</h4>
                    <p class="text-gray-400 text-sm whitespace-pre-wrap">${escapeHtml(delivery.notes)}</p>
                </div>
                ` : ''}

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-3">
                    <button onclick="downloadDeliveryNote(${delivery.delivery_id})" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-download"></i>
                        <span>Download POD</span>
                    </button>
                    <button onclick="closeDeliveryNoteModal()" 
                            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('deliveryNoteModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Insert modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeDeliveryNoteModal() {
        const modal = document.getElementById('deliveryNoteModal');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * Download Delivery Note (Proof of Delivery)
     */
    function downloadDeliveryNote(deliveryId) {
        showToast('📄 Downloading delivery note...', 'info');

        // Simple download implementation
        // Untuk production, bisa generate PDF server-side
        window.open(`delivery_ajax.php?action=download_pod&id=${deliveryId}`, '_blank');
    }

    // ==============================================
    // ORDER MANAGEMENT FUNCTIONS 
    // ==============================================

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

    function editOrder(orderId) {
        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to edit purchase orders");
            return;
        <?php endif; ?>

        showLoading('Loading order data...');

        // Fetch order data via AJAX
        fetch(`order_ajax.php?action=get&id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showEditOrderModal(data.order);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error("Error:", error);
                showToast("Error loading order data: " + error.message, 'error');
            });
    }

    /**
     * Show Edit Order Modal - REVISED VERSION
     * REPLACE function showEditOrderModal() yang lama
     */
    function showEditOrderModal(order) {
        console.log('📝 Editing order:', order);

        document.getElementById("orderModalTitle").textContent = "Edit Purchase Order";
        document.getElementById("saveOrderButtonText").textContent = "Update PO";

        // Set order_id untuk update
        document.getElementById("order_id").value = order.order_id;

        // Supplier name (dengan autocomplete)
        document.getElementById("supplier_name").value = order.supplier_name || '';

        // Quantity & Unit
        document.getElementById("order_quantity").value = order.quantity || '';
        document.getElementById("order_unit").value = order.unit || 'pcs';

        // Dates
        document.getElementById("order_date").value = order.order_date || '';
        document.getElementById("expected_receiving_date").value = order.expected_receiving_date || '';

        // Specifications & Notes
        document.getElementById("specifications").value = order.specifications || '';
        document.getElementById("notes").value = order.notes || '';

        // ========================================
        // MATERIAL SELECTION HANDLING
        // ========================================

        // Show order info section
        const orderInfoDiv = document.getElementById("orderInfoDisplay");
        if (orderInfoDiv) {
            orderInfoDiv.classList.remove("hidden");
        }

        if (order.material_id) {
            // Material dari material_lists
            console.log('✅ Material from list:', order.material_id);

            document.getElementById("material_id").value = order.material_id;

            // Hide custom material fields
            document.getElementById("customMaterialFields").classList.add("hidden");

            // Show material info
            const materialInfo = document.getElementById("materialInfo");
            if (materialInfo) {
                materialInfo.classList.remove("hidden");

                // Update material info display
                document.getElementById("info_assy").textContent = order.assy_marking || '-';
                document.getElementById("info_name").textContent = order.material_name || order.item_name || '-';
                document.getElementById("info_quantity").textContent = order.quantity || '-';
                document.getElementById("info_dimensions").textContent = order.dimensions || '-';
            }
        } else {
            // Custom material (tidak ada di material_lists)
            console.log('🔧 Custom material');

            document.getElementById("material_id").value = "custom";

            // Show custom material fields
            document.getElementById("customMaterialFields").classList.remove("hidden");
            document.getElementById("material_type").value = order.material_type || '';
            document.getElementById("custom_material_name").value = order.item_name || order.material_type || '';

            // Hide material info
            const materialInfo = document.getElementById("materialInfo");
            if (materialInfo) {
                materialInfo.classList.add("hidden");
            }
        }

        // Show modal
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

    /**
     * Enhanced Save Order - Handle both create & update
     * REPLACE function saveOrder() yang lama
     */
    function saveOrder(event) {
        event.preventDefault();

        <?php if (!canManagePurchasing()): ?>
            alert("You don't have permission to save purchase orders");
            return;
        <?php endif; ?>

        // Validate form
        if (!validateForm(event.target)) {
            showToast('Please fill all required fields', 'warning');
            return;
        }

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        const orderId = document.getElementById("order_id").value;
        const actionText = orderId ? 'Updating' : 'Creating';

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + actionText + '...';
        submitBtn.disabled = true;

        fetch("order_ajax.php?action=save", {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeOrderModal();
                    showToast(data.message, 'success');

                    // Refresh orders list
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                showToast("Save failed: " + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    /**
     * View Order Details - Enhanced Version
     * REPLACE function viewOrder() yang lama
     */
    function viewOrder(orderId) {
        showLoading('Loading order details...');

        fetch(`order_ajax.php?action=get&id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showOrderDetailModal(data.order);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Error loading order details', 'error');
            });
    }

    /**
     * Show Order Detail Modal - NEW ENHANCED VERSION
     */
    function showOrderDetailModal(order) {
        const statusColors = {
            'Ordered': 'bg-yellow-500',
            'Partial Received': 'bg-blue-500',
            'Received': 'bg-green-500',
            'Cancelled': 'bg-red-500'
        };

        const modalHTML = `
        <div id="orderDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-file-invoice text-green-400 mr-2"></i>
                        Purchase Order Details
                    </h3>
                    <button onclick="closeOrderDetailModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- PO Header -->
                <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 border border-green-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-gray-400 text-sm">Purchase Order Number</div>
                            <div class="text-2xl font-bold text-white font-mono">PO-${order.order_id.toString().padStart(4, '0')}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-gray-400 text-sm">Status</div>
                            <span class="px-4 py-2 rounded-full text-sm font-bold ${statusColors[order.status]} text-white">
                                ${order.status}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <!-- Material Info -->
                    <div class="bg-gray-900 p-4 rounded-lg">
                        <h4 class="text-blue-300 font-semibold mb-3 flex items-center">
                            <i class="fas fa-box mr-2"></i>Material Information
                        </h4>
                        <div class="space-y-2 text-sm">
                            ${order.assy_marking && order.assy_marking !== '-' ? `
                            <div class="flex justify-between">
                                <span class="text-gray-400">Assy Marking:</span>
                                <span class="text-blue-300 font-mono font-semibold">${escapeHtml(order.assy_marking)}</span>
                            </div>
                            ` : ''}
                            <div class="flex justify-between">
                                <span class="text-gray-400">Item Name:</span>
                                <span class="text-white font-semibold">${escapeHtml(order.item_name || order.material_name || order.material_type)}</span>
                            </div>
                            ${order.dimensions ? `
                            <div class="flex justify-between">
                                <span class="text-gray-400">Dimensions:</span>
                                <span class="text-gray-300">${escapeHtml(order.dimensions)}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Supplier Info -->
                    <div class="bg-gray-900 p-4 rounded-lg">
                        <h4 class="text-green-300 font-semibold mb-3 flex items-center">
                            <i class="fas fa-building mr-2"></i>Supplier Information
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Supplier:</span>
                                <span class="text-white font-semibold">${escapeHtml(order.supplier_name)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quantity & Dates -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-gray-900 p-4 rounded-lg text-center">
                        <div class="text-gray-400 text-sm mb-1">Order Quantity</div>
                        <div class="text-2xl font-bold text-white">${order.quantity || 0}</div>
                        <div class="text-gray-400 text-xs">${order.unit || 'pcs'}</div>
                    </div>
                    <div class="bg-gray-900 p-4 rounded-lg text-center">
                        <div class="text-gray-400 text-sm mb-1">Order Date</div>
                        <div class="text-white font-semibold">${formatDateDisplay(order.order_date)}</div>
                    </div>
                    <div class="bg-gray-900 p-4 rounded-lg text-center">
                        <div class="text-gray-400 text-sm mb-1">Expected Delivery</div>
                        <div class="text-white font-semibold">${formatDateDisplay(order.expected_receiving_date)}</div>
                    </div>
                </div>

                <!-- Specifications -->
                ${order.specifications ? `
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-purple-300 font-semibold mb-3 flex items-center">
                        <i class="fas fa-clipboard-list mr-2"></i>Specifications
                    </h4>
                    <p class="text-gray-300 text-sm whitespace-pre-wrap">${escapeHtml(order.specifications)}</p>
                </div>
                ` : ''}

                <!-- Notes -->
                ${order.notes ? `
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-yellow-300 font-semibold mb-3 flex items-center">
                        <i class="fas fa-sticky-note mr-2"></i>Notes
                    </h4>
                    <p class="text-gray-300 text-sm whitespace-pre-wrap">${escapeHtml(order.notes)}</p>
                </div>
                ` : ''}

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-3">
                    ${order.status !== 'Received' && order.status !== 'Cancelled' ? `
                    <button onclick="closeOrderDetailModal(); editOrder(${order.order_id})" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit Order</span>
                    </button>
                    <button onclick="closeOrderDetailModal(); createDeliveryForOrder(${order.order_id})" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-truck"></i>
                        <span>Create Delivery</span>
                    </button>
                    ` : ''}
                    <button onclick="closeOrderDetailModal()" 
                            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('orderDetailModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Insert modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeOrderDetailModal() {
        const modal = document.getElementById('orderDetailModal');
        if (modal) {
            modal.remove();
        }
    }

    // Refresh orders list
    function refreshOrdersList() {
        location.reload();
    }

    /**
     * Track Delivery - Switch ke tab delivery dan highlight
     */
    function showDeliveryTracking() {
        console.log('📦 Opening delivery tracking...');

        // Switch ke tab delivery
        switchTab('delivery');

        // Scroll ke atas
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        // Highlight dengan animation
        const deliverySection = document.getElementById('content-delivery');
        if (deliverySection) {
            deliverySection.classList.add('animate-pulse');
            setTimeout(() => {
                deliverySection.classList.remove('animate-pulse');
            }, 2000);
        }

        // Refresh data
        loadDeliveriesList();

        showToast('📦 Delivery tracking loaded', 'info');
    }

    /**
     * Supplier Management - Switch ke tab suppliers
     */
    function showSupplierManagement() {
        console.log('🏢 Opening supplier management...');

        // Switch ke tab suppliers
        switchTab('suppliers');

        // Scroll ke atas
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        // Highlight dengan animation
        const supplierSection = document.getElementById('content-suppliers');
        if (supplierSection) {
            supplierSection.classList.add('animate-pulse');
            setTimeout(() => {
                supplierSection.classList.remove('animate-pulse');
            }, 2000);
        }

        // Refresh data
        loadSuppliersList();

        showToast('🏢 Supplier management loaded', 'info');
    }

    /**
     * Show Order Reports - Generate report modal
     */
    function showOrderReports() {
        console.log('📊 Generating purchase order reports...');

        showLoading('Generating reports...');

        // Fetch report data
        fetch(`order_ajax.php?action=list&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    displayOrderReportModal(data.orders);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Report error:', error);
                showToast('Failed to generate report', 'error');
            });
    }

    /**
     * Display Order Report Modal
     */
    function displayOrderReportModal(orders) {
        // Calculate statistics
        const totalOrders = orders.length;
        const completedOrders = orders.filter(o => o.status === 'Received').length;
        const pendingOrders = orders.filter(o => o.status === 'Ordered').length;
        const partialOrders = orders.filter(o => o.status === 'Partial Received').length;

        const totalQuantity = orders.reduce((sum, o) => sum + parseFloat(o.quantity || 0), 0);
        const totalValue = orders.length; // Bisa diganti dengan sum of order values jika ada field price

        const modalHTML = `
        <div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-chart-bar text-green-400 mr-2"></i>
                        Purchase Order Reports
                    </h3>
                    <button onclick="closeReportModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Report Statistics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                        <div class="text-2xl font-bold text-blue-400">${totalOrders}</div>
                        <div class="text-blue-300 text-sm">Total Orders</div>
                    </div>
                    <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                        <div class="text-2xl font-bold text-green-400">${completedOrders}</div>
                        <div class="text-green-300 text-sm">Completed</div>
                    </div>
                    <div class="bg-yellow-900 bg-opacity-20 p-4 rounded-lg border border-yellow-700">
                        <div class="text-2xl font-bold text-yellow-400">${pendingOrders}</div>
                        <div class="text-yellow-300 text-sm">Pending</div>
                    </div>
                    <div class="bg-purple-900 bg-opacity-20 p-4 rounded-lg border border-purple-700">
                        <div class="text-2xl font-bold text-purple-400">${partialOrders}</div>
                        <div class="text-purple-300 text-sm">Partial</div>
                    </div>
                </div>

                <!-- Orders Summary Table -->
                <div class="bg-gray-900 rounded-lg p-4 mb-4">
                    <h4 class="text-white font-semibold mb-3">Orders Summary by Status</h4>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800 text-gray-300">
                            <tr>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-center">Count</th>
                                <th class="px-4 py-2 text-center">Percentage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <tr>
                                <td class="px-4 py-2 text-gray-300">Ordered</td>
                                <td class="px-4 py-2 text-center text-white">${pendingOrders}</td>
                                <td class="px-4 py-2 text-center text-yellow-400">${totalOrders > 0 ? Math.round((pendingOrders/totalOrders)*100) : 0}%</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-gray-300">Partial Received</td>
                                <td class="px-4 py-2 text-center text-white">${partialOrders}</td>
                                <td class="px-4 py-2 text-center text-blue-400">${totalOrders > 0 ? Math.round((partialOrders/totalOrders)*100) : 0}%</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-gray-300">Received</td>
                                <td class="px-4 py-2 text-center text-white">${completedOrders}</td>
                                <td class="px-4 py-2 text-center text-green-400">${totalOrders > 0 ? Math.round((completedOrders/totalOrders)*100) : 0}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Export Buttons -->
                <div class="flex items-center justify-end space-x-3">
                    <button onclick="exportReportPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-file-pdf"></i>
                        <span>Export PDF</span>
                    </button>
                    <button onclick="exportReportExcel()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                    </button>
                    <button onclick="closeReportModal()" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('reportModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Insert modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeReportModal() {
        const modal = document.getElementById('reportModal');
        if (modal) {
            modal.remove();
        }
    }

    function exportReportPDF() {
        showToast('📄 PDF export feature coming soon', 'info');
        // Implementasi PDF export bisa pakai library seperti jsPDF
    }

    function exportReportExcel() {
        showToast('📊 Excel export feature coming soon', 'info');
        // Implementasi Excel export bisa pakai library seperti SheetJS
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
        <?php if (!canManageFabrication()): ?>
            showToast("You don't have permission to update fabrication", 'error');
            return;
        <?php endif; ?>

        showLoading('Loading material data...');

        fetch(`fabrication_ajax.php?action=get_fabrication_materials&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const material = data.materials.find(m => m.material_id == materialId);
                    if (material) {
                        showMaterialFabricationEditModal(material);
                    } else {
                        throw new Error('Material not found');
                    }
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error("Error:", error);
                showToast("Error loading material data: " + error.message, 'error');
            });
    }

    function showMaterialFabricationModal() {
        showLoading('Loading materials...');

        fetch(`fabrication_ajax.php?action=get_fabrication_materials&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const pendingMaterials = data.materials.filter(m =>
                        m.fabrication_status === 'Pending' || m.fabrication_status === 'In Progress'
                    );

                    if (pendingMaterials.length === 0) {
                        showToast('✅ All materials have been processed', 'info');
                        return;
                    }

                    // Tampilkan material selection modal
                    showMaterialSelectionModal(pendingMaterials);
                } else {
                    throw new Error(data.message || 'Failed to load materials');
                }
            })
            .catch(error => {
                hideLoading();
                console.error("Error:", error);
                showToast("Error loading materials: " + error.message, 'error');
            });
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

        <?php if (!canManageFabrication()): ?>
            showToast("You don't have permission to update fabrication", 'error');
            return;
        <?php endif; ?>

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
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeMaterialFabricationModal();
                    showToast(data.message, 'success');
                    refreshFabricationData();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                showToast("Update failed: " + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    /**
     * Show Material Fabrication History
     */
    function showMaterialHistory(materialId) {
        showLoading('Loading fabrication history...');

        fetch(`fabrication_ajax.php?action=get_material_history&material_id=${materialId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showHistoryModal(data.history);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Error loading history', 'error');
            });
    }

    function showHistoryModal(history) {
        let historyHTML = '';

        if (history.length === 0) {
            historyHTML = '<div class="text-center py-8 text-gray-500">No history available</div>';
        } else {
            history.forEach((item, index) => {
                const progressChange = (item.progress_to - item.progress_from).toFixed(2);
                const progressColor = progressChange > 0 ? 'text-green-400' : 'text-gray-400';

                historyHTML += `
                <div class="p-4 bg-gray-750 rounded-lg mb-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-400 text-sm">${formatDateTimeDisplay(item.created_at)}</span>
                        <span class="text-white font-semibold">${item.updated_by_name || 'System'}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Status:</span>
                            <span class="text-white ml-2">${item.status_from} → ${item.status_to}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Progress:</span>
                            <span class="${progressColor} ml-2 font-bold">
                                ${item.progress_from}% → ${item.progress_to}% 
                                (${progressChange > 0 ? '+' : ''}${progressChange}%)
                            </span>
                        </div>
                        ${item.fabrication_phase ? `
                        <div>
                            <span class="text-gray-400">Phase:</span>
                            <span class="text-blue-300 ml-2">${item.fabrication_phase}</span>
                        </div>
                        ` : ''}
                        ${item.qc_status ? `
                        <div>
                            <span class="text-gray-400">QC Status:</span>
                            <span class="text-white ml-2">${item.qc_status}</span>
                        </div>
                        ` : ''}
                    </div>
                    ${item.notes ? `
                    <div class="mt-2 text-gray-300 text-sm">
                        <i class="fas fa-sticky-note text-yellow-400 mr-1"></i>
                        ${escapeHtml(item.notes)}
                    </div>
                    ` : ''}
                </div>
            `;
            });
        }

        const modalHTML = `
        <div id="historyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-3xl max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-history text-orange-400 mr-2"></i>
                        Fabrication History
                    </h3>
                    <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="historyContent">
                    ${historyHTML}
                </div>
                <div class="flex justify-end mt-6">
                    <button onclick="closeHistoryModal()" 
                            class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('historyModal');
        if (existingModal) {
            existingModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeHistoryModal() {
        const modal = document.getElementById('historyModal');
        if (modal) {
            modal.remove();
        }
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

    // TAMBAHKAN FUNGSI BARU INI
    function showMaterialSelectionModal(materials) {
        let materialOptions = '';

        materials.forEach((material, index) => {
            const statusBadge = material.fabrication_status === 'Pending' ?
                '<span class="px-2 py-1 bg-gray-500 text-white text-xs rounded">Not Started</span>' :
                '<span class="px-2 py-1 bg-orange-500 text-white text-xs rounded">In Progress (' + material.fabrication_progress + '%)</span>';

            materialOptions += `
            <div class="material-select-item p-4 bg-gray-750 rounded-lg mb-3 cursor-pointer hover:bg-gray-700 transition border-2 border-transparent hover:border-orange-500"
                 onclick="selectMaterialForFabrication(${material.material_id})">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl font-bold text-orange-400">${index + 1}</span>
                            <div>
                                <h4 class="text-white font-semibold text-lg">${escapeHtml(material.material_name)}</h4>
                                <div class="flex items-center space-x-3 mt-1">
                                    <span class="text-gray-400 text-sm">Assy: <span class="text-orange-300 font-mono">${material.assy_marking || 'N/A'}</span></span>
                                    <span class="text-gray-400 text-sm">Qty: <span class="text-white">${material.quantity}</span></span>
                                    ${material.total_weight_kg ? `<span class="text-gray-400 text-sm">Weight: <span class="text-white">${parseFloat(material.total_weight_kg).toFixed(2)} kg</span></span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        ${statusBadge}
                    </div>
                </div>
            </div>
        `;
        });

        const modalHTML = `
        <div id="materialSelectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-3xl max-h-[80vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-tools text-orange-400 mr-2"></i>
                        Select Material for Fabrication
                    </h3>
                    <button onclick="closeMaterialSelectionModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-gray-400 text-sm">
                        <i class="fas fa-info-circle text-blue-400 mr-1"></i>
                        Select a material to start or update its fabrication progress
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto">
                    ${materialOptions}
                </div>

                <div class="flex justify-end mt-6 pt-4 border-t border-gray-700">
                    <button onclick="closeMaterialSelectionModal()"
                            class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('materialSelectionModal');
        if (existingModal) {
            existingModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeMaterialSelectionModal() {
        const modal = document.getElementById('materialSelectionModal');
        if (modal) {
            modal.remove();
        }
    }

    function selectMaterialForFabrication(materialId) {
        closeMaterialSelectionModal();

        showLoading('Loading material data...');

        // Load material detail
        fetch(`fabrication_ajax.php?action=get_fabrication_materials&pon_id=<?php echo $pon_id; ?>`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const material = data.materials.find(m => m.material_id == materialId);
                    if (material) {
                        showMaterialFabricationEditModal(material);
                    } else {
                        throw new Error('Material not found');
                    }
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Error loading material data', 'error');
            });
    }

    function showMaterialFabricationEditModal(material) {
        // Populate modal dengan data material
        document.getElementById("fabrication_material_id").value = material.material_id;
        document.getElementById("fabrication_material_name").textContent = material.material_name;
        document.getElementById("fabrication_assy_marking").textContent = material.assy_marking || 'N/A';

        // Set progress
        const currentProgress = parseFloat(material.fabrication_progress) || 0;
        document.getElementById("fabrication_progress").value = currentProgress;
        updateFabricationProgressValue(currentProgress);

        // Set status
        document.getElementById("fabrication_status").value = material.fabrication_status || 'Pending';

        // Set fabrication phase berdasarkan progress
        const phase = material.fabrication_phase || getFabricationPhaseByProgress(currentProgress);
        document.getElementById("fabrication_phase").value = phase;

        // Set QC status
        document.getElementById("qc_status").value = material.qc_status || 'Pending';

        // Set workstation & shift jika ada
        if (material.workstation) {
            document.getElementById("workstation").value = material.workstation;
        }
        if (material.shift) {
            document.getElementById("shift").value = material.shift;
        }

        // Clear notes
        document.getElementById("fabrication_notes").value = '';

        // Show modal
        document.getElementById("materialFabricationModal").classList.remove("hidden");
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

    // Auto-refresh production data every 30 seconds
    setInterval(() => {
        if (currentDivision === 'Fabrikasi') {
            refreshProductionData();
        }
    }, 30000);

    // ==============================================
    // LOGISTIK DIVISION - JAVASCRIPT FUNCTIONS
    // ==============================================

    // ==============================================
    // SHIPPING STATUS MANAGEMENT
    // ==============================================

    function showAddShipmentModal() {
        // Redirect ke Purchasing untuk create delivery dari order
        if (confirm('Create new shipment from Purchase Order?\n\nYou will be redirected to Purchasing division.')) {
            window.location.href = 'division_tasks.php?pon_id=<?php echo $pon_id; ?>&division=Purchasing#delivery';
        }
    }

    function viewShipmentDetails(deliveryId) {
        showLoading('Loading shipment details...');

        fetch(`delivery_ajax.php?action=get&id=${deliveryId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showShipmentDetailsModal(data.delivery);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Error loading shipment details', 'error');
            });
    }

    function showShipmentDetailsModal(delivery) {
        const statusColors = {
            'Scheduled': 'bg-blue-500',
            'In Transit': 'bg-yellow-500',
            'Delivered': 'bg-green-500',
            'Delayed': 'bg-orange-500',
            'Cancelled': 'bg-red-500'
        };

        const modalHTML = `
        <div id="shipmentDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-shipping-fast text-purple-400 mr-2"></i>
                        Shipment Details
                    </h3>
                    <button onclick="closeShipmentDetailsModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Shipment Header -->
                <div class="bg-purple-900 bg-opacity-20 p-4 rounded-lg mb-6 border border-purple-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-gray-400 text-sm">Delivery Number</div>
                            <div class="text-2xl font-bold text-white font-mono">${delivery.delivery_number}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-gray-400 text-sm">Status</div>
                            <span class="px-4 py-2 rounded-full text-sm font-bold ${statusColors[delivery.status]} text-white">
                                ${delivery.status}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Shipment Information Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Order Information -->
                    <div class="bg-gray-900 p-4 rounded-lg">
                        <h4 class="text-blue-300 font-semibold mb-3 flex items-center">
                            <i class="fas fa-file-invoice mr-2"></i>Order Information
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Order:</span>
                                <span class="text-white font-mono font-semibold">PO-${delivery.order_id.toString().padStart(4, '0')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Supplier:</span>
                                <span class="text-white">${escapeHtml(delivery.supplier_name)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Material:</span>
                                <span class="text-white">${escapeHtml(delivery.material_type)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Quantity:</span>
                                <span class="text-white font-semibold">${delivery.order_quantity} ${delivery.unit || 'pcs'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Carrier Information -->
                    <div class="bg-gray-900 p-4 rounded-lg">
                        <h4 class="text-green-300 font-semibold mb-3 flex items-center">
                            <i class="fas fa-truck mr-2"></i>Carrier Information
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Carrier:</span>
                                <span class="text-white font-semibold">${escapeHtml(delivery.carrier_name)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Driver:</span>
                                <span class="text-white">${delivery.driver_name || '-'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Vehicle:</span>
                                <span class="text-white">${delivery.vehicle_number || '-'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Tracking #:</span>
                                <span class="text-white font-mono">${delivery.tracking_number || '-'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schedule Information -->
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-yellow-300 font-semibold mb-3 flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i>Schedule & Timeline
                    </h4>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-gray-400 text-sm mb-1">Delivery Date</div>
                            <div class="text-white font-semibold">${formatDateDisplay(delivery.delivery_date)}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-sm mb-1">Estimated Arrival</div>
                            <div class="text-white font-semibold">${delivery.estimated_arrival ? formatDateTimeDisplay(delivery.estimated_arrival) : '-'}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-sm mb-1">Actual Arrival</div>
                            <div class="text-green-400 font-semibold">${delivery.actual_arrival ? formatDateTimeDisplay(delivery.actual_arrival) : '-'}</div>
                        </div>
                    </div>
                </div>

                <!-- Received Quantity -->
                ${delivery.status === 'Delivered' ? `
                <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 border border-green-700">
                    <h4 class="text-green-300 font-semibold mb-3 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>Received Information
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <div class="text-gray-400 text-sm">Received Quantity</div>
                            <div class="text-2xl font-bold text-green-400">${delivery.received_quantity || 0}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-400 text-sm">Completion</div>
                            <div class="text-2xl font-bold text-green-400">
                                ${delivery.order_quantity > 0 ? Math.round((delivery.received_quantity / delivery.order_quantity) * 100) : 0}%
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Notes -->
                ${delivery.notes ? `
                <div class="bg-gray-900 p-4 rounded-lg mb-6">
                    <h4 class="text-gray-300 font-semibold mb-3 flex items-center">
                        <i class="fas fa-sticky-note mr-2"></i>Shipment Notes
                    </h4>
                    <p class="text-gray-400 text-sm whitespace-pre-wrap">${escapeHtml(delivery.notes)}</p>
                </div>
                ` : ''}

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-3">
                    ${delivery.status !== 'Delivered' && delivery.status !== 'Cancelled' ? `
                    <button onclick="closeShipmentDetailsModal(); updateShipmentStatus(${delivery.delivery_id})" 
                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Update Status</span>
                    </button>
                    <button onclick="closeShipmentDetailsModal(); markAsDelivered(${delivery.delivery_id})" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-check-circle"></i>
                        <span>Mark as Delivered</span>
                    </button>
                    ` : ''}
                    <button onclick="printShippingLabel(${delivery.delivery_id})" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-print"></i>
                        <span>Print Label</span>
                    </button>
                    <button onclick="closeShipmentDetailsModal()" 
                            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('shipmentDetailsModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Insert modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeShipmentDetailsModal() {
        const modal = document.getElementById('shipmentDetailsModal');
        if (modal) {
            modal.remove();
        }
    }

    function updateShipmentStatus(deliveryId) {
        showLoading('Loading shipment info...');

        fetch(`delivery_ajax.php?action=get&id=${deliveryId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showUpdateStatusModal(data.delivery);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Error loading shipment data', 'error');
            });
    }

    function showUpdateStatusModal(delivery) {
        const modalHTML = `
        <div id="updateStatusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">Update Shipment Status</h3>
                    <button onclick="closeUpdateStatusModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="updateStatusForm" onsubmit="submitStatusUpdate(event)">
                    <input type="hidden" id="update_delivery_id" name="delivery_id" value="${delivery.delivery_id}">

                    <!-- Current Info -->
                    <div class="mb-4 p-4 bg-purple-900 bg-opacity-20 rounded-lg border border-purple-700">
                        <h4 class="text-purple-300 font-semibold mb-2">Shipment Info</h4>
                        <p class="text-white font-semibold">${delivery.delivery_number}</p>
                        <p class="text-gray-300 text-sm">${escapeHtml(delivery.carrier_name)}</p>
                        <div class="mt-2 flex items-center space-x-2">
                            <span class="text-gray-400 text-sm">Current:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-500 text-white">${delivery.status}</span>
                        </div>
                    </div>

                    <!-- Status Selection -->
                    <div class="mb-4">
                        <label class="block text-gray-300 font-medium mb-3">Select New Status</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                                <input type="radio" name="status" value="Scheduled" class="hidden" onchange="updateStatusSelection(this)" ${delivery.status === 'Scheduled' ? 'checked' : ''}>
                                <div class="flex items-center space-x-3 w-full">
                                    <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                        <div class="w-2 h-2 rounded-full ${delivery.status === 'Scheduled' ? 'bg-blue-400' : 'bg-transparent'}"></div>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">Scheduled</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                                <input type="radio" name="status" value="In Transit" class="hidden" onchange="updateStatusSelection(this)" ${delivery.status === 'In Transit' ? 'checked' : ''}>
                                <div class="flex items-center space-x-3 w-full">
                                    <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                        <div class="w-2 h-2 rounded-full ${delivery.status === 'In Transit' ? 'bg-blue-400' : 'bg-transparent'}"></div>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">In Transit</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                                <input type="radio" name="status" value="Delayed" class="hidden" onchange="updateStatusSelection(this)" ${delivery.status === 'Delayed' ? 'checked' : ''}>
                                <div class="flex items-center space-x-3 w-full">
                                    <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                        <div class="w-2 h-2 rounded-full ${delivery.status === 'Delayed' ? 'bg-blue-400' : 'bg-transparent'}"></div>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">Delayed</div>
                                    </div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                                <input type="radio" name="status" value="Cancelled" class="hidden" onchange="updateStatusSelection(this)" ${delivery.status === 'Cancelled' ? 'checked' : ''}>
                                <div class="flex items-center space-x-3 w-full">
                                    <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                        <div class="w-2 h-2 rounded-full ${delivery.status === 'Cancelled' ? 'bg-blue-400' : 'bg-transparent'}"></div>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">Cancelled</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label class="block text-gray-300 font-medium mb-2">Update Notes</label>
                        <textarea id="status_notes" name="notes" rows="3"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
                            placeholder="Reason for status update..."></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeUpdateStatusModal()"
                            class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                            <i class="fas fa-save"></i>
                            <span>Update Status</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;

        // Remove existing modal if any
        const existingModal = document.getElementById('updateStatusModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Insert modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeUpdateStatusModal() {
        const modal = document.getElementById('updateStatusModal');
        if (modal) {
            modal.remove();
        }
    }

    function submitStatusUpdate(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        fetch("delivery_ajax.php?action=update_status", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeUpdateStatusModal();
                    showToast(data.message, 'success');
                    refreshShippingList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                showToast("Update failed: " + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    function markAsDelivered(deliveryId) {
        if (!confirm('Mark this shipment as delivered?\n\nThis will update the order status and notify relevant divisions.')) {
            return;
        }

        showLoading('Marking as delivered...');

        const formData = new FormData();
        formData.append('delivery_id', deliveryId);
        formData.append('status', 'Delivered');
        formData.append('notes', 'Marked as delivered on ' + new Date().toLocaleString());

        fetch("delivery_ajax.php?action=update_status", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('✅ Shipment marked as delivered successfully!', 'success');
                    refreshShippingList();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Failed to mark as delivered: ' + error.message, 'error');
            });
    }

    function printShippingLabel(deliveryId) {
        showToast('📄 Generating shipping label...', 'info');

        // Open print view in new window
        window.open(`print_shipping_label.php?delivery_id=${deliveryId}`, '_blank', 'width=800,height=600');
    }

    function filterShipments(filterType) {
        const rows = document.querySelectorAll('.shipment-row');

        // Update active filter button
        document.querySelectorAll('[id^="filter-"]').forEach(btn => {
            btn.classList.remove('bg-purple-600', 'text-white');
            btn.classList.add('bg-gray-700', 'text-gray-300');
        });

        const activeBtn = document.getElementById('filter-' + filterType.toLowerCase().replace(' ', ''));
        if (activeBtn) {
            activeBtn.classList.add('bg-purple-600', 'text-white');
            activeBtn.classList.remove('bg-gray-700', 'text-gray-300');
        }

        // Filter rows
        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const isOverdue = row.querySelector('.animate-pulse') !== null;

            if (filterType === 'all') {
                row.style.display = '';
            } else if (filterType === 'Overdue') {
                row.style.display = isOverdue ? '' : 'none';
            } else {
                row.style.display = status === filterType ? '' : 'none';
            }
        });
    }

    function refreshShippingList() {
        showToast('🔄 Refreshing shipment data...', 'info');
        location.reload();
    }

    // ==============================================
    // INITIALIZATION
    // ==============================================

    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Page loaded, initializing data...');

        // Initialize supplier autocomplete
        initSupplierAutocomplete();

        // PRE-LOAD data untuk tab yang sering diakses
        // Ini akan mempercepat experience user
        if (currentDivision === 'Purchasing') {
            // Load suppliers data di background
            setTimeout(() => {
                loadSuppliersList();
                console.log('✅ Suppliers data pre-loaded');
            }, 500);

            // Load deliveries data di background
            setTimeout(() => {
                loadDeliveriesList();
                console.log('✅ Deliveries data pre-loaded');
            }, 1000);
        }

        // Load initial data for active tab
        const activeTab = document.querySelector('.tab-content:not(.hidden)');
        if (activeTab) {
            const tabName = activeTab.id.replace('content-', '');
            console.log('📌 Active tab:', tabName);

            switch (tabName) {
                case 'suppliers':
                    loadSuppliersList();
                    break;
                case 'delivery':
                    loadDeliveriesList();
                    break;
                case 'drawings':
                    initDrawingsTab();
                    break;
            }
        }

        console.log('✅ Initialization complete');
    });

    // Handle tab changes dari URL hash (optional, untuk bookmark support)
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash.substring(1); // Remove #
        if (hash) {
            switchTab(hash);
        }
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
        } elseif ($division === 'Logistik') {
            echo getLogistikTabContent($pon_id, $tasks, $config);
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

<!-- Drawing Upload Modal -->
<div id="drawingUploadModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Upload Engineering Drawing</h3>
            <button onclick="closeDrawingUploadModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="drawingUploadForm" onsubmit="uploadDrawing(event)" enctype="multipart/form-data">
            <input type="hidden" name="pon_id" value="<?php echo $pon_id; ?>">
            <input type="hidden" name="task_id" value="<?php echo getEngineeringTaskId($conn, $pon_id); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-gray-300 font-medium mb-2">Drawing Number *</label>
                    <input type="text" id="drawing_number" name="drawing_number" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="EX: DRW-001">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Revision</label>
                    <input type="text" id="drawing_revision" name="revision"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="A, B, C, etc." value="A">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Drawing Name *</label>
                    <input type="text" id="drawing_name" name="drawing_name" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="Main Structure Assembly Drawing">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Status</label>
                    <select id="drawing_status" name="status"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="Draft">Draft</option>
                        <option value="For Review">For Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Upload Date</label>
                    <input type="date" id="upload_date" name="upload_date"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Drawing File *</label>
                    <input type="file" id="drawing_file" name="drawing_file" accept=".pdf,.dwg,.dxf,.PDF,.DWG,.DXF" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-gray-400 text-sm mt-1">Supported formats: PDF, DWG, DXF (Max 20MB)</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Notes</label>
                    <textarea id="drawing_notes" name="notes" rows="3"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="Additional notes about this drawing..."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeDrawingUploadModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-upload"></i>
                    <span>Upload Drawing</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Drawing Status Update Modal -->
<div id="drawingStatusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Update Drawing Status</h3>
            <button onclick="closeDrawingStatusModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="drawingStatusForm" onsubmit="submitDrawingStatusUpdate(event)">
            <input type="hidden" id="status_drawing_id" name="drawing_id" value="">

            <!-- Drawing Info -->
            <div class="mb-6 p-4 bg-blue-900 bg-opacity-20 rounded-lg border border-blue-700">
                <h4 class="text-blue-300 font-semibold mb-2">Drawing Information</h4>
                <p class="text-white font-semibold" id="status_drawing_number">-</p>
                <p class="text-gray-300 text-sm" id="status_drawing_name">-</p>
                <div class="mt-2 flex items-center space-x-2">
                    <span class="text-gray-400 text-sm">Current Status:</span>
                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-500 text-white" id="status_current_status">-</span>
                </div>
            </div>

            <!-- Status Selection -->
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-3">Select New Status</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                        <input type="radio" name="status" value="Draft" class="hidden" onchange="updateStatusSelection(this)">
                        <div class="flex items-center space-x-3 w-full">
                            <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                <div class="w-2 h-2 rounded-full bg-transparent"></div>
                            </div>
                            <div>
                                <div class="text-white font-medium">Draft</div>
                                <div class="text-gray-400 text-xs">Initial version</div>
                            </div>
                        </div>
                    </label>

                    <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                        <input type="radio" name="status" value="For Review" class="hidden" onchange="updateStatusSelection(this)">
                        <div class="flex items-center space-x-3 w-full">
                            <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                <div class="w-2 h-2 rounded-full bg-transparent"></div>
                            </div>
                            <div>
                                <div class="text-white font-medium">For Review</div>
                                <div class="text-gray-400 text-xs">Ready for approval</div>
                            </div>
                        </div>
                    </label>

                    <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                        <input type="radio" name="status" value="Approved" class="hidden" onchange="updateStatusSelection(this)">
                        <div class="flex items-center space-x-3 w-full">
                            <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                <div class="w-2 h-2 rounded-full bg-transparent"></div>
                            </div>
                            <div>
                                <div class="text-white font-medium">Approved</div>
                                <div class="text-gray-400 text-xs">Final approved</div>
                            </div>
                        </div>
                    </label>

                    <label class="flex items-center p-3 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 transition border-2 border-transparent status-option">
                        <input type="radio" name="status" value="Rejected" class="hidden" onchange="updateStatusSelection(this)">
                        <div class="flex items-center space-x-3 w-full">
                            <div class="w-4 h-4 rounded-full border-2 border-gray-400 flex items-center justify-center">
                                <div class="w-2 h-2 rounded-full bg-transparent"></div>
                            </div>
                            <div>
                                <div class="text-white font-medium">Rejected</div>
                                <div class="text-gray-400 text-xs">Needs revision</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Hidden select for form submission -->
                <select id="drawing_status_select" name="status" class="hidden" required>
                    <option value="Draft">Draft</option>
                    <option value="For Review">For Review</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <!-- Notes -->
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">
                    Update Notes
                    <span class="text-gray-400 text-sm font-normal">(optional)</span>
                </label>
                <textarea id="status_notes" name="notes" rows="3"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    placeholder="Add notes about this status update..."></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeDrawingStatusModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Update Status</span>
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
            <h3 class="text-xl font-bold text-white">Update Task Progress</h3> <!-- This will be updated by JS -->
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

<!-- Task Selection Modal -->
<div id="taskSelectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-2xl max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Select Task to Update Progress</h3>
            <button onclick="closeTaskSelectionModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="mb-4">
            <div class="flex items-center space-x-2 text-blue-300">
                <i class="fas fa-info-circle"></i>
                <span class="text-sm">Select a task to update its progress and status</span>
            </div>
        </div>

        <div class="overflow-y-auto max-h-[60vh]">
            <div id="taskSelectionBody">
                <!-- Tasks will be loaded here dynamically -->
            </div>
        </div>

        <div class="flex justify-end mt-6 pt-4 border-t border-gray-700">
            <button onclick="closeTaskSelectionModal()"
                class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Order Modal - REVISED VERSION -->
<!-- REPLACE modal orderModal yang lama dengan ini -->
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

            <!-- ORDER INFO DISPLAY (untuk mode edit) -->
            <div id="orderInfoDisplay" class="hidden mb-6 p-4 bg-green-900 bg-opacity-20 rounded-lg border border-green-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-gray-400 text-sm">Purchase Order Number</div>
                        <div class="text-xl font-bold text-white font-mono" id="displayOrderNumber">-</div>
                    </div>
                    <div class="text-right">
                        <div class="text-gray-400 text-sm">Current Status</div>
                        <span id="displayOrderStatus" class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-500 text-white">-</span>
                    </div>
                </div>
            </div>

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
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 relative"
                        placeholder="Type to search suppliers or enter new supplier"
                        autocomplete="off">
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
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        value="<?php echo date('Y-m-d'); ?>">
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

<!-- Supplier Management Modal -->
<div id="supplierModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white" id="supplierModalTitle">Add New Supplier</h3>
            <button onclick="closeSupplierModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="supplierForm" onsubmit="saveSupplier(event)">
            <input type="hidden" id="supplier_id" name="supplier_id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Supplier Name *</label>
                    <input type="text" id="supplier_name" name="supplier_name" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Company Name">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Contact Person *</label>
                    <input type="text" id="contact_person" name="contact_person" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Full Name">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Phone *</label>
                    <input type="tel" id="phone" name="phone" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="+62 XXX XXXX XXXX">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Email *</label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="email@company.com">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Address</label>
                    <textarea id="address" name="address" rows="2"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Full address"></textarea>
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">City</label>
                    <input type="text" id="city" name="city"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="City">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Country</label>
                    <input type="text" id="country" name="country" value="Indonesia"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Tax Number</label>
                    <input type="text" id="tax_number" name="tax_number"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="NPWP">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Bank Account</label>
                    <input type="text" id="bank_account" name="bank_account"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Bank Name - Account Number">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Payment Terms</label>
                    <input type="text" id="payment_terms" name="payment_terms"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Net 30, COD, etc.">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Notes</label>
                    <textarea id="supplier_notes" name="notes" rows="3"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        placeholder="Additional notes..."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeSupplierModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span id="saveSupplierButtonText">Save Supplier</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delivery Management Modal - UPDATED -->
<div id="deliveryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white" id="deliveryModalTitle">Schedule Delivery</h3>
            <button onclick="closeDeliveryModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="deliveryForm" onsubmit="saveDelivery(event)">
            <input type="hidden" id="delivery_id" name="delivery_id" value="">
            <input type="hidden" id="delivery_order_id" name="order_id" value="">

            <!-- Order Selection Section - Will be dynamically populated -->
            <div id="deliveryOrderInfo" class="mb-6">
                <!-- Content will be filled by JavaScript -->
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-gray-300 font-medium mb-2">Delivery Number *</label>
                    <input type="text" id="delivery_number" name="delivery_number" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="DEL-001">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Delivery Date *</label>
                    <input type="date" id="delivery_date" name="delivery_date" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Carrier Name *</label>
                    <input type="text" id="carrier_name" name="carrier_name" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="Shipping Company">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Driver Name</label>
                    <input type="text" id="driver_name" name="driver_name"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="Driver's Name">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Vehicle Number</label>
                    <input type="text" id="vehicle_number" name="vehicle_number"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="License Plate">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Tracking Number</label>
                    <input type="text" id="tracking_number" name="tracking_number"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="Tracking Code">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Estimated Arrival</label>
                    <input type="datetime-local" id="estimated_arrival" name="estimated_arrival"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-gray-300 font-medium mb-2">Status</label>
                    <select id="delivery_status" name="status"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Transit">In Transit</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Delayed">Delayed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-300 font-medium mb-2">Notes</label>
                    <textarea id="delivery_notes" name="notes" rows="3"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        placeholder="Delivery notes..."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeDeliveryModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span id="saveDeliveryButtonText">Save Delivery</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Receive Items Modal -->
<div id="receiveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Receive Items</h3>
            <button onclick="closeReceiveModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="receiveForm" onsubmit="receiveItems(event)">
            <input type="hidden" id="receive_delivery_id" name="delivery_id" value="">

            <div class="mb-4 p-4 bg-green-900 bg-opacity-20 rounded-lg border border-green-700">
                <h4 class="text-green-300 font-semibold mb-2">Delivery Information</h4>
                <p class="text-white font-semibold" id="receive_delivery_number">-</p>
                <p class="text-gray-300 text-sm" id="receive_order_info">-</p>
                <div class="mt-2 text-sm">
                    <span class="text-gray-400">Already Received:</span>
                    <span id="receive_previous" class="text-white ml-2">0</span>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Received Quantity *</label>
                <input type="number" id="received_quantity" name="received_quantity" required step="0.01" min="0.01"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                    placeholder="0.00">
            </div>

            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">Receive Date</label>
                <input type="date" id="receive_date" name="receive_date"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                    value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">Notes</label>
                <textarea id="receive_notes" name="receive_notes" rows="3"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                    placeholder="Receiving notes..."></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeReceiveModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-check-circle"></i>
                    <span>Receive Items</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Material Fabrication Progress Modal - IMPROVED -->
<div id="materialFabricationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-xl p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-hammer text-orange-400 mr-2"></i>
                Update Material Fabrication
            </h3>
            <button onclick="closeMaterialFabricationModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="materialFabricationForm" onsubmit="updateMaterialFabricationSubmit(event)">
            <input type="hidden" id="fabrication_material_id" name="material_id" value="">

            <!-- Material Info -->
            <div class="mb-6 p-4 bg-orange-900 bg-opacity-20 rounded-lg border border-orange-700">
                <h4 class="text-orange-300 font-semibold mb-3 flex items-center">
                    <i class="fas fa-box mr-2"></i>Material Information
                </h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-400 text-sm">Material Name:</span>
                        <p class="text-white font-semibold" id="fabrication_material_name">-</p>
                    </div>
                    <div>
                        <span class="text-gray-400 text-sm">Assy Marking:</span>
                        <p class="text-orange-300 font-mono" id="fabrication_assy_marking">-</p>
                    </div>
                </div>
            </div>

            <!-- Progress Slider -->
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-3">
                    <i class="fas fa-percentage mr-1"></i>Progress (%) *
                </label>
                <div class="mb-4">
                    <input type="range" id="fabrication_progress" name="progress" min="0" max="100" step="1" value="0" required
                        class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer slider"
                        oninput="updateFabricationProgressValue(this.value)">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>0%</span>
                        <span>25%</span>
                        <span>50%</span>
                        <span>75%</span>
                        <span>100%</span>
                    </div>
                </div>
                <div class="text-center">
                    <span id="fabrication_progress_display" class="text-4xl font-bold text-orange-400">0%</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Fabrication Phase -->
                <div>
                    <label class="block text-gray-300 font-medium mb-2">
                        <i class="fas fa-tasks mr-1"></i>Fabrication Phase *
                    </label>
                    <select id="fabrication_phase" name="fabrication_phase" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                        <option value="Material Preparation">Material Preparation</option>
                        <option value="Cutting & Preparation">Cutting & Preparation</option>
                        <option value="Component Assembly">Component Assembly</option>
                        <option value="Welding & Joining">Welding & Joining</option>
                        <option value="Final Assembly & Finishing">Final Assembly & Finishing</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-gray-300 font-medium mb-2">
                        <i class="fas fa-flag mr-1"></i>Status *
                    </label>
                    <select id="fabrication_status" name="fabrication_status" required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <!-- Workstation -->
                <div>
                    <label class="block text-gray-300 font-medium mb-2">
                        <i class="fas fa-industry mr-1"></i>Workstation
                    </label>
                    <select id="workstation" name="workstation"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                        <option value="Main Workshop">Main Workshop</option>
                        <option value="Workstation A">Workstation A</option>
                        <option value="Workstation B">Workstation B</option>
                        <option value="Workstation C">Workstation C</option>
                        <option value="Welding Area">Welding Area</option>
                        <option value="Assembly Area">Assembly Area</option>
                        <option value="Finishing Area">Finishing Area</option>
                    </select>
                </div>

                <!-- Shift -->
                <div>
                    <label class="block text-gray-300 font-medium mb-2">
                        <i class="fas fa-clock mr-1"></i>Shift
                    </label>
                    <select id="shift" name="shift"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                        <option value="Shift 1">Shift 1 (07:00 - 15:00)</option>
                        <option value="Shift 2">Shift 2 (15:00 - 23:00)</option>
                        <option value="Shift 3">Shift 3 (23:00 - 07:00)</option>
                    </select>
                </div>
            </div>

            <!-- QC Status -->
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">
                    <i class="fas fa-clipboard-check mr-1"></i>QC Status
                </label>
                <select id="qc_status" name="qc_status"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Passed">Passed</option>
                    <option value="Failed">Failed</option>
                    <option value="Rework">Rework</option>
                </select>
            </div>

            <!-- Notes -->
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">
                    <i class="fas fa-sticky-note mr-1"></i>Progress Notes
                </label>
                <textarea id="fabrication_notes" name="notes" rows="4"
                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                    placeholder="Enter progress update notes, issues encountered, or achievements..."></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeMaterialFabricationModal()"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="submit"
                    class="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Save Progress</span>
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

    /* Status Option Hover Effects */
    .status-option {
        transition: all 0.2s ease-in-out;
    }

    .status-option:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Custom radio button styling */
    .status-option input[type="radio"]:checked+div .w-4.h-4 {
        border-color: #3b82f6;
    }

    .status-option input[type="radio"]:checked+div .w-2.h-2 {
        background-color: #3b82f6;
    }

    /* Status color coding in modal */
    .status-option[data-status="Draft"] {
        border-left-color: #6b7280;
    }

    .status-option[data-status="For Review"] {
        border-left-color: #f59e0b;
    }

    .status-option[data-status="Approved"] {
        border-left-color: #10b981;
    }

    .status-option[data-status="Rejected"] {
        border-left-color: #ef4444;
    }

    /* Task Selection Item Hover Effects */
    .task-selection-item {
        transition: all 0.2s ease-in-out;
        border: 1px solid #4b5563;
    }

    .task-selection-item:hover {
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Progress bar colors */
    .bg-green-400 {
        background-color: #34d399;
    }

    .bg-green-300 {
        background-color: #6ee7b7;
    }

    .bg-yellow-400 {
        background-color: #fbbf24;
    }

    .bg-orange-400 {
        background-color: #fb923c;
    }

    .bg-red-400 {
        background-color: #f87171;
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
            ['icon' => 'fa-upload', 'title' => 'Upload Drawing', 'subtitle' => 'PDF, DWG, DXF', 'onclick' => 'showUploadDrawingModal()'],
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
            ['id' => 'delivery', 'icon' => 'fa-truck', 'label' => 'Delivery Tracking', 'count' => ''],
            ['id' => 'deliveries', 'icon' => 'fa-clipboard-list', 'label' => 'Delivery Notes', 'count' => '']
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

    // Drawings Tab - UPDATED COMPLETE IMPLEMENTATION
    $html .= '
    <div id="content-drawings" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl mb-6">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-drafting-compass text-blue-400 mr-2"></i>
                    Engineering Drawings Management
                </h2>
                ' . (canManageMaterial() ? '
                <button onclick="showUploadDrawingModal()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-upload"></i>
                    <span>Upload Drawing</span>
                </button>' : '<span class="text-gray-400 text-sm">Read-only access</span>') . '
            </div>

            <div class="p-6">
                <!-- Drawing Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-blue-400" id="totalDrawings">-</div>
                                <div class="text-blue-300 text-sm">Total Drawings</div>
                            </div>
                            <i class="fas fa-drafting-compass text-blue-400"></i>
                        </div>
                    </div>
                    <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-green-400" id="approvedDrawings">-</div>
                                <div class="text-green-300 text-sm">Approved</div>
                            </div>
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                    </div>
                    <div class="bg-yellow-900 bg-opacity-20 p-4 rounded-lg border border-yellow-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-yellow-400" id="reviewDrawings">-</div>
                                <div class="text-yellow-300 text-sm">For Review</div>
                            </div>
                            <i class="fas fa-clock text-yellow-400"></i>
                        </div>
                    </div>
                    <div class="bg-red-900 bg-opacity-20 p-4 rounded-lg border border-red-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-red-400" id="rejectedDrawings">-</div>
                                <div class="text-red-300 text-sm">Rejected</div>
                            </div>
                            <i class="fas fa-times-circle text-red-400"></i>
                        </div>
                    </div>
                </div>

                <!-- Drawings Table -->
                <div class="bg-gray-800 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-blue-600 text-white text-sm">
                                <tr>
                                    <th class="px-4 py-3 text-left">No.</th>
                                    <th class="px-4 py-3 text-left">Drawing Number</th>
                                    <th class="px-4 py-3 text-left">Drawing Name</th>
                                    <th class="px-4 py-3 text-center">Revision</th>
                                    <th class="px-4 py-3 text-center">File Type</th>
                                    <th class="px-4 py-3 text-center">File Size</th>
                                    <th class="px-4 py-3 text-center">Upload Date</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="drawingsTableBody" class="divide-y divide-gray-700">
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                        <p>Loading drawings data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Help -->
                <div class="mt-6 p-4 bg-blue-900 bg-opacity-20 rounded-lg border border-blue-700">
                    <h4 class="text-blue-300 font-semibold mb-2 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        Drawing Management Guide
                    </h4>
                    <ul class="text-blue-200 text-sm space-y-1">
                        <li>• Supported formats: PDF, DWG, DXF (Max 20MB)</li>
                        <li>• Use consistent drawing numbering for better organization</li>
                        <li>• Update status to track drawing approval progress</li>
                        <li>• PDF files can be viewed directly in browser</li>
                    </ul>
                </div>
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

    // Get material orders dengan JOIN ke material_lists untuk nama item
    $orders_query = "SELECT 
                        mo.order_id,
                        mo.supplier_name,
                        mo.quantity,
                        mo.unit,
                        mo.order_date,
                        mo.expected_receiving_date,
                        mo.status,
                        mo.material_id,
                        mo.material_type,
                        COALESCE(ml.name, mo.material_type, 'Custom Material') as item_name,
                        COALESCE(ml.assy_marking, '-') as assy_marking,
                        u.full_name as created_by_name 
                     FROM material_orders mo 
                     LEFT JOIN material_lists ml ON mo.material_id = ml.material_id
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

    // Get suppliers data
    $suppliers = [];
    $suppliers_query = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY supplier_name";
    $suppliers_result = $conn->query($suppliers_query);
    if ($suppliers_result) {
        while ($row = $suppliers_result->fetch_assoc()) {
            $suppliers[] = $row;
        }
    }

    // Get deliveries data
    $deliveries = [];
    $deliveries_query = "SELECT d.*, mo.supplier_name, mo.material_type 
                         FROM deliveries d 
                         JOIN material_orders mo ON d.order_id = mo.order_id 
                         WHERE mo.pon_id = ? 
                         ORDER BY d.delivery_date DESC";
    $deliveries_stmt = $conn->prepare($deliveries_query);
    $deliveries_stmt->bind_param("i", $pon_id);
    $deliveries_stmt->execute();
    $deliveries_result = $deliveries_stmt->get_result();
    while ($row = $deliveries_result->fetch_assoc()) {
        $deliveries[] = $row;
    }

    $html = '';

    // Orders Tab
    $html .= '
    <div id="content-orders" class="tab-content">
        <div class="bg-dark-light rounded-xl shadow-xl mb-6">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-file-invoice text-green-400 mr-2"></i>
                        Purchase Orders (' . count($orders) . ' orders)
                    </h2>
                    <p class="text-gray-400 text-sm mt-1">Manage purchase orders with material details</p>
                </div>
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
                            <th class="px-4 py-3 text-center">No</th>
                            <th class="px-4 py-3 text-left">No Pre-Order</th>
                            <th class="px-4 py-3 text-left">Nama Item</th>
                            <th class="px-4 py-3 text-left">Supplier</th>
                            <th class="px-4 py-3 text-center">QTY</th>
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
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-file-invoice text-4xl mb-3 opacity-50"></i>
                                <p>No purchase orders found</p>
                                ' . (canManagePurchasing() ? '
                                <button onclick="showAddOrderModal()" class="text-green-400 hover:text-green-300 mt-2">
                                    <i class="fas fa-plus-circle mr-1"></i>Create first PO
                                </button>' : '') . '
                            </td>
                        </tr>';
    } else {
        $no = 1;
        foreach ($orders as $order) {
            $status_colors = [
                'Ordered' => 'bg-yellow-500',
                'Partial Received' => 'bg-blue-500',
                'Received' => 'bg-green-500',
                'Cancelled' => 'bg-red-500'
            ];

            // Format item name dengan assy marking jika ada
            $item_display = htmlspecialchars($order['item_name']);
            if ($order['assy_marking'] && $order['assy_marking'] != '-') {
                $item_display = '<span class="text-blue-300 font-mono text-xs">' . htmlspecialchars($order['assy_marking']) . '</span><br>' . $item_display;
            }

            $html .= '
                        <tr class="hover:bg-gray-800 transition">
                            <td class="px-4 py-3 text-center text-gray-300 text-sm font-semibold">' . $no++ . '</td>
                            <td class="px-4 py-3">
                                <span class="text-white font-mono text-sm font-bold">PO-' . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . '</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-white font-semibold">' . $item_display . '</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-gray-300">' . htmlspecialchars($order['supplier_name']) . '</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-white font-bold">' . ($order['quantity'] ? number_format($order['quantity'], 2) : '0') . '</span>
                                <span class="text-gray-400 text-sm ml-1">' . ($order['unit'] ?: 'pcs') . '</span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300 text-sm">
                                ' . ($order['order_date'] ? format_date_indo($order['order_date']) : '-') . '
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300 text-sm">
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
                                            class="text-green-400 hover:text-green-300" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ' . (canManagePurchasing() ? '
                                    <button onclick="editOrder(' . $order['order_id'] . ')" 
                                            class="text-blue-400 hover:text-blue-300" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="createDeliveryForOrder(' . $order['order_id'] . ')" 
                                            class="text-purple-400 hover:text-purple-300" title="Create Delivery">
                                        <i class="fas fa-truck"></i>
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

            <!-- Quick Statistics -->
            <div class="p-4 bg-gray-850 border-t border-gray-700">
                <div class="grid grid-cols-4 gap-4 text-center text-sm">
                    <div>
                        <div class="text-gray-400">Total Orders</div>
                        <div class="text-white font-bold text-xl">' . count($orders) . '</div>
                    </div>
                    <div>
                        <div class="text-gray-400">Ordered</div>
                        <div class="text-yellow-400 font-bold text-xl">' . count(array_filter($orders, fn($o) => $o['status'] == 'Ordered')) . '</div>
                    </div>
                    <div>
                        <div class="text-gray-400">Partial</div>
                        <div class="text-blue-400 font-bold text-xl">' . count(array_filter($orders, fn($o) => $o['status'] == 'Partial Received')) . '</div>
                    </div>
                    <div>
                        <div class="text-gray-400">Received</div>
                        <div class="text-green-400 font-bold text-xl">' . count(array_filter($orders, fn($o) => $o['status'] == 'Received')) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    // Suppliers Tab - UPDATE DENGAN TABLE LENGKAP
    $html .= '
    <div id="content-suppliers" class="tab-content hidden">
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Supplier Management</h3>
                <button onclick="showAddSupplierModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Supplier</span>
                </button>
            </div>
            <p class="text-gray-400 mt-2">Manage your supplier database with complete contact information</p>
        </div>

        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Supplier Info</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Details</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Created</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody" class="bg-gray-800 divide-y divide-gray-700">
                        <!-- Suppliers will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>';


    // Delivery Tracking Tab - UPDATE DENGAN TABLE LENGKAP
    $html .= '
    <div id="content-delivery" class="tab-content hidden">
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Delivery Tracking</h3>
                <button onclick="showAddDeliveryModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                    <i class="fas fa-truck"></i>
                    <span>Schedule Delivery</span>
                </button>
            </div>
            <p class="text-gray-400 mt-2">Track all deliveries and manage shipping information</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="bg-blue-900 bg-opacity-20 p-6 rounded-lg border border-blue-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-blue-400" id="scheduledDeliveries">0</div>
                        <div class="text-blue-300 text-sm">Scheduled</div>
                    </div>
                    <i class="fas fa-clock text-blue-400 text-2xl"></i>
                </div>
            </div>
            <div class="bg-yellow-900 bg-opacity-20 p-6 rounded-lg border border-yellow-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-yellow-400" id="inTransitDeliveries">0</div>
                        <div class="text-yellow-300 text-sm">In Transit</div>
                    </div>
                    <i class="fas fa-shipping-fast text-yellow-400 text-2xl"></i>
                </div>
            </div>
            <div class="bg-green-900 bg-opacity-20 p-6 rounded-lg border border-green-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-green-400" id="deliveredDeliveries">0</div>
                        <div class="text-green-300 text-sm">Delivered</div>
                    </div>
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Delivery #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Order Info</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Carrier & Tracking</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Schedule</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deliveriesTableBody" class="bg-gray-800 divide-y divide-gray-700">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p>Loading deliveries data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>';

    // Delivery Notes Tab - TAMBAHKAN SETELAH content-delivery
    $html .= '
    <div id="content-deliveries" class="tab-content hidden">
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">Delivery Notes & Documentation</h3>
                    <p class="text-gray-400 mt-2">Complete delivery records with proof of delivery (POD)</p>
                </div>
                <button onclick="showAddDeliveryModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                    <i class="fas fa-plus-circle"></i>
                    <span>Create Delivery Note</span>
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-blue-400" id="totalDeliveryNotes">0</div>
                        <div class="text-blue-300 text-sm">Total Delivery Notes</div>
                    </div>
                    <i class="fas fa-clipboard-list text-blue-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-green-400" id="completedDeliveryNotes">0</div>
                        <div class="text-green-300 text-sm">Completed</div>
                    </div>
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-yellow-900 bg-opacity-20 p-4 rounded-lg border border-yellow-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-yellow-400" id="pendingDeliveryNotes">0</div>
                        <div class="text-yellow-300 text-sm">In Transit</div>
                    </div>
                    <i class="fas fa-shipping-fast text-yellow-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-purple-900 bg-opacity-20 p-4 rounded-lg border border-purple-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-purple-400" id="thisMonthDeliveries">0</div>
                        <div class="text-purple-300 text-sm">This Month</div>
                    </div>
                    <i class="fas fa-calendar-alt text-purple-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Delivery Notes Table -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Delivery Note #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Order Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Material</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Delivery Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Received Qty</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deliveryNotesTableBody" class="bg-gray-800 divide-y divide-gray-700">
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p>Loading delivery notes...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Box -->
        <div class="mt-6 p-4 bg-blue-900 bg-opacity-20 rounded-lg border border-blue-700">
            <h4 class="text-blue-300 font-semibold mb-2 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                Delivery Notes Management
            </h4>
            <ul class="text-blue-200 text-sm space-y-1">
                <li>• Delivery notes are automatically created from delivery tracking</li>
                <li>• Each delivery note contains proof of delivery (POD) and received quantities</li>
                <li>• Update received quantities to match actual delivery</li>
                <li>• Track partial deliveries and backorders</li>
            </ul>
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
                <td class="px-4 py-3 text-center">';
            // Tambahkan kondisi PHP untuk tombol aksi
            if (canManageFabrication()) {
                $html .= '
                        <div class="flex items-center justify-center space-x-2">
                            <button onclick="updateMaterialFabrication(' . $material['material_id'] . ')" 
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-sm font-semibold transition"
                                    title="Update Progress">';

                // Tentukan teks tombol berdasarkan status
                if ($material['fabrication_status'] === 'Pending') {
                    $html .= '<i class="fas fa-play mr-1"></i>Start';
                } else {
                    $html .= '<i class="fas fa-edit mr-1"></i>Update';
                }
                $html .= '
                            </button>
                            <button onclick="showMaterialHistory(' . $material['material_id'] . ')" 
                                    class="text-blue-400 hover:text-blue-300" 
                                    title="View History">
                                <i class="fas fa-history"></i>
                            </button>
                        </div>';
            } else {
                $html .= '
                        <span class="text-gray-500 text-sm">Read-only</span>';
            }

            $html .= '
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

// ==============================================    
// LOGISTIK HELPER FUNCTION
// ==============================================

function getLogistikTabContent($pon_id, $tasks, $config)
{
    global $conn;

    // Get deliveries dengan join ke orders dan PON
    $deliveries_query = "SELECT 
                            d.*,
                            mo.supplier_name,
                            mo.material_type,
                            mo.quantity as order_quantity,
                            mo.unit,
                            mo.order_id,
                            p.pon_number,
                            u.full_name as created_by_name
                         FROM deliveries d
                         JOIN material_orders mo ON d.order_id = mo.order_id
                         JOIN pon p ON mo.pon_id = p.pon_id
                         LEFT JOIN users u ON d.created_by = u.user_id
                         WHERE mo.pon_id = ?
                         ORDER BY d.delivery_date DESC, d.created_at DESC";

    $stmt = $conn->prepare($deliveries_query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $deliveries_result = $stmt->get_result();
    $deliveries = [];
    while ($row = $deliveries_result->fetch_assoc()) {
        $deliveries[] = $row;
    }

    // Calculate statistics
    $total_deliveries = count($deliveries);
    $scheduled_count = count(array_filter($deliveries, fn($d) => $d['status'] == 'Scheduled'));
    $in_transit_count = count(array_filter($deliveries, fn($d) => $d['status'] == 'In Transit'));
    $delivered_count = count(array_filter($deliveries, fn($d) => $d['status'] == 'Delivered'));
    $delayed_count = count(array_filter($deliveries, fn($d) => $d['status'] == 'Delayed'));

    // Overdue deliveries (delivery_date sudah lewat tapi belum delivered)
    $overdue_count = count(array_filter($deliveries, function ($d) {
        return $d['delivery_date'] < date('Y-m-d') &&
            !in_array($d['status'], ['Delivered', 'Cancelled']);
    }));

    $html = '';

    // ========================================
    // TAB 1: SHIPPING STATUS
    // ========================================
    $html .= '
    <div id="content-shipping" class="tab-content">
        <!-- Statistics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
            <div class="bg-purple-900 bg-opacity-20 p-4 rounded-lg border border-purple-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-purple-400">' . $total_deliveries . '</div>
                        <div class="text-purple-300 text-sm">Total Shipments</div>
                    </div>
                    <i class="fas fa-box text-purple-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-blue-900 bg-opacity-20 p-4 rounded-lg border border-blue-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-blue-400">' . $scheduled_count . '</div>
                        <div class="text-blue-300 text-sm">Scheduled</div>
                    </div>
                    <i class="fas fa-clock text-blue-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-yellow-900 bg-opacity-20 p-4 rounded-lg border border-yellow-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-yellow-400">' . $in_transit_count . '</div>
                        <div class="text-yellow-300 text-sm">In Transit</div>
                    </div>
                    <i class="fas fa-shipping-fast text-yellow-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-green-900 bg-opacity-20 p-4 rounded-lg border border-green-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-green-400">' . $delivered_count . '</div>
                        <div class="text-green-300 text-sm">Delivered</div>
                    </div>
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-orange-900 bg-opacity-20 p-4 rounded-lg border border-orange-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-orange-400">' . $delayed_count . '</div>
                        <div class="text-orange-300 text-sm">Delayed</div>
                    </div>
                    <i class="fas fa-exclamation-triangle text-orange-400 text-2xl"></i>
                </div>
            </div>
            
            <div class="bg-red-900 bg-opacity-20 p-4 rounded-lg border border-red-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-red-400">' . $overdue_count . '</div>
                        <div class="text-red-300 text-sm">Overdue</div>
                    </div>
                    <i class="fas fa-exclamation-circle text-red-400 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Action Buttons & Filters -->
        <div class="bg-dark-light rounded-xl shadow-xl mb-6">
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-shipping-fast text-purple-400 mr-2"></i>
                        Shipping Management
                    </h2>
                    
                    <!-- Quick Filters -->
                    <div class="flex space-x-2">
                        <button onclick="filterShipments(\'all\')" 
                                class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition"
                                id="filter-all">
                            All
                        </button>
                        <button onclick="filterShipments(\'Scheduled\')" 
                                class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition"
                                id="filter-scheduled">
                            Scheduled
                        </button>
                        <button onclick="filterShipments(\'In Transit\')" 
                                class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition"
                                id="filter-transit">
                            In Transit
                        </button>
                        <button onclick="filterShipments(\'Overdue\')" 
                                class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition"
                                id="filter-overdue">
                            Overdue
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center space-x-3">
                    <button onclick="showAddShipmentModal()" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>New Shipment</span>
                    </button>
                    <button onclick="refreshShippingList()" 
                            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <!-- Shipments Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-purple-600 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 text-left">Delivery #</th>
                            <th class="px-4 py-3 text-left">Order Info</th>
                            <th class="px-4 py-3 text-left">Carrier & Tracking</th>
                            <th class="px-4 py-3 text-center">Schedule</th>
                            <th class="px-4 py-3 text-center">Progress</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="shippingTableBody" class="divide-y divide-gray-700">';

    if (empty($deliveries)) {
        $html .= '
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-shipping-fast text-4xl mb-3 opacity-50"></i>
                                <p>No shipments found</p>
                                <button onclick="showAddShipmentModal()" class="text-purple-400 hover:text-purple-300 mt-2">
                                    <i class="fas fa-plus-circle mr-1"></i>Create first shipment
                                </button>
                            </td>
                        </tr>';
    } else {
        foreach ($deliveries as $delivery) {
            $status_colors = [
                'Scheduled' => 'bg-blue-500',
                'In Transit' => 'bg-yellow-500',
                'Delivered' => 'bg-green-500',
                'Delayed' => 'bg-orange-500',
                'Cancelled' => 'bg-red-500'
            ];

            // Calculate progress
            $progress_percent = 0;
            if ($delivery['status'] == 'Scheduled') $progress_percent = 10;
            elseif ($delivery['status'] == 'In Transit') $progress_percent = 50;
            elseif ($delivery['status'] == 'Delivered') $progress_percent = 100;
            elseif ($delivery['status'] == 'Delayed') $progress_percent = 30;

            // Check if overdue
            $is_overdue = $delivery['delivery_date'] < date('Y-m-d') &&
                !in_array($delivery['status'], ['Delivered', 'Cancelled']);

            $overdue_badge = $is_overdue ?
                '<span class="ml-2 px-2 py-1 bg-red-500 text-white text-xs rounded-full animate-pulse">OVERDUE</span>' : '';

            $html .= '
                        <tr class="hover:bg-gray-800 transition shipment-row" data-status="' . $delivery['status'] . '">
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-box text-purple-400"></i>
                                    <div>
                                        <span class="text-white font-mono font-bold">' . htmlspecialchars($delivery['delivery_number']) . '</span>
                                        ' . $overdue_badge . '
                                        <div class="text-gray-400 text-xs">
                                            ' . ($delivery['tracking_number'] ?
                '<i class="fas fa-barcode mr-1"></i>' . htmlspecialchars($delivery['tracking_number'])
                : 'No tracking') . '
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-white font-semibold">PO-' . str_pad($delivery['order_id'], 4, '0', STR_PAD_LEFT) . '</div>
                                <div class="text-gray-400 text-sm">' . htmlspecialchars($delivery['supplier_name']) . '</div>
                                <div class="text-gray-500 text-xs">' . htmlspecialchars($delivery['material_type']) . '</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-white font-medium">' . htmlspecialchars($delivery['carrier_name']) . '</div>
                                <div class="text-gray-400 text-sm">
                                    ' . ($delivery['driver_name'] ?
                '<i class="fas fa-user mr-1"></i>' . htmlspecialchars($delivery['driver_name'])
                : 'No driver assigned') . '
                                </div>
                                <div class="text-gray-500 text-xs">
                                    ' . ($delivery['vehicle_number'] ?
                '<i class="fas fa-truck mr-1"></i>' . htmlspecialchars($delivery['vehicle_number'])
                : '') . '
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="text-white text-sm font-medium">' . format_date_indo($delivery['delivery_date']) . '</div>
                                ' . ($delivery['estimated_arrival'] ? '
                                <div class="text-blue-400 text-xs">
                                    ETA: ' . date('d M H:i', strtotime($delivery['estimated_arrival'])) . '
                                </div>' : '') . '
                                ' . ($delivery['actual_arrival'] ? '
                                <div class="text-green-400 text-xs">
                                    <i class="fas fa-check mr-1"></i>Arrived: ' . date('d M H:i', strtotime($delivery['actual_arrival'])) . '
                                </div>' : '') . '
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col items-center">
                                    <span class="text-white font-bold text-lg">' . $progress_percent . '%</span>
                                    <div class="w-20 bg-gray-700 rounded-full h-2 mt-1">
                                        <div class="h-2 rounded-full ' . ($is_overdue ? 'bg-red-500' : 'bg-purple-500') . '" 
                                             style="width: ' . $progress_percent . '%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold ' . $status_colors[$delivery['status']] . ' text-white">
                                    ' . $delivery['status'] . '
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick="viewShipmentDetails(' . $delivery['delivery_id'] . ')" 
                                            class="text-blue-400 hover:text-blue-300" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="updateShipmentStatus(' . $delivery['delivery_id'] . ')" 
                                            class="text-yellow-400 hover:text-yellow-300" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="printShippingLabel(' . $delivery['delivery_id'] . ')" 
                                            class="text-green-400 hover:text-green-300" title="Print Label">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    ' . (!in_array($delivery['status'], ['Delivered', 'Cancelled']) ? '
                                    <button onclick="markAsDelivered(' . $delivery['delivery_id'] . ')" 
                                            class="text-purple-400 hover:text-purple-300" title="Mark as Delivered">
                                        <i class="fas fa-check-circle"></i>
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

        <!-- Quick Info Panel -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-dark-light rounded-xl p-6">
                <h3 class="text-white font-bold mb-4">
                    <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                    Requires Attention
                </h3>
                <div id="attentionList" class="space-y-3">
                    ' . generateAttentionList($deliveries) . '
                </div>
            </div>
            
            <div class="bg-dark-light rounded-xl p-6">
                <h3 class="text-white font-bold mb-4">
                    <i class="fas fa-clock text-blue-400 mr-2"></i>
                    Upcoming Deliveries (7 Days)
                </h3>
                <div id="upcomingList" class="space-y-3">
                    ' . generateUpcomingList($deliveries) . '
                </div>
            </div>
        </div>
    </div>';

    // ========================================
    // TAB 2: LIVE TRACKING (Placeholder for now)
    // ========================================
    $html .= '
    <div id="content-tracking" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl p-8">
            <div class="text-center">
                <i class="fas fa-map-marked-alt text-6xl text-purple-600 mb-4"></i>
                <h3 class="text-2xl font-bold text-white mb-2">Live Tracking</h3>
                <p class="text-gray-400 mb-6">Real-time GPS tracking coming soon</p>
                <p class="text-purple-300 text-sm">This feature will be available in Phase 5</p>
            </div>
        </div>
    </div>';

    // ========================================
    // TAB 3: DELIVERY NOTES (Placeholder for now)
    // ========================================
    $html .= '
    <div id="content-delivery_notes" class="tab-content hidden">
        <div class="bg-dark-light rounded-xl shadow-xl p-8">
            <div class="text-center">
                <i class="fas fa-file-signature text-6xl text-purple-600 mb-4"></i>
                <h3 class="text-2xl font-bold text-white mb-2">Delivery Notes</h3>
                <p class="text-gray-400 mb-6">Document management & POD system</p>
                <p class="text-purple-300 text-sm">This feature will be available in Phase 4</p>
            </div>
        </div>
    </div>';

    return $html;
}

// Helper function untuk attention list
function generateAttentionList($deliveries)
{
    $attention_items = array_filter($deliveries, function ($d) {
        return $d['status'] == 'Delayed' ||
            ($d['delivery_date'] < date('Y-m-d') && !in_array($d['status'], ['Delivered', 'Cancelled']));
    });

    if (empty($attention_items)) {
        return '<div class="text-center py-4 text-gray-500">
                    <i class="fas fa-check-circle text-2xl mb-2"></i>
                    <p>All shipments on track</p>
                </div>';
    }

    $html = '';
    foreach (array_slice($attention_items, 0, 5) as $item) {
        $days_overdue = floor((time() - strtotime($item['delivery_date'])) / (60 * 60 * 24));
        $html .= '
        <div class="flex items-center justify-between p-3 bg-red-900 bg-opacity-20 rounded-lg border border-red-700">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
                <div>
                    <div class="text-white font-semibold text-sm">' . htmlspecialchars($item['delivery_number']) . '</div>
                    <div class="text-gray-400 text-xs">' . htmlspecialchars($item['supplier_name']) . '</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-red-400 font-bold text-sm">' . $days_overdue . ' days overdue</div>
                <button onclick="updateShipmentStatus(' . $item['delivery_id'] . ')" 
                        class="text-yellow-400 hover:text-yellow-300 text-xs">
                    Update <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>';
    }
    return $html;
}

// Helper function untuk upcoming list
function generateUpcomingList($deliveries)
{
    $upcoming = array_filter($deliveries, function ($d) {
        $days_until = floor((strtotime($d['delivery_date']) - time()) / (60 * 60 * 24));
        return $days_until >= 0 && $days_until <= 7 && !in_array($d['status'], ['Delivered', 'Cancelled']);
    });

    usort($upcoming, function ($a, $b) {
        return strtotime($a['delivery_date']) - strtotime($b['delivery_date']);
    });

    if (empty($upcoming)) {
        return '<div class="text-center py-4 text-gray-500">
                    <i class="fas fa-calendar-day text-2xl mb-2"></i>
                    <p>No upcoming deliveries</p>
                </div>';
    }

    $html = '';
    foreach (array_slice($upcoming, 0, 5) as $item) {
        $days_until = floor((strtotime($item['delivery_date']) - time()) / (60 * 60 * 24));
        $urgency_color = $days_until <= 1 ? 'text-yellow-400' : 'text-blue-400';

        $html .= '
        <div class="flex items-center justify-between p-3 bg-gray-750 rounded-lg">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                <div>
                    <div class="text-white font-semibold text-sm">' . htmlspecialchars($item['delivery_number']) . '</div>
                    <div class="text-gray-400 text-xs">' . htmlspecialchars($item['carrier_name']) . '</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-white font-semibold">' . format_date_indo($item['delivery_date']) . '</div>
                <div class="' . $urgency_color . ' text-xs">
                    ' . ($days_until == 0 ? 'Today' : ($days_until == 1 ? 'Tomorrow' : "in $days_until days")) . '
                </div>
            </div>
        </div>';
    }
    return $html;
}
?>