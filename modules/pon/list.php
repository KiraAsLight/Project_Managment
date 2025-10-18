<?php

/**
 * PON List - Daftar semua Project Order Notification
 * Dengan fitur filter, export, dan visualisasi chart
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication - hanya Admin & Semua Divisi
require_role(['Admin', 'Engineering', 'Purchasing', 'Fabrikasi', 'Logistik', 'QC']);

$conn = getDBConnection();

// Get statistics untuk cards
$stats = get_dashboard_stats($conn);

// Get PON list dengan filter
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Build query dengan filter
$query = "SELECT 
            p.*,
            u.full_name as created_by_name,
            (SELECT AVG(progress) FROM tasks WHERE pon_id = p.pon_id) as overall_progress,
            (SELECT COUNT(*) FROM tasks WHERE pon_id = p.pon_id) as total_tasks,
            (SELECT SUM(weight_value) FROM tasks WHERE pon_id = p.pon_id AND phase IN ('Fabrication + Trial', 'Delivery')) as total_weight_calculated
          FROM pon p
          LEFT JOIN users u ON p.created_by = u.user_id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (p.pon_number LIKE '%$search%' 
                     OR p.project_name LIKE '%$search%' 
                     OR p.client_name LIKE '%$search%'
                     OR p.subject LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $query .= " AND p.status = '$status_filter'";
}

$query .= " ORDER BY p.created_at DESC";

$result = $conn->query($query);
$pon_list = [];
while ($row = $result->fetch_assoc()) {
    $pon_list[] = $row;
}

// Get data untuk charts
// 1. Project Status Distribution
$status_query = "SELECT 
                    status,
                    COUNT(*) as count
                 FROM pon
                 GROUP BY status";
$status_result = $conn->query($status_query);
$status_data = [];
while ($row = $status_result->fetch_assoc()) {
    $status_data[$row['status']] = $row['count'];
}

// 2. Material Types Distribution (dari tasks)
$material_query = "SELECT 
                    CASE 
                        WHEN phase = 'Engineering' THEN 'Engineering'
                        WHEN phase = 'Fabrication + Trial' THEN 'Fabrication'
                        WHEN phase = 'Civil Work Start' OR phase = 'Civil Work Finished' THEN 'Civil'
                        WHEN phase = 'Galvanizing + Packing' THEN 'Galvanizing'
                        WHEN phase = 'Delivery' THEN 'Logistik'
                        ELSE 'Other'
                    END as material_type,
                    COUNT(*) as count
                   FROM tasks
                   GROUP BY material_type";
$material_result = $conn->query($material_query);
$material_data = [];
while ($row = $material_result->fetch_assoc()) {
    $material_data[$row['material_type']] = $row['count'];
}

$page_title = "Project Order Notification (PON)";
include '../../includes/header.php';
?>

<div class="flex">

    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Project Order Notification (PON)</h1>
            <p class="text-gray-400">
                Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12 ·
                <?php echo date('d/m/Y, H:i:s') . ' WIB'; ?>
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

            <!-- Total Projects -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Total Projects</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $stats['total_projects']; ?></h2>
                    </div>
                    <div class="bg-blue-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-folder text-blue-400 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Completed -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Completed</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $stats['completed_projects']; ?></h2>
                    </div>
                    <div class="bg-green-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Delayed -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Delayed</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $stats['delayed_projects']; ?></h2>
                    </div>
                    <div class="bg-yellow-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-clock text-yellow-400 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Weight -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Total Weight</p>
                        <h2 class="text-3xl font-bold text-white">
                            <?php echo number_format($stats['total_weight'], 3); ?> Kg
                        </h2>
                    </div>
                    <div class="bg-purple-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-weight text-purple-400 text-3xl"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Project Status Distribution (Donut Chart) -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4">Project Status Distribution</h2>
                <div class="flex items-center justify-center">
                    <canvas id="statusChart" width="300" height="300"></canvas>
                </div>
            </div>

            <!-- Material Types Distribution (Bar Chart) -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4">Material Types Distribution</h2>
                <div class="flex items-center justify-center">
                    <canvas id="materialChart" width="400" height="300"></canvas>
                </div>
            </div>

        </div>

        <!-- Project List Section -->
        <div class="bg-dark-light rounded-xl shadow-xl overflow-hidden">

            <!-- Header dengan Search & Actions -->
            <div class="p-6 border-b border-gray-700">
                <div class="flex flex-col lg:flex-row items-center justify-between space-y-4 lg:space-y-0">

                    <h2 class="text-2xl font-bold text-white">PROJECT LIST</h2>

                    <!-- Search & Filter -->
                    <div class="flex items-center space-x-3">
                        <form method="GET" action="" class="flex items-center space-x-3">
                            <!-- Search Input -->
                            <div class="relative">
                                <input
                                    type="text"
                                    name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Cari Job No/Project/Client..."
                                    class="bg-gray-800 text-white px-4 py-2 pr-10 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 w-80">
                                <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>

                            <!-- Status Filter -->
                            <select
                                name="status"
                                onchange="this.form.submit()"
                                class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="Planning" <?php echo $status_filter == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="Engineering" <?php echo $status_filter == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                                <option value="Fabrication" <?php echo $status_filter == 'Fabrication' ? 'selected' : ''; ?>>Fabrication</option>
                                <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Delayed" <?php echo $status_filter == 'Delayed' ? 'selected' : ''; ?>>Delayed</option>
                            </select>
                        </form>

                        <!-- Action Buttons -->
                        <div class="flex items-center space-x-3">
                            <!-- Export Button -->
                            <button
                                onclick="exportPON()"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                                <i class="fas fa-file-download"></i>
                                <span>Export</span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>

                            <!-- New Project Button -->
                            <a
                                href="add.php"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                                <i class="fas fa-plus-circle"></i>
                                <span>New Project</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800 text-gray-400 text-sm">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold">JOB NO</th>
                            <th class="px-6 py-4 text-left font-semibold">PROJECT INFORMATION</th>
                            <th class="px-6 py-4 text-left font-semibold">CLIENT & LOCATION</th>
                            <th class="px-6 py-4 text-left font-semibold">TECHNICAL SPECS</th>
                            <th class="px-6 py-4 text-center font-semibold">PROGRESS</th>
                            <th class="px-6 py-4 text-left font-semibold">TIMELINE</th>
                            <th class="px-6 py-4 text-center font-semibold">STATUS</th>
                            <th class="px-6 py-4 text-center font-semibold">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">

                        <?php if (empty($pon_list)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-folder-open text-6xl mb-4 opacity-20"></i>
                                    <p class="text-lg">Tidak ada data PON ditemukan</p>
                                    <a href="add.php" class="text-blue-400 hover:text-blue-300 mt-2 inline-block">
                                        <i class="fas fa-plus-circle"></i> Buat PON Baru
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($pon_list as $pon): ?>
                                <?php
                                $progress = round($pon['overall_progress'] ?? 0, 0);
                                $status_color = get_status_color($pon['status']);
                                $weight_display = $pon['total_weight_calculated'] ?? $pon['total_weight'] ?? 0;

                                // Determine status badge text
                                $status_badge = $pon['status'];
                                if ($pon['status'] == 'Completed') {
                                    $status_badge = 'SELESAI';
                                } elseif (days_difference($pon['project_target_date']) < 0 && $pon['status'] != 'Completed') {
                                    $status_badge = 'DELAYED';
                                    $status_color = 'bg-red-500';
                                }
                                ?>
                                <tr class="hover:bg-gray-800 transition">

                                    <!-- Job No -->
                                    <td class="px-6 py-4">
                                        <a href="detail.php?id=<?php echo $pon['pon_id']; ?>" class="text-blue-400 hover:text-blue-300 font-bold text-lg">
                                            <?php echo htmlspecialchars($pon['pon_number']); ?>
                                        </a>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($pon['offer_number'] ?? '-'); ?></p>
                                    </td>

                                    <!-- Project Information -->
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs">
                                            <h3 class="font-semibold text-white mb-1">
                                                <?php echo htmlspecialchars($pon['subject']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-400 line-clamp-2">
                                                <?php echo htmlspecialchars(substr($pon['project_name'], 0, 80)); ?>
                                                <?php if (strlen($pon['project_name']) > 80) echo '...'; ?>
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Client & Location -->
                                    <td class="px-6 py-4">
                                        <p class="text-white font-semibold mb-1">
                                            <?php echo htmlspecialchars($pon['client_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php
                                            if (!empty($pon['contract_number'])) {
                                                echo 'PO. ' . htmlspecialchars($pon['contract_number']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </p>
                                    </td>

                                    <!-- Technical Specs -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <p class="text-sm">
                                                <span class="text-gray-400">Material:</span>
                                                <span class="text-white font-semibold ml-2">
                                                    <?php echo htmlspecialchars($pon['qty_configuration'] ?? 'N/A'); ?>
                                                </span>
                                            </p>
                                            <p class="text-sm">
                                                <span class="text-gray-400">QTY:</span>
                                                <span class="text-white font-semibold ml-2">
                                                    <?php echo $pon['total_tasks']; ?> units
                                                </span>
                                            </p>
                                            <p class="text-sm">
                                                <span class="text-gray-400">Weight:</span>
                                                <span class="text-white font-semibold ml-2">
                                                    <?php echo number_format($weight_display, 0); ?> Kg
                                                </span>
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Progress -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center">
                                            <!-- Circular Progress Mini -->
                                            <div class="relative inline-flex items-center justify-center mb-2">
                                                <svg class="w-16 h-16 transform -rotate-90">
                                                    <circle cx="32" cy="32" r="28" stroke="#1e293b" stroke-width="6" fill="none" />
                                                    <circle
                                                        cx="32" cy="32" r="28"
                                                        stroke="<?php echo $progress == 100 ? '#10b981' : '#3b82f6'; ?>"
                                                        stroke-width="6"
                                                        fill="none"
                                                        stroke-dasharray="<?php echo 2 * pi() * 28; ?>"
                                                        stroke-dashoffset="<?php echo 2 * pi() * 28 * (1 - $progress / 100); ?>"
                                                        stroke-linecap="round" />
                                                </svg>
                                                <div class="absolute">
                                                    <span class="text-lg font-bold text-white"><?php echo $progress; ?>%</span>
                                                </div>
                                            </div>
                                            <span class="text-xs px-3 py-1 rounded-full <?php echo $status_color; ?> text-white font-semibold">
                                                <?php echo strtoupper($pon['status']); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Timeline -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-2">
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-calendar-alt text-gray-500 text-xs"></i>
                                                <div>
                                                    <p class="text-xs text-gray-400">Start:</p>
                                                    <p class="text-sm text-white font-semibold">
                                                        <?php echo format_date_indo($pon['project_start_date']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <i class="fas fa-flag-checkered text-gray-500 text-xs"></i>
                                                <div>
                                                    <p class="text-xs text-gray-400">PON:</p>
                                                    <p class="text-sm text-white font-semibold">
                                                        <?php echo format_date_indo($pon['created_at']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($status_badge == 'SELESAI'): ?>
                                            <span class="inline-block px-4 py-2 rounded-lg bg-green-600 text-white font-bold text-sm">
                                                SELESAI
                                            </span>
                                        <?php elseif ($status_badge == 'DELAYED'): ?>
                                            <span class="inline-block px-4 py-2 rounded-lg bg-red-600 text-white font-bold text-sm animate-pulse">
                                                DELAYED
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-block px-4 py-2 rounded-lg <?php echo $status_color; ?> text-white font-bold text-sm">
                                                ACTIVE
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center space-x-2">
                                            <!-- View Details - SEMUA DIVISI BISA LIHAT -->
                                            <a href="detail.php?id=<?php echo $pon['pon_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-lg transition" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Edit - HANYA ADMIN -->
                                            <?php if (hasRole('Admin')): ?>
                                                <a href="edit.php?id=<?php echo $pon['pon_id']; ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg transition" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Tasks - HANYA DIVISI YANG BERHAK -->
                                            <a href="../tasks/manage.php?pon_id=<?php echo $pon['pon_id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white p-2 rounded-lg transition" title="Manage Tasks">
                                                <i class="fas fa-tasks"></i>
                                            </a>

                                            <!-- Delete - HANYA ADMIN -->
                                            <?php if (hasRole('Admin')): ?>
                                                <button onclick="deletePON(<?php echo $pon['pon_id']; ?>, '<?php echo htmlspecialchars($pon['pon_number']); ?>')" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <!-- Pagination (optional untuk future) -->
            <!-- <div class="p-6 border-t border-gray-700 text-center text-gray-400">
                <p>© 2025 PT. Wiratama Globalindo Jaya · Project Management</p>
            </div> -->

        </div>

    </main>

</div>

<!-- Chart.js Scripts -->
<script>
    // Data dari PHP
    const statusData = <?php echo json_encode($status_data); ?>;
    const materialData = <?php echo json_encode($material_data); ?>;

    // 1. Project Status Distribution (Donut Chart)
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusData),
            datasets: [{
                data: Object.values(statusData),
                backgroundColor: [
                    '#f59e0b', // Progress - Orange
                    '#10b981', // Completed - Green
                    '#6b7280', // Pending - Gray
                    '#ef4444', // Delayed - Red
                    '#3b82f6', // Active - Blue
                ],
                borderColor: '#1e293b',
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#fff',
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' projects';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });

    // 2. Material Types Distribution (Bar Chart)
    const materialCtx = document.getElementById('materialChart').getContext('2d');
    new Chart(materialCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(materialData),
            datasets: [{
                label: 'Active Projects',
                data: Object.values(materialData),
                backgroundColor: [
                    '#8b5cf6', // Purple
                    '#06b6d4', // Cyan
                    '#f59e0b', // Orange
                    '#10b981', // Green
                    '#ef4444', // Red
                ],
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' projects';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9ca3af',
                        stepSize: 1
                    },
                    grid: {
                        color: '#374151'
                    }
                },
                x: {
                    ticks: {
                        color: '#9ca3af'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Export Function
    function exportPON() {
        // Show export options
        const options = ['Export to Excel', 'Export to PDF', 'Export to CSV'];
        const choice = confirm('Export PON list?\n\nTekan OK untuk Excel, Cancel untuk format lain');

        if (choice) {
            window.location.href = 'export.php?format=excel';
        } else {
            // Bisa ditambahkan menu dropdown untuk pilihan lain
            alert('Fitur export PDF/CSV akan segera hadir!');
        }
    }

    // Delete PON Function
    function deletePON(ponId, ponNumber) {
        if (confirm(`Apakah Anda yakin ingin menghapus PON ${ponNumber}?\n\nSemua task dan dokumen terkait akan ikut terhapus!`)) {
            window.location.href = `delete.php?id=${ponId}`;
        }
    }

    // Auto refresh charts every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
</script>

<style>
    /* Line clamp utility untuk text truncation */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Hover effect untuk table rows */
    tbody tr {
        transition: background-color 0.2s ease;
    }

    /* Smooth animation untuk circular progress */
    circle {
        transition: stroke-dashoffset 0.8s ease-in-out;
    }
</style>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>