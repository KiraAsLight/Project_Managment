<?php

/**
 * Form Add PON (Project Order Notification)
 * Form lengkap untuk membuat PON baru dengan semua fields
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_role(['Admin']);

$conn = getDBConnection();

// Variabel untuk menyimpan error dan success message
$errors = [];
$success_message = '';

// Get list users untuk PIC assignment
$users_query = "SELECT user_id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name";
$users_result = $conn->query($users_query);
$users_list = [];
while ($row = $users_result->fetch_assoc()) {
    $users_list[] = $row;
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi dan sanitasi input
    $pon_number = sanitize_input($_POST['pon_number']);
    $offer_number = sanitize_input($_POST['offer_number']);
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

    // Timeline
    $project_start_date = sanitize_input($_POST['project_start_date']);

    // Material Suppliers
    $material_steel_supplier = sanitize_input($_POST['material_steel_supplier']);
    $material_bolt_supplier = sanitize_input($_POST['material_bolt_supplier']);
    $material_anchorage_supplier = sanitize_input($_POST['material_anchorage_supplier']);
    $material_bearing_supplier = sanitize_input($_POST['material_bearing_supplier']);
    $material_deck_supplier = sanitize_input($_POST['material_deck_supplier']);

    // Project Manager
    $project_manager = sanitize_input($_POST['project_manager']);
    $market = sanitize_input($_POST['market']);

    // Quality Assurance Requirements
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

    // Validasi required fields
    if (empty($pon_number)) $errors[] = "PON Number wajib diisi";
    if (empty($subject)) $errors[] = "Subject wajib diisi";
    if (empty($project_name)) $errors[] = "Project Name wajib diisi";
    if (empty($client_name)) $errors[] = "Client Name wajib diisi";
    if (empty($project_start_date)) $errors[] = "Project Start Date wajib diisi";

    // Cek duplikasi PON Number
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT pon_id FROM pon WHERE pon_number = ?");
        $check_stmt->bind_param("s", $pon_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = "PON Number sudah digunakan. Gunakan nomor lain.";
        }
    }

    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $insert_query = "INSERT INTO pon (
            pon_number, offer_number, job_number, subject, project_name, 
            qty_configuration, scope_of_work, client_name, project_owner,
            contract_number, contract_date, contract_address, director_name, 
            pic_name, pic_contact, project_start_date, project_manager, market,
            material_steel_supplier, material_bolt_supplier, material_anchorage_supplier,
            material_bearing_supplier, material_deck_supplier,
            require_wps_pqr, require_galvanizing_cert, require_mill_cert_sm490,
            require_inspection_report, require_mill_cert_deck, require_visual_welding,
            require_mill_cert_pipe, require_dimensional_report,
            notes, status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($insert_query);
        $status = 'Planning';
        $created_by = $_SESSION['user_id'];

        $stmt->bind_param(
            "sssssssssssssssssssssssssssssssssi",
            $pon_number,
            $offer_number,
            $pon_number,
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
            $notes,
            $status,
            $created_by
        );

        if ($stmt->execute()) {
            $new_pon_id = $stmt->insert_id;

            // Log activity
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'Create PON',
                "Membuat PON baru: {$pon_number}"
            );

            // Redirect ke detail page
            $_SESSION['success_message'] = "PON {$pon_number} berhasil dibuat!";
            redirect("modules/pon/detail.php?id={$new_pon_id}");
        } else {
            $errors[] = "Gagal menyimpan PON: " . $stmt->error;
        }
    }
}

$page_title = "Add New PON";
include '../../includes/header.php';
?>

<div class="flex">

    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Create New PON</h1>
                    <p class="text-gray-400">Project Order Notification - Form Input</p>
                </div>
                <a href="list.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to List</span>
                </a>
            </div>
        </div>

        <!-- Error Messages -->
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

        <!-- Form -->
        <form method="POST" action="" class="space-y-6">

            <!-- Section 1: Basic Information -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    Basic Information
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- PON Number -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            PON Number <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="pon_number"
                            required
                            placeholder="W-XXX"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['pon_number']) ? htmlspecialchars($_POST['pon_number']) : ''; ?>">
                        <p class="text-gray-500 text-sm mt-1">Format: W-XXX (contoh: W-584)</p>
                    </div>

                    <!-- Offer Number -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Offer Number</label>
                        <input
                            type="text"
                            name="offer_number"
                            placeholder="Nomor penawaran"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['offer_number']) ? htmlspecialchars($_POST['offer_number']) : ''; ?>">
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
                            placeholder="1xC70 LAHAT"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
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
                            placeholder="Pekerjaan Jembatan di..."
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo isset($_POST['project_name']) ? htmlspecialchars($_POST['project_name']) : ''; ?></textarea>
                    </div>

                    <!-- QTY/Configuration -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">QTY / Configuration</label>
                        <input
                            type="text"
                            name="qty_configuration"
                            placeholder="1 x C70"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['qty_configuration']) ? htmlspecialchars($_POST['qty_configuration']) : ''; ?>">
                    </div>

                    <!-- Scope of Work -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Scope of Work</label>
                        <input
                            type="text"
                            name="scope_of_work"
                            placeholder="PENGADAAN + PEK SIPIL + PENGANGKUTAN + ERECTION"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['scope_of_work']) ? htmlspecialchars($_POST['scope_of_work']) : ''; ?>">
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
                            placeholder="CV. EMPAT PUTRA ALAM ABADI"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['client_name']) ? htmlspecialchars($_POST['client_name']) : ''; ?>">
                    </div>

                    <!-- Project Owner -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Project Owner</label>
                        <input
                            type="text"
                            name="project_owner"
                            placeholder="Nama project owner"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['project_owner']) ? htmlspecialchars($_POST['project_owner']) : ''; ?>">
                    </div>

                    <!-- Contract Number -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Contract Number</label>
                        <input
                            type="text"
                            name="contract_number"
                            placeholder="No : 1733-00/SP-EPAA/RC70/I/2024"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['contract_number']) ? htmlspecialchars($_POST['contract_number']) : ''; ?>">
                    </div>

                    <!-- Contract Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Contract Date</label>
                        <input
                            type="date"
                            name="contract_date"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['contract_date']) ? $_POST['contract_date'] : ''; ?>">
                    </div>

                    <!-- Contract Address -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">Contract Address</label>
                        <textarea
                            name="contract_address"
                            rows="2"
                            placeholder="Jalan Lintas Sumatera KM. 07 Desa Tanjung Pinang..."
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo isset($_POST['contract_address']) ? htmlspecialchars($_POST['contract_address']) : ''; ?></textarea>
                    </div>

                    <!-- Director Name -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Director Name</label>
                        <input
                            type="text"
                            name="director_name"
                            placeholder="Ibu Dina Komalasari"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['director_name']) ? htmlspecialchars($_POST['director_name']) : ''; ?>">
                    </div>

                    <!-- PIC Name -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PIC Name</label>
                        <input
                            type="text"
                            name="pic_name"
                            placeholder="Bp. Tanhar"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['pic_name']) ? htmlspecialchars($_POST['pic_name']) : ''; ?>">
                    </div>

                    <!-- PIC Contact -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PIC Contact</label>
                        <input
                            type="text"
                            name="pic_contact"
                            placeholder="+62 811-7109-101"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['pic_contact']) ? htmlspecialchars($_POST['pic_contact']) : ''; ?>">
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
                            value="<?php echo isset($_POST['project_start_date']) ? $_POST['project_start_date'] : date('Y-m-d'); ?>">
                    </div>

                    <!-- Project Manager -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Project Manager</label>
                        <input
                            type="text"
                            name="project_manager"
                            placeholder="JON PUTRA"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['project_manager']) ? htmlspecialchars($_POST['project_manager']) : ''; ?>">
                    </div>

                    <!-- Market -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Market / Target</label>
                        <input
                            type="text"
                            name="market"
                            placeholder="Market target"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['market']) ? htmlspecialchars($_POST['market']) : ''; ?>">
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
                            placeholder="By DHJ / WGJ"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['material_steel_supplier']) ? htmlspecialchars($_POST['material_steel_supplier']) : ''; ?>">
                    </div>

                    <!-- Bolt/Nut/Washers -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Bolt/Nut/Washers</label>
                        <input
                            type="text"
                            name="material_bolt_supplier"
                            placeholder="By WGJ"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['material_bolt_supplier']) ? htmlspecialchars($_POST['material_bolt_supplier']) : ''; ?>">
                    </div>

                    <!-- Anchorage -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Anchorage</label>
                        <input
                            type="text"
                            name="material_anchorage_supplier"
                            placeholder="By WGJ"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['material_anchorage_supplier']) ? htmlspecialchars($_POST['material_anchorage_supplier']) : ''; ?>">
                    </div>

                    <!-- Bearing Pads -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Bearing Pads</label>
                        <input
                            type="text"
                            name="material_bearing_supplier"
                            placeholder="By WGJ"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['material_bearing_supplier']) ? htmlspecialchars($_POST['material_bearing_supplier']) : ''; ?>">
                    </div>

                    <!-- Steel Deck Plate -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Steel Deck Plate/Bondeck</label>
                        <input
                            type="text"
                            name="material_deck_supplier"
                            placeholder="By WGJ"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo isset($_POST['material_deck_supplier']) ? htmlspecialchars($_POST['material_deck_supplier']) : ''; ?>">
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
                            <?php echo (isset($_POST['require_wps_pqr']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">WPS / PQR</span>
                    </label>

                    <!-- Galvanizing Certificate -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_galvanizing_cert"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_galvanizing_cert']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">Galvanizing Certificate</span>
                    </label>

                    <!-- Mill Certificates Plat SM490 -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_mill_cert_sm490"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_mill_cert_sm490']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">Mill Certificates Plat SM490</span>
                    </label>

                    <!-- Inspection Report -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_inspection_report"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_inspection_report']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">Inspection Report</span>
                    </label>

                    <!-- Mill Certificates Plat Deck Plate -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_mill_cert_deck"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_mill_cert_deck']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">Mill Certificates Plat Deck Plate</span>
                    </label>

                    <!-- Visual Welding Report -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_visual_welding"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_visual_welding']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">Visual Welding Report</span>
                    </label>

                    <!-- Mill Certificates Pipa/WF/RB -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_mill_cert_pipe"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_mill_cert_pipe']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
                        <span class="text-white">Mill Certificates Pipa/WF/RB/</span>
                    </label>

                    <!-- Dimensional Report -->
                    <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-750 cursor-pointer">
                        <input
                            type="checkbox"
                            name="require_dimensional_report"
                            class="w-5 h-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                            <?php echo (isset($_POST['require_dimensional_report']) || (!isset($_POST['pon_number']))) ? 'checked' : ''; ?>>
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
                    placeholder="Catatan tambahan mengenai proyek ini..."
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-between space-x-4">
                <a
                    href="list.php"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>

                <button
                    type="submit"
                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Create PON</span>
                </button>
            </div>

        </form>

    </main>

</div>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>