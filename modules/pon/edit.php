<?php

/**
 * Form Edit PON - UPDATED WITH DIVISION TIMELINE
 * Form untuk update PON yang sudah ada + Timeline Management
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/email_notifications.php';

require_role(['Admin']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid PON ID");
}

$pon_id = (int)$_GET['id'];
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

// Save old data for email comparison (NEW)
$old_pon_data = $pon; // Simpan data lama sebelum update

$errors = [];

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic Information
    $subject = sanitize_input($_POST['subject']);
    $project_name = sanitize_input($_POST['project_name']);
    $qty_configuration = sanitize_input($_POST['qty_configuration']);
    $scope_of_work = sanitize_input($_POST['scope_of_work']);

    // Client Information
    $client_name = sanitize_input($_POST['client_name']);
    $project_owner = sanitize_input($_POST['project_owner']);
    $contract_number = sanitize_input($_POST['contract_number']);
    $contract_date = sanitize_input($_POST['contract_date']);
    $contract_address = sanitize_input($_POST['contract_address']);
    $director_name = sanitize_input($_POST['director_name']);
    $pic_name = sanitize_input($_POST['pic_name']);
    $pic_contact = sanitize_input($_POST['pic_contact']);

    // Timeline & Project Management
    $project_start_date = sanitize_input($_POST['project_start_date']);
    $project_manager = sanitize_input($_POST['project_manager']);
    $market = sanitize_input($_POST['market']);

    // Material Suppliers
    $material_steel_supplier = sanitize_input($_POST['material_steel_supplier']);
    $material_bolt_supplier = sanitize_input($_POST['material_bolt_supplier']);
    $material_anchorage_supplier = sanitize_input($_POST['material_anchorage_supplier']);
    $material_bearing_supplier = sanitize_input($_POST['material_bearing_supplier']);
    $material_deck_supplier = sanitize_input($_POST['material_deck_supplier']);

    // QA Requirements
    $require_wps_pqr = isset($_POST['require_wps_pqr']) ? 'YES' : 'NO';
    $require_galvanizing_cert = isset($_POST['require_galvanizing_cert']) ? 'YES' : 'NO';
    $require_mill_cert_sm490 = isset($_POST['require_mill_cert_sm490']) ? 'YES' : 'NO';
    $require_inspection_report = isset($_POST['require_inspection_report']) ? 'YES' : 'NO';
    $require_mill_cert_deck = isset($_POST['require_mill_cert_deck']) ? 'YES' : 'NO';
    $require_visual_welding = isset($_POST['require_visual_welding']) ? 'YES' : 'NO';
    $require_mill_cert_pipe = isset($_POST['require_mill_cert_pipe']) ? 'YES' : 'NO';
    $require_dimensional_report = isset($_POST['require_dimensional_report']) ? 'YES' : 'NO';

    // Notes
    $notes = sanitize_input($_POST['notes']);

    // ============================================================
    // NEW: Division Timeline Input Processing
    // ============================================================

    // Engineering Division
    $engineering_start_date = !empty($_POST['engineering_start_date']) ? sanitize_input($_POST['engineering_start_date']) : NULL;
    $engineering_finish_date = !empty($_POST['engineering_finish_date']) ? sanitize_input($_POST['engineering_finish_date']) : NULL;
    $engineering_pic = !empty($_POST['engineering_pic']) ? sanitize_input($_POST['engineering_pic']) : 'N/A';

    // Purchasing Division
    $purchasing_start_date = !empty($_POST['purchasing_start_date']) ? sanitize_input($_POST['purchasing_start_date']) : NULL;
    $purchasing_finish_date = !empty($_POST['purchasing_finish_date']) ? sanitize_input($_POST['purchasing_finish_date']) : NULL;
    $purchasing_pic = !empty($_POST['purchasing_pic']) ? sanitize_input($_POST['purchasing_pic']) : 'N/A';

    // Fabrikasi Division
    $fabrikasi_start_date = !empty($_POST['fabrikasi_start_date']) ? sanitize_input($_POST['fabrikasi_start_date']) : NULL;
    $fabrikasi_finish_date = !empty($_POST['fabrikasi_finish_date']) ? sanitize_input($_POST['fabrikasi_finish_date']) : NULL;
    $fabrikasi_pic = !empty($_POST['fabrikasi_pic']) ? sanitize_input($_POST['fabrikasi_pic']) : 'N/A';

    // Logistik Division
    $logistik_start_date = !empty($_POST['logistik_start_date']) ? sanitize_input($_POST['logistik_start_date']) : NULL;
    $logistik_finish_date = !empty($_POST['logistik_finish_date']) ? sanitize_input($_POST['logistik_finish_date']) : NULL;
    $logistik_pic = !empty($_POST['logistik_pic']) ? sanitize_input($_POST['logistik_pic']) : 'N/A';

    // Validasi
    if (empty($subject)) $errors[] = "Subject wajib diisi";
    if (empty($project_name)) $errors[] = "Project Name wajib diisi";
    if (empty($client_name)) $errors[] = "Client Name wajib diisi";

    // ============================================================
    // NEW: Validasi Timeline Logic (Optional - bisa diaktifkan)
    // ============================================================
    // Contoh: Validasi finish_date harus >= start_date
    /*
    if ($engineering_start_date && $engineering_finish_date) {
        if (strtotime($engineering_finish_date) < strtotime($engineering_start_date)) {
            $errors[] = "Engineering: Finish Date tidak boleh lebih awal dari Start Date";
        }
    }
    // Ulangi untuk divisi lain jika perlu
    */

    if (empty($errors)) {
        // UPDATE query dengan timeline columns
        $update_query = "UPDATE pon SET 
            subject = ?, project_name = ?, qty_configuration = ?, scope_of_work = ?,
            client_name = ?, project_owner = ?, contract_number = ?, contract_date = ?,
            contract_address = ?, director_name = ?, pic_name = ?, pic_contact = ?,
            project_start_date = ?, project_manager = ?, market = ?,
            material_steel_supplier = ?, material_bolt_supplier = ?, material_anchorage_supplier = ?,
            material_bearing_supplier = ?, material_deck_supplier = ?,
            require_wps_pqr = ?, require_galvanizing_cert = ?, require_mill_cert_sm490 = ?,
            require_inspection_report = ?, require_mill_cert_deck = ?, require_visual_welding = ?,
            require_mill_cert_pipe = ?, require_dimensional_report = ?,
            engineering_start_date = ?, engineering_finish_date = ?, engineering_pic = ?,
            purchasing_start_date = ?, purchasing_finish_date = ?, purchasing_pic = ?,
            fabrikasi_start_date = ?, fabrikasi_finish_date = ?, fabrikasi_pic = ?,
            logistik_start_date = ?, logistik_finish_date = ?, logistik_pic = ?,
            notes = ?
            WHERE pon_id = ?";

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param(
            "sssssssssssssssssssssssssssssssssssssssssi",
            $subject,
            $project_name,
            $qty_configuration,
            $scope_of_work,
            $client_name,
            $project_owner,
            $contract_number,
            $contract_date,
            $contract_address,
            $director_name,
            $pic_name,
            $pic_contact,
            $project_start_date,
            $project_manager,
            $market,
            $material_steel_supplier,
            $material_bolt_supplier,
            $material_anchorage_supplier,
            $material_bearing_supplier,
            $material_deck_supplier,
            $require_wps_pqr,
            $require_galvanizing_cert,
            $require_mill_cert_sm490,
            $require_inspection_report,
            $require_mill_cert_deck,
            $require_visual_welding,
            $require_mill_cert_pipe,
            $require_dimensional_report,
            $engineering_start_date,
            $engineering_finish_date,
            $engineering_pic,
            $purchasing_start_date,
            $purchasing_finish_date,
            $purchasing_pic,
            $fabrikasi_start_date,
            $fabrikasi_finish_date,
            $fabrikasi_pic,
            $logistik_start_date,
            $logistik_finish_date,
            $logistik_pic,
            $notes,
            $pon_id
        );

        if ($stmt->execute()) {
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'Update PON',
                "Update PON {$pon['pon_number']} (termasuk Division Timeline)"
            );

            // ============================================================
            // NEW: Send Email Notification if Timeline Changed (Phase 3)
            // ============================================================
            try {
                // Get updated data
                $stmt_check = $conn->prepare("SELECT * FROM pon WHERE pon_id = ?");
                $stmt_check->bind_param("i", $pon_id);
                $stmt_check->execute();
                $new_pon_data = $stmt_check->get_result()->fetch_assoc();

                // Detect timeline changes
                $timeline_fields = [
                    'engineering_start_date',
                    'engineering_finish_date',
                    'engineering_pic',
                    'purchasing_start_date',
                    'purchasing_finish_date',
                    'purchasing_pic',
                    'fabrikasi_start_date',
                    'fabrikasi_finish_date',
                    'fabrikasi_pic',
                    'logistik_start_date',
                    'logistik_finish_date',
                    'logistik_pic'
                ];

                $has_timeline_changes = false;
                foreach ($timeline_fields as $field) {
                    if (($old_pon_data[$field] ?? null) !== ($new_pon_data[$field] ?? null)) {
                        $has_timeline_changes = true;
                        break;
                    }
                }

                // Send email only if timeline changed
                if ($has_timeline_changes) {
                    $email_sent = send_pon_updated_notification($conn, $pon_id, $old_pon_data);

                    if ($email_sent) {
                        error_log("Email SUCCESS: Timeline update notification sent for PON {$pon['pon_number']} (ID: $pon_id)");
                        $_SESSION['success_message'] = "PON {$pon['pon_number']} berhasil diupdate! Email notifikasi telah dikirim.";
                    } else {
                        error_log("Email WARNING: Failed to send timeline update notification for PON {$pon['pon_number']}");
                        $_SESSION['success_message'] = "PON {$pon['pon_number']} berhasil diupdate!";
                    }
                } else {
                    // No timeline changes
                    $_SESSION['success_message'] = "PON {$pon['pon_number']} berhasil diupdate!";
                }
            } catch (Exception $e) {
                error_log("Email ERROR: Exception when sending update notification - " . $e->getMessage());
                $_SESSION['success_message'] = "PON {$pon['pon_number']} berhasil diupdate!";
            }

            redirect("modules/pon/detail.php?id={$pon_id}");
        } else {
            $errors[] = "Gagal update PON: " . $stmt->error;
        }
    }
} else {
    // Populate form dengan data existing
    $_POST = $pon;
}

$page_title = "Edit PON - " . $pon['pon_number'];
include '../../includes/header.php';
?>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Edit PON: <?php echo htmlspecialchars($pon['pon_number']); ?></h1>
                    <p class="text-gray-400">Update Project Order Notification</p>
                </div>
                <a href="detail.php?id=<?php echo $pon_id; ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Detail</span>
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-900 border-l-4 border-red-500 text-red-200 p-4 mb-6 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3 mt-1"></i>
                    <div>
                        <p class="font-bold mb-2">Error! Mohon perbaiki:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">

            <!-- Section 1: Basic Information -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    Basic Information
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- PON Number (Disabled) -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PON Number</label>
                        <input
                            type="text"
                            value="<?php echo htmlspecialchars($pon['pon_number']); ?>"
                            disabled
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-gray-400 cursor-not-allowed">
                        <p class="text-gray-500 text-sm mt-1">PON Number tidak dapat diubah</p>
                    </div>

                    <!-- Offer Number -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Offer Number</label>
                        <input
                            type="text"
                            name="offer_number"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['offer_number'] ?? ''); ?>">
                    </div>

                    <!-- Subject -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="subject"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                    </div>

                    <!-- Project Name -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">
                            Project Name <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            name="project_name"
                            required
                            rows="3"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?></textarea>
                    </div>

                    <!-- QTY/Configuration -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">QTY / Configuration</label>
                        <input
                            type="text"
                            name="qty_configuration"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['qty_configuration'] ?? ''); ?>">
                    </div>

                    <!-- Scope of Work -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Scope of Work</label>
                        <input
                            type="text"
                            name="scope_of_work"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['scope_of_work'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Section 2: Client Information -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-building text-green-400 mr-2"></i>
                    Client Information
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Client Name -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Client Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="client_name"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>">
                    </div>

                    <!-- Project Owner -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Project Owner</label>
                        <input
                            type="text"
                            name="project_owner"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['project_owner'] ?? ''); ?>">
                    </div>

                    <!-- Contract Number -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Contract Number</label>
                        <input
                            type="text"
                            name="contract_number"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['contract_number'] ?? ''); ?>">
                    </div>

                    <!-- Contract Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Contract Date</label>
                        <input
                            type="date"
                            name="contract_date"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['contract_date'] ?? ''; ?>">
                    </div>

                    <!-- Contract Address -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">Contract Address</label>
                        <textarea
                            name="contract_address"
                            rows="2"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['contract_address'] ?? ''); ?></textarea>
                    </div>

                    <!-- Director Name -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Director Name</label>
                        <input
                            type="text"
                            name="director_name"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['director_name'] ?? ''); ?>">
                    </div>

                    <!-- PIC Name -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PIC Name</label>
                        <input
                            type="text"
                            name="pic_name"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['pic_name'] ?? ''); ?>">
                    </div>

                    <!-- PIC Contact -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PIC Contact</label>
                        <input
                            type="text"
                            name="pic_contact"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['pic_contact'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Section 3: Timeline & Project Management -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-calendar-alt text-yellow-400 mr-2"></i>
                    Timeline & Project Management
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Project Start Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Project Start Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="project_start_date"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['project_start_date'] ?? date('Y-m-d'); ?>">
                    </div>

                    <!-- Project Manager -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Project Manager</label>
                        <input
                            type="text"
                            name="project_manager"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['project_manager'] ?? ''); ?>">
                    </div>

                    <!-- Market -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Market / Target</label>
                        <input
                            type="text"
                            name="market"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['market'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- NEW SECTION: Division Timeline & Deadline Management (EDIT) -->
            <!-- ============================================================ -->
            <div class="bg-gradient-to-br from-indigo-900 to-purple-900 rounded-xl p-6 shadow-xl border-2 border-indigo-500">
                <div class="flex items-center justify-between mb-4 border-b border-indigo-400 pb-3">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-clock text-yellow-400 mr-2"></i>
                        Division Timeline & Deadline Management
                    </h2>
                    <span class="px-3 py-1 bg-yellow-500 text-black text-xs font-bold rounded-full">
                        <i class="fas fa-lock mr-1"></i>ADMIN ONLY
                    </span>
                </div>

                <div class="bg-yellow-900 bg-opacity-30 border-l-4 border-yellow-500 p-4 mb-6 rounded">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-yellow-400 text-xl mr-3 mt-1"></i>
                        <div>
                            <p class="text-yellow-200 font-semibold mb-1">Panduan Edit Timeline:</p>
                            <ul class="text-yellow-100 text-sm space-y-1">
                                <li>• <strong>Start Date & Finish Date</strong>: Kosongkan untuk tampilkan "TBA"</li>
                                <li>• <strong>PIC</strong>: Kosongkan untuk default "N/A"</li>
                                <li>• Timeline ini adalah <strong>DEADLINE</strong> untuk divisi</li>
                                <li>• Perubahan akan menjadi <strong>reminder baru</strong> untuk divisi terkait</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Engineering Division -->
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border border-blue-500">
                        <h3 class="text-lg font-bold text-blue-400 mb-4 flex items-center">
                            <i class="fas fa-drafting-compass mr-2"></i>
                            Engineering Division
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-alt mr-1"></i>Start Date
                                </label>
                                <input
                                    type="date"
                                    name="engineering_start_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo $_POST['engineering_start_date'] ?? ''; ?>">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-check mr-1"></i>Finish Date (Deadline)
                                </label>
                                <input
                                    type="date"
                                    name="engineering_finish_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo $_POST['engineering_finish_date'] ?? ''; ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk "TBA"</p>
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="fas fa-user mr-1"></i>PIC (Person In Charge)
                                </label>
                                <input
                                    type="text"
                                    name="engineering_pic"
                                    placeholder="Nama PIC Engineering"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    value="<?php echo htmlspecialchars($_POST['engineering_pic'] ?? ''); ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk default "N/A"</p>
                            </div>
                        </div>
                    </div>

                    <!-- Purchasing Division -->
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border border-green-500">
                        <h3 class="text-lg font-bold text-green-400 mb-4 flex items-center">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Purchasing Division
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-alt mr-1"></i>Start Date
                                </label>
                                <input
                                    type="date"
                                    name="purchasing_start_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                                    value="<?php echo $_POST['purchasing_start_date'] ?? ''; ?>">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-check mr-1"></i>Finish Date (Deadline)
                                </label>
                                <input
                                    type="date"
                                    name="purchasing_finish_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                                    value="<?php echo $_POST['purchasing_finish_date'] ?? ''; ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk "TBA"</p>
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="fas fa-user mr-1"></i>PIC (Person In Charge)
                                </label>
                                <input
                                    type="text"
                                    name="purchasing_pic"
                                    placeholder="Nama PIC Purchasing"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:ring-2 focus:ring-green-500"
                                    value="<?php echo htmlspecialchars($_POST['purchasing_pic'] ?? ''); ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk default "N/A"</p>
                            </div>
                        </div>
                    </div>

                    <!-- Fabrikasi Division -->
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border border-orange-500">
                        <h3 class="text-lg font-bold text-orange-400 mb-4 flex items-center">
                            <i class="fas fa-hammer mr-2"></i>
                            Fabrikasi Division
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-alt mr-1"></i>Start Date
                                </label>
                                <input
                                    type="date"
                                    name="fabrikasi_start_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                    value="<?php echo $_POST['fabrikasi_start_date'] ?? ''; ?>">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-check mr-1"></i>Finish Date (Deadline)
                                </label>
                                <input
                                    type="date"
                                    name="fabrikasi_finish_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                    value="<?php echo $_POST['fabrikasi_finish_date'] ?? ''; ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk "TBA"</p>
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="fas fa-user mr-1"></i>PIC (Person In Charge)
                                </label>
                                <input
                                    type="text"
                                    name="fabrikasi_pic"
                                    placeholder="Nama PIC Fabrikasi"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500"
                                    value="<?php echo htmlspecialchars($_POST['fabrikasi_pic'] ?? ''); ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk default "N/A"</p>
                            </div>
                        </div>
                    </div>

                    <!-- Logistik Division -->
                    <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border border-purple-500">
                        <h3 class="text-lg font-bold text-purple-400 mb-4 flex items-center">
                            <i class="fas fa-truck mr-2"></i>
                            Logistik Division
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-alt mr-1"></i>Start Date
                                </label>
                                <input
                                    type="date"
                                    name="logistik_start_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
                                    value="<?php echo $_POST['logistik_start_date'] ?? ''; ?>">
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="far fa-calendar-check mr-1"></i>Finish Date (Deadline)
                                </label>
                                <input
                                    type="date"
                                    name="logistik_finish_date"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
                                    value="<?php echo $_POST['logistik_finish_date'] ?? ''; ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk "TBA"</p>
                            </div>
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    <i class="fas fa-user mr-1"></i>PIC (Person In Charge)
                                </label>
                                <input
                                    type="text"
                                    name="logistik_pic"
                                    placeholder="Nama PIC Logistik"
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
                                    value="<?php echo htmlspecialchars($_POST['logistik_pic'] ?? ''); ?>">
                                <p class="text-gray-400 text-xs mt-1">Kosongkan untuk default "N/A"</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Section 4: Material Suppliers -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-truck text-purple-400 mr-2"></i>
                    Material Suppliers (Scope of Supply)
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Steel Material Supplier -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">All Steel Material</label>
                        <input
                            type="text"
                            name="material_steel_supplier"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['material_steel_supplier'] ?? ''); ?>">
                    </div>

                    <!-- Bolt/Nut/Washers -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Bolt/Nut/Washers</label>
                        <input
                            type="text"
                            name="material_bolt_supplier"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['material_bolt_supplier'] ?? ''); ?>">
                    </div>

                    <!-- Anchorage -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Anchorage</label>
                        <input
                            type="text"
                            name="material_anchorage_supplier"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['material_anchorage_supplier'] ?? ''); ?>">
                    </div>

                    <!-- Bearing Pads -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Bearing Pads</label>
                        <input
                            type="text"
                            name="material_bearing_supplier"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['material_bearing_supplier'] ?? ''); ?>">
                    </div>

                    <!-- Steel Deck Plate -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Steel Deck Plate/Bondeck</label>
                        <input
                            type="text"
                            name="material_deck_supplier"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['material_deck_supplier'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Section 5: Quality Assurance Requirements -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                    Quality Assurance Requirements (Dossier = 2 Sets)
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- WPS/PQR -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_wps_pqr"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_wps_pqr'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">WPS / PQR</span>
                    </label>

                    <!-- Galvanizing Certificate -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_galvanizing_cert"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_galvanizing_cert'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Galvanizing Certificate</span>
                    </label>

                    <!-- Mill Certificates Plat SM490 -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_mill_cert_sm490"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_mill_cert_sm490'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Mill Certificates Plat SM490</span>
                    </label>

                    <!-- Inspection Report -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_inspection_report"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_inspection_report'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Inspection Report</span>
                    </label>

                    <!-- Mill Certificates Plat Deck Plate -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_mill_cert_deck"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_mill_cert_deck'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Mill Certificates Plat Deck Plate</span>
                    </label>

                    <!-- Visual Welding Report -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_visual_welding"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_visual_welding'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Visual Welding Report</span>
                    </label>

                    <!-- Mill Certificates Pipa/WF/RB -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_mill_cert_pipe"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_mill_cert_pipe'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Mill Certificates Pipa/WF/RB/</span>
                    </label>

                    <!-- Dimensional Report -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_dimensional_report"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo ($_POST['require_dimensional_report'] == 'YES') ? 'checked' : ''; ?>>
                        <span class="text-white">Dimensional Report</span>
                    </label>
                </div>
            </div>

            <!-- Section 6: Additional Notes -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-sticky-note text-orange-400 mr-2"></i>
                    Additional Notes
                </h2>

                <textarea
                    name="notes"
                    rows="4"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-between space-x-4">
                <a
                    href="detail.php?id=<?php echo $pon_id; ?>"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>

                <button
                    type="submit"
                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Update PON</span>
                </button>
            </div>
        </form>
    </main>
</div>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>