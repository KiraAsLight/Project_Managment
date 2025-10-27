<?php

/**
 * MANUAL TASK GENERATOR
 * 
 * Halaman admin untuk generate tasks secara manual
 * untuk PON yang belum memiliki tasks
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once 'auto_generate_tasks.php';

require_login();

$conn = getDBConnection();

// Handle Generate Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_tasks'])) {
    $pon_id = (int)$_POST['pon_id'];

    $result = generateTasksForPON($pon_id, $conn);

    if ($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}

// Get all PONs
$pon_query = "SELECT p.*, 
              COUNT(t.task_id) as total_tasks
              FROM pon p
              LEFT JOIN tasks t ON p.pon_id = t.pon_id
              GROUP BY p.pon_id
              ORDER BY p.created_at DESC";
$pon_result = $conn->query($pon_query);
$pons = [];
while ($row = $pon_result->fetch_assoc()) {
    $pons[] = $row;
}

$page_title = "Task Generator - Admin";
include '../../includes/header.php';
?>

<div class="flex">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">
                <i class="fas fa-cogs text-blue-400 mr-3"></i>
                Auto Task Generator
            </h1>
            <p class="text-gray-400">
                Generate tasks otomatis untuk setiap PON berdasarkan workflow divisi
            </p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-900 bg-opacity-20 border border-green-500 text-green-400 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <div>
                        <p class="font-semibold">Success!</p>
                        <p><?php echo $success_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-900 bg-opacity-20 border border-red-500 text-red-400 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                    <div>
                        <p class="font-semibold">Error!</p>
                        <p><?php echo $error_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="bg-blue-900 bg-opacity-20 border border-blue-500 rounded-lg p-6 mb-8">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-400 text-2xl mr-4 mt-1"></i>
                <div class="text-blue-300">
                    <p class="font-semibold mb-2">Cara Kerja Auto Task Generator:</p>
                    <ol class="list-decimal list-inside space-y-1 text-sm">
                        <li>Sistem akan membuat 14 tasks untuk setiap divisi secara otomatis</li>
                        <li>Tasks dibuat berdasarkan workflow standar: Engineering → Purchasing → Fabrikasi → Logistik → QC</li>
                        <li>Setiap task memiliki deskripsi lengkap tentang apa yang harus dikerjakan</li>
                        <li>Tasks dengan status "depends_on_material" akan menunggu material list dari Engineering</li>
                        <li>Setelah tasks dibuat, setiap divisi bisa mulai bekerja sesuai tugasnya masing-masing</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- PON List -->
        <div class="bg-dark-light rounded-xl shadow-xl overflow-hidden">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-list text-purple-400 mr-2"></i>
                    Daftar PON
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800 text-gray-400 text-sm">
                        <tr>
                            <th class="px-6 py-4 text-left">PON Number</th>
                            <th class="px-6 py-4 text-left">Subject</th>
                            <th class="px-6 py-4 text-left">Client</th>
                            <th class="px-6 py-4 text-center">Total Tasks</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($pons as $pon): ?>
                            <tr class="hover:bg-gray-800 transition">
                                <td class="px-6 py-4">
                                    <a href="../pon/detail.php?id=<?php echo $pon['pon_id']; ?>"
                                        class="text-blue-400 hover:text-blue-300 font-semibold">
                                        <?php echo htmlspecialchars($pon['pon_number']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-white">
                                    <?php echo htmlspecialchars($pon['subject']); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-300">
                                    <?php echo htmlspecialchars($pon['client_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($pon['total_tasks'] > 0): ?>
                                        <span class="bg-green-900 text-green-300 px-3 py-1 rounded-full text-sm font-semibold">
                                            <?php echo $pon['total_tasks']; ?> Tasks
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-red-900 text-red-300 px-3 py-1 rounded-full text-sm font-semibold">
                                            No Tasks
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo get_status_color($pon['status']); ?> text-white">
                                        <?php echo $pon['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($pon['total_tasks'] == 0): ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Generate tasks untuk PON <?php echo $pon['pon_number']; ?>?');">
                                            <input type="hidden" name="pon_id" value="<?php echo $pon['pon_id']; ?>">
                                            <button type="submit" name="generate_tasks"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition inline-flex items-center space-x-2">
                                                <i class="fas fa-magic"></i>
                                                <span>Generate Tasks</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="pon_tasks.php?pon_id=<?php echo $pon['pon_id']; ?>"
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition inline-flex items-center space-x-2">
                                            <i class="fas fa-eye"></i>
                                            <span>View Tasks</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Task Template Preview -->
        <div class="mt-8 bg-dark-light rounded-xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-clipboard-list text-green-400 mr-2"></i>
                Task Template Yang Akan Dibuat
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">

                <!-- Engineering -->
                <div class="bg-blue-900 bg-opacity-20 border border-blue-500 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-calculator text-blue-400 text-2xl mr-3"></i>
                        <h3 class="text-blue-300 font-bold">Engineering</h3>
                    </div>
                    <div class="text-sm text-gray-300 space-y-2">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-blue-400 mr-2 mt-1"></i>
                            <span>Upload Drawings</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-blue-400 mr-2 mt-1"></i>
                            <span>Upload Material List</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-blue-700">
                        <span class="text-xs text-blue-400 font-semibold">2 Tasks</span>
                    </div>
                </div>

                <!-- Purchasing -->
                <div class="bg-green-900 bg-opacity-20 border border-green-500 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-shopping-cart text-green-400 text-2xl mr-3"></i>
                        <h3 class="text-green-300 font-bold">Purchasing</h3>
                    </div>
                    <div class="text-sm text-gray-300 space-y-2">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span>Input Suppliers</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span>Create Purchase Orders</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                            <span>Monitor Orders</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-green-700">
                        <span class="text-xs text-green-400 font-semibold">3 Tasks</span>
                    </div>
                </div>

                <!-- Fabrikasi -->
                <div class="bg-orange-900 bg-opacity-20 border border-orange-500 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-hammer text-orange-400 text-2xl mr-3"></i>
                        <h3 class="text-orange-300 font-bold">Fabrikasi</h3>
                    </div>
                    <div class="text-sm text-gray-300 space-y-2">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-orange-400 mr-2 mt-1"></i>
                            <span>Manage Materials</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-orange-400 mr-2 mt-1"></i>
                            <span>Production Management</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-orange-700">
                        <span class="text-xs text-orange-400 font-semibold">2 Tasks</span>
                    </div>
                </div>

                <!-- Logistik -->
                <div class="bg-purple-900 bg-opacity-20 border border-purple-500 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-truck text-purple-400 text-2xl mr-3"></i>
                        <h3 class="text-purple-300 font-bold">Logistik</h3>
                    </div>
                    <div class="text-sm text-gray-300 space-y-2">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-purple-400 mr-2 mt-1"></i>
                            <span>Monitor Supplier Delivery</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-purple-400 mr-2 mt-1"></i>
                            <span>Coordinate Site Delivery</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-purple-700">
                        <span class="text-xs text-purple-400 font-semibold">2 Tasks</span>
                    </div>
                </div>

                <!-- QC -->
                <div class="bg-red-900 bg-opacity-20 border border-red-500 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-clipboard-check text-red-400 text-2xl mr-3"></i>
                        <h3 class="text-red-300 font-bold">QC</h3>
                    </div>
                    <div class="text-sm text-gray-300 space-y-2">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-red-400 mr-2 mt-1"></i>
                            <span>Material Testing</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-red-400 mr-2 mt-1"></i>
                            <span>Workshop Inspection</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-red-400 mr-2 mt-1"></i>
                            <span>Site Inspection</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-red-400 mr-2 mt-1"></i>
                            <span>Camber & Bolt Check</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-red-700">
                        <span class="text-xs text-red-400 font-semibold">4 Tasks</span>
                    </div>
                </div>

            </div>

            <div class="mt-6 text-center">
                <p class="text-gray-400 text-sm">
                    Total: <span class="text-white font-bold text-lg">13 Tasks</span> akan dibuat untuk setiap PON
                </p>
            </div>
        </div>

    </main>
</div>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>