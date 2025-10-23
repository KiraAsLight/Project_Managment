<?php

/**
 * PON List - UI OPTIMIZED VERSION
 * Daftar semua Project Order Notification dengan UI yang lebih compact
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
    <main class="flex-1 ml-64 p-6 bg-gray-900 min-h-screen">

        <!-- Header - MORE COMPACT -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white mb-1">Project Order Notification (PON)</h1>
            <p class="text-gray-400 text-sm">
                <?php echo date('d/m/Y, H:i:s') . ' WIB'; ?>
            </p>
        </div>

        <!-- Statistics Cards - SMALLER HEIGHT -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

            <!-- Total Projects -->
            <div class="bg-dark-light rounded-lg p-4 shadow-xl border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs mb-1">Total Projects</p>
                        <h2 class="text-3xl font-bold text-white"><?php echo $stats['total_projects']; ?></h2>
                    </div>
                    <div class="bg-blue-500 bg-opacity-20 rounded-lg p-2">
                        <i class="fas fa-folder text-blue-400 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Completed -->
            <div class="bg-dark-light rounded-lg p-4 shadow-xl border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs mb-1">Completed</p>
                        <h2 class="text-3xl font-bold text-white"><?php echo $stats['completed_projects']; ?></h2>
                    </div>
                    <div class="bg-green-500 bg-opacity-20 rounded-lg p-2">
                        <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Delayed -->
            <div class="bg-dark-light rounded-lg p-4 shadow-xl border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs mb-1">Delayed</p>
                        <h2 class="text-3xl font-bold text-white"><?php echo $stats['delayed_projects']; ?></h2>
                    </div>
                    <div class="bg-yellow-500 bg-opacity-20 rounded-lg p-2">
                        <i class="fas fa-clock text-yellow-400 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Weight -->
            <div class="bg-dark-light rounded-lg p-4 shadow-xl border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs mb-1">Total Weight</p>
                        <h2 class="text-2xl font-bold text-white">
                            <?php echo number_format($stats['total_weight'], 1); ?> Kg
                        </h2>
                    </div>
                    <div class="bg-purple-500 bg-opacity-20 rounded-lg p-2">
                        <i class="fas fa-weight text-purple-400 text-2xl"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Charts Section - BALANCED SIZE -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

            <!-- Project Status Distribution (Balanced Donut Chart) -->
            <div class="bg-dark-light rounded-lg p-5 shadow-xl">
                <h3 class="text-base font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-chart-pie text-blue-400 mr-2"></i>
                    Project Status
                </h3>
                <div class="flex items-center justify-center" style="height: 240px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Material Types Distribution (Balanced Bar Chart) -->
            <div class="bg-dark-light rounded-lg p-5 shadow-xl">
                <h3 class="text-base font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-chart-bar text-green-400 mr-2"></i>
                    Task Distribution
                </h3>
                <div class="flex items-center justify-center" style="height: 240px;">
                    <canvas id="materialChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Project List Section -->
        <div class="bg-dark-light rounded-lg shadow-xl overflow-hidden">

            <!-- Header dengan Search & Actions - MORE COMPACT -->
            <div class="p-4 border-b border-gray-700">
                <div class="flex flex-col lg:flex-row items-center justify-between space-y-3 lg:space-y-0">

                    <h2 class="text-lg font-bold text-white">PROJECT LIST</h2>

                    <!-- Search & Filter -->
                    <div class="flex items-center space-x-2">
                        <form method="GET" action="" class="flex items-center space-x-2">
                            <!-- Search Input - SMALLER -->
                            <div class="relative">
                                <input
                                    type="text"
                                    name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search..."
                                    class="bg-gray-800 text-white px-3 py-2 pr-8 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 w-60 text-sm">
                                <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                                    <i class="fas fa-search text-sm"></i>
                                </button>
                            </div>

                            <!-- Status Filter - SMALLER -->
                            <select
                                name="status"
                                onchange="this.form.submit()"
                                class="bg-gray-800 text-white px-3 py-2 rounded-lg border border-gray-700 focus:border-blue-500 text-sm">
                                <option value="">All Status</option>
                                <option value="Planning" <?php echo $status_filter == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="Engineering" <?php echo $status_filter == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                                <option value="Fabrication" <?php echo $status_filter == 'Fabrication' ? 'selected' : ''; ?>>Fabrication</option>
                                <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Delayed" <?php echo $status_filter == 'Delayed' ? 'selected' : ''; ?>>Delayed</option>
                            </select>
                        </form>

                        <!-- Action Buttons - SMALLER -->
                        <div class="flex items-center space-x-2">
                            <!-- Export Button -->
                            <button
                                onclick="exportPON()"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg flex items-center space-x-1 transition text-sm">
                                <i class="fas fa-file-download"></i>
                                <span>Export</span>
                            </button>

                            <!-- New Project Button -->
                            <a
                                href="add.php"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg flex items-center space-x-1 transition text-sm">
                                <i class="fas fa-plus-circle"></i>
                                <span>New</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Table - MORE COMPACT -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800 text-gray-400 text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">JOB NO</th>
                            <th class="px-4 py-3 text-left font-semibold">PROJECT INFO</th>
                            <th class="px-4 py-3 text-left font-semibold">CLIENT</th>
                            <th class="px-4 py-3 text-left font-semibold">SPECS</th>
                            <th class="px-4 py-3 text-center font-semibold">PROGRESS</th>
                            <th class="px-4 py-3 text-left font-semibold">TIMELINE</th>
                            <th class="px-4 py-3 text-center font-semibold">STATUS</th>
                            <th class="px-4 py-3 text-center font-semibold">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">

                        <?php if (empty($pon_list)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-folder-open text-4xl mb-3 opacity-20"></i>
                                    <p class="text-sm">Tidak ada data PON ditemukan</p>
                                    <a href="add.php" class="text-blue-400 hover:text-blue-300 mt-2 inline-block text-sm">
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
                                <tr class="hover:bg-gray-800 transition text-sm">

                                    <!-- Job No - COMPACT -->
                                    <td class="px-4 py-3">
                                        <a href="detail.php?id=<?php echo $pon['pon_id']; ?>" class="text-blue-400 hover:text-blue-300 font-bold">
                                            <?php echo htmlspecialchars($pon['pon_number']); ?>
                                        </a>
                                        <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($pon['offer_number'] ?? '-'); ?></p>
                                    </td>

                                    <!-- Project Information - COMPACT -->
                                    <td class="px-4 py-3">
                                        <div class="max-w-xs">
                                            <h3 class="font-semibold text-white text-sm mb-0.5">
                                                <?php echo htmlspecialchars($pon['subject']); ?>
                                            </h3>
                                            <p class="text-xs text-gray-400 line-clamp-1">
                                                <?php echo htmlspecialchars(substr($pon['project_name'], 0, 60)); ?>
                                                <?php if (strlen($pon['project_name']) > 60) echo '...'; ?>
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Client - COMPACT -->
                                    <td class="px-4 py-3">
                                        <p class="text-white font-semibold text-sm">
                                            <?php echo htmlspecialchars($pon['client_name']); ?>
                                        </p>
                                    </td>

                                    <!-- Technical Specs - COMPACT -->
                                    <td class="px-4 py-3">
                                        <div class="space-y-0.5">
                                            <p class="text-xs">
                                                <span class="text-gray-400">Qty:</span>
                                                <span class="text-white font-semibold ml-1">
                                                    <?php echo $pon['total_tasks']; ?>
                                                </span>
                                            </p>
                                            <p class="text-xs">
                                                <span class="text-gray-400">Weight:</span>
                                                <span class="text-white font-semibold ml-1">
                                                    <?php echo number_format($weight_display, 0); ?> Kg
                                                </span>
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Progress - SMALLER CIRCLE -->
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col items-center">
                                            <!-- Smaller Circular Progress -->
                                            <div class="relative inline-flex items-center justify-center mb-1">
                                                <svg class="w-12 h-12 transform -rotate-90">
                                                    <circle cx="24" cy="24" r="20" stroke="#1e293b" stroke-width="4" fill="none" />
                                                    <circle
                                                        cx="24" cy="24" r="20"
                                                        stroke="<?php echo $progress == 100 ? '#10b981' : '#3b82f6'; ?>"
                                                        stroke-width="4"
                                                        fill="none"
                                                        stroke-dasharray="<?php echo 2 * pi() * 20; ?>"
                                                        stroke-dashoffset="<?php echo 2 * pi() * 20 * (1 - $progress / 100); ?>"
                                                        stroke-linecap="round" />
                                                </svg>
                                                <div class="absolute">
                                                    <span class="text-xs font-bold text-white"><?php echo $progress; ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Timeline - COMPACT -->
                                    <td class="px-4 py-3">
                                        <div class="space-y-1">
                                            <div class="flex items-center space-x-1">
                                                <i class="fas fa-calendar-alt text-gray-500 text-xs"></i>
                                                <p class="text-xs text-white">
                                                    <?php echo format_date_indo($pon['project_start_date']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status Badge - SMALLER -->
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block px-2 py-1 rounded text-xs font-bold <?php echo $status_color; ?> text-white">
                                            <?php echo $pon['status']; ?>
                                        </span>
                                    </td>

                                    <!-- Actions - COMPACT -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center space-x-1">
                                            <!-- View Details -->
                                            <a href="detail.php?id=<?php echo $pon['pon_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white p-1.5 rounded transition text-xs" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Edit - HANYA ADMIN -->
                                            <?php if (hasRole('Admin')): ?>
                                                <a href="edit.php?id=<?php echo $pon['pon_id']; ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white p-1.5 rounded transition text-xs" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Tasks -->
                                            <a href="../tasks/manage.php?pon_id=<?php echo $pon['pon_id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white p-1.5 rounded transition text-xs" title="Tasks">
                                                <i class="fas fa-tasks"></i>
                                            </a>

                                            <!-- Delete - HANYA ADMIN -->
                                            <?php if (hasRole('Admin')): ?>
                                                <button onclick="deletePON(<?php echo $pon['pon_id']; ?>, '<?php echo htmlspecialchars($pon['pon_number']); ?>')" class="bg-red-600 hover:bg-red-700 text-white p-1.5 rounded transition text-xs" title="Delete">
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

        </div>

    </main>

</div>

<!-- Chart.js Scripts - OPTIMIZED SIZE -->
<script>
    // Data dari PHP
    const statusData = <?php echo json_encode($status_data); ?>;
    const materialData = <?php echo json_encode($material_data); ?>;

    // 1. Project Status Distribution (BALANCED Donut Chart)
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
                    position: 'right',
                    labels: {
                        color: '#fff',
                        padding: 10,
                        font: {
                            size: 11
                        },
                        boxWidth: 15,
                        boxHeight: 15
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

    // 2. Material Types Distribution (BALANCED Bar Chart)
    const materialCtx = document.getElementById('materialChart').getContext('2d');
    new Chart(materialCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(materialData),
            datasets: [{
                label: 'Tasks',
                data: Object.values(materialData),
                backgroundColor: [
                    '#8b5cf6', // Purple
                    '#06b6d4', // Cyan
                    '#f59e0b', // Orange
                    '#10b981', // Green
                    '#ef4444', // Red
                ],
                borderRadius: 6,
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
                            return context.parsed.y + ' tasks';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9ca3af',
                        stepSize: 1,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: '#374151'
                    }
                },
                x: {
                    ticks: {
                        color: '#9ca3af',
                        font: {
                            size: 11
                        }
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
        if (confirm('Export PON list to Excel?')) {
            window.location.href = 'export.php?format=excel';
        }
    }

    // Delete PON Function
    function deletePON(ponId, ponNumber) {
        if (confirm(`Hapus PON ${ponNumber}?\n\nSemua task dan dokumen terkait akan ikut terhapus!`)) {
            window.location.href = `delete.php?id=${ponId}`;
        }
    }
</script>

<style>
    /* Line clamp utility - SINGLE LINE */
    .line-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Smooth animation untuk circular progress */
    circle {
        transition: stroke-dashoffset 0.6s ease-in-out;
    }

    /* Compact table rows */
    tbody tr {
        transition: background-color 0.15s ease;
    }
</style>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>