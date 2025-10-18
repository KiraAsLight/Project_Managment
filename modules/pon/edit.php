<?php

/**
 * Form Edit PON
 * Form untuk update PON yang sudah ada
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

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

$errors = [];

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sama seperti add.php, tapi gunakan UPDATE query
    $subject = sanitize_input($_POST['subject']);
    $project_name = sanitize_input($_POST['project_name']);
    $qty_configuration = sanitize_input($_POST['qty_configuration']);
    $scope_of_work = sanitize_input($_POST['scope_of_work']);

    $client_name = sanitize_input($_POST['client_name']);
    $project_owner = sanitize_input($_POST['project_owner']);
    $contract_number = sanitize_input($_POST['contract_number']);
    $contract_date = sanitize_input($_POST['contract_date']);
    $contract_address = sanitize_input($_POST['contract_address']);
    $director_name = sanitize_input($_POST['director_name']);
    $pic_name = sanitize_input($_POST['pic_name']);
    $pic_contact = sanitize_input($_POST['pic_contact']);

    $project_start_date = sanitize_input($_POST['project_start_date']);
    $project_manager = sanitize_input($_POST['project_manager']);
    $market = sanitize_input($_POST['market']);

    $material_steel_supplier = sanitize_input($_POST['material_steel_supplier']);
    $material_bolt_supplier = sanitize_input($_POST['material_bolt_supplier']);
    $material_anchorage_supplier = sanitize_input($_POST['material_anchorage_supplier']);
    $material_bearing_supplier = sanitize_input($_POST['material_bearing_supplier']);
    $material_deck_supplier = sanitize_input($_POST['material_deck_supplier']);

    $require_wps_pqr = isset($_POST['require_wps_pqr']) ? 'YES' : 'NO';
    $require_galvanizing_cert = isset($_POST['require_galvanizing_cert']) ? 'YES' : 'NO';
    $require_mill_cert_sm490 = isset($_POST['require_mill_cert_sm490']) ? 'YES' : 'NO';
    $require_inspection_report = isset($_POST['require_inspection_report']) ? 'YES' : 'NO';
    $require_mill_cert_deck = isset($_POST['require_mill_cert_deck']) ? 'YES' : 'NO';
    $require_visual_welding = isset($_POST['require_visual_welding']) ? 'YES' : 'NO';
    $require_mill_cert_pipe = isset($_POST['require_mill_cert_pipe']) ? 'YES' : 'NO';
    $require_dimensional_report = isset($_POST['require_dimensional_report']) ? 'YES' : 'NO';

    $notes = sanitize_input($_POST['notes']);

    // Validasi
    if (empty($subject)) $errors[] = "Subject wajib diisi";
    if (empty($project_name)) $errors[] = "Project Name wajib diisi";
    if (empty($client_name)) $errors[] = "Client Name wajib diisi";

    if (empty($errors)) {
        $update_query = "UPDATE pon SET 
            subject = ?, project_name = ?, qty_configuration = ?, scope_of_work = ?,
            client_name = ?, project_owner = ?, contract_number = ?, contract_date = ?,
            contract_address = ?, director_name = ?, pic_name = ?, pic_contact = ?,
            project_start_date = ?, project_manager = ?, market = ?,
            material_steel_supplier = ?, material_bolt_supplier = ?, material_anchorage_supplier = ?,
            material_bearing_supplier = ?, material_deck_supplier = ?,
            require_wps_pqr = ?, require_galvanizing_cert = ?, require_mill_cert_sm490 = ?,
            require_inspection_report = ?, require_mill_cert_deck = ?, require_visual_welding = ?,
            require_mill_cert_pipe = ?, require_dimensional_report = ?, notes = ?
            WHERE pon_id = ?";

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param(
            "sssssssssssssssssssssssssssssi",
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
            $pon_id
        );

        if ($stmt->execute()) {
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'Update PON',
                "Update PON {$pon['pon_number']}"
            );

            $_SESSION['success_message'] = "PON {$pon['pon_number']} berhasil diupdate!";
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