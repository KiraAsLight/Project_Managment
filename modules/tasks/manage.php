<?php

/**
 * Task Management Page
 * Menampilkan overview semua project dengan task completion tracking
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

$conn = getDBConnection();

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$time_filter = isset($_GET['time']) ? sanitize_input($_GET['time']) : 'all';

// Build query based on filters
$where_conditions = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.pon_number LIKE ? OR p.project_name LIKE ? OR p.client_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get all PON with task statistics
$query = "SELECT 
            p.pon_id,
            p.pon_number,
            p.subject,
            p.project_name,
            p.client_name,
            p.status,
            p.project_start_date,
            p.project_target_date,
            COUNT(t.task_id) as total_tasks,
            SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
            AVG(t.progress) as avg_progress,
            SUM(CASE WHEN t.status IN ('In Progress', 'Not Started', 'On Hold') THEN 1 ELSE 0 END) as active_tasks
          FROM pon p
          LEFT JOIN tasks t ON p.pon_id = t.pon_id
          WHERE " . implode(" AND ", $where_conditions) . "
          GROUP BY p.pon_id
          ORDER BY p.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

// Calculate overview statistics
$total_items = 0;
$completed_items = 0;
$in_progress_items = 0;

foreach ($projects as $project) {
    $total_items += $project['total_tasks'];
    $completed_items += $project['completed_tasks'];
    $in_progress_items += $project['active_tasks'];
}

$completion_rate = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;

$page_title = "Task List";
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
                    <h1 class="text-3xl font-bold text-white mb-2">Task List</h1>
                    <p class="text-gray-400">
                        Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12 ·
                        <?php echo date('d/m/Y, H:i:s') . ' WIB'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Project Overview Cards -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-white">Project Overview</h2>
                <select
                    id="timeFilter"
                    class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    onchange="filterByTime(this.value)">
                    <option value="all" <?php echo $time_filter == 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $time_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $time_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $time_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

                <!-- Total Items -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-blue-500">
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-500 bg-opacity-20 rounded-lg p-3">
                            <i class="fas fa-clipboard-list text-blue-400 text-3xl"></i>
                        </div>
                        <div>
                            <h3 class="text-4xl font-bold text-white mb-1"><?php echo $total_items; ?></h3>
                            <p class="text-gray-400 text-sm">Total Items</p>
                            <p class="text-blue-300 text-xs mt-1">Tasks + Fabrikasi + Logistik</p>
                        </div>
                    </div>
                </div>

                <!-- Completed Items -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-green-500">
                    <div class="flex items-center space-x-4">
                        <div class="bg-green-500 bg-opacity-20 rounded-lg p-3">
                            <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                        </div>
                        <div>
                            <h3 class="text-4xl font-bold text-white mb-1"><?php echo $completed_items; ?></h3>
                            <p class="text-gray-400 text-sm">Completed Items</p>
                            <p class="text-green-300 text-xs mt-1">Done + Progress 100%</p>
                        </div>
                    </div>
                </div>

                <!-- In Progress -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-yellow-500">
                    <div class="flex items-center space-x-4">
                        <div class="bg-yellow-500 bg-opacity-20 rounded-lg p-3">
                            <i class="fas fa-clock text-yellow-400 text-3xl"></i>
                        </div>
                        <div>
                            <h3 class="text-4xl font-bold text-white mb-1"><?php echo $in_progress_items; ?></h3>
                            <p class="text-gray-400 text-sm">Progress</p>
                            <p class="text-yellow-300 text-xs mt-1">Active items</p>
                        </div>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-cyan-500">
                    <div class="flex items-center space-x-4">
                        <div class="bg-cyan-500 bg-opacity-20 rounded-lg p-3">
                            <i class="fas fa-percentage text-cyan-400 text-3xl"></i>
                        </div>
                        <div>
                            <h3 class="text-4xl font-bold text-white mb-1"><?php echo $completion_rate; ?>%</h3>
                            <p class="text-gray-400 text-sm">Completion Rate</p>
                            <p class="text-cyan-300 text-xs mt-1">Based on completed items</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Filters & View Toggle -->
        <div class="mb-6">
            <div class="flex items-center justify-between">

                <!-- Left: Showing count & View Toggle -->
                <div class="flex items-center space-x-4">
                    <p class="text-gray-400">
                        Showing <span class="text-white font-semibold"><?php echo count($projects); ?></span> projects
                    </p>

                    <div class="flex items-center space-x-2 bg-gray-800 rounded-lg p-1">
                        <button
                            id="gridViewBtn"
                            onclick="toggleView('grid')"
                            class="px-3 py-2 rounded bg-blue-600 text-white transition">
                            <i class="fas fa-th"></i>
                        </button>
                        <button
                            id="listViewBtn"
                            onclick="toggleView('list')"
                            class="px-3 py-2 rounded text-gray-400 hover:text-white transition">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>

                <!-- Right: Search & Filters -->
                <div class="flex items-center space-x-3">

                    <!-- Search -->
                    <div class="relative">
                        <input
                            type="text"
                            id="searchInput"
                            placeholder="Search projects..."
                            class="bg-gray-800 text-white px-4 py-2 pl-10 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 w-64"
                            value="<?php echo htmlspecialchars($search); ?>"
                            onkeyup="handleSearch(this.value)">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500"></i>
                    </div>

                    <!-- Status Filter -->
                    <select
                        id="statusFilter"
                        class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        onchange="filterByStatus(this.value)">
                        <option value="all">All Status</option>
                        <option value="Planning" <?php echo $status_filter == 'Planning' ? 'selected' : ''; ?>>Planning</option>
                        <option value="Engineering" <?php echo $status_filter == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                        <option value="Fabrication" <?php echo $status_filter == 'Fabrication' ? 'selected' : ''; ?>>Fabrication</option>
                        <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>

                    <!-- Type Filter -->
                    <select
                        id="typeFilter"
                        class="bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        onchange="filterByType(this.value)">
                        <option value="all">All Types</option>
                        <option value="bridge" <?php echo $type_filter == 'bridge' ? 'selected' : ''; ?>>Bridge</option>
                        <option value="building" <?php echo $type_filter == 'building' ? 'selected' : ''; ?>>Building</option>
                        <option value="structure" <?php echo $type_filter == 'structure' ? 'selected' : ''; ?>>Structure</option>
                    </select>

                </div>

            </div>
        </div>

        <!-- Projects Grid -->
        <div id="projectsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php if (empty($projects)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-folder-open text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400 text-lg">No projects found</p>
                </div>
            <?php else: ?>

                <?php foreach ($projects as $project): ?>
                    <?php
                    $progress = round($project['avg_progress'] ?? 0, 0);
                    $completion_text = $project['completed_tasks'] > 0 && $project['total_tasks'] > 0
                        ? round(($project['completed_tasks'] / $project['total_tasks']) * 100)
                        : 0;

                    // Determine status badge
                    $status_badge = 'ACTIVE';
                    $status_color = 'bg-blue-600';

                    if ($project['status'] == 'Completed' || $completion_text == 100) {
                        $status_badge = 'SELESAI';
                        $status_color = 'bg-green-600';
                    }
                    ?>
                    
                    <!-- Project Card -->
                    <div class="bg-dark-light rounded-xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-gray-800 hover:border-gray-700 overflow-hidden">

                        <!-- Card Header -->
                        <div class="p-6 border-b border-gray-800">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="text-xl font-bold text-white mb-1">
                                        <?php echo htmlspecialchars($project['pon_number']); ?>
                                    </h3>
                                    <p class="text-gray-400 text-sm">
                                        <?php echo htmlspecialchars($project['client_name']); ?>
                                    </p>
                                </div>
                                <button
                                    class="text-gray-400 hover:text-white"
                                    onclick="showProjectInfo('<?php echo $project['pon_id']; ?>')"
                                    title="Project Info">
                                    <i class="fas fa-info-circle text-xl"></i>
                                </button>
                            </div>

                            <?php if ($status_badge == 'SELESAI'): ?>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?php echo $status_color; ?> text-white">
                                    <?php echo $status_badge; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6">

                            <!-- Project Type -->
                            <div class="mb-4">
                                <p class="text-gray-500 text-sm mb-1">Type</p>
                                <p class="text-white font-semibold">
                                    <?php echo htmlspecialchars($project['subject'] ?? 'No Type'); ?>
                                </p>
                            </div>

                            <!-- Timeline -->
                            <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
                                <div>
                                    <div class="flex items-center space-x-2 text-gray-400">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Start: <?php echo format_date_indo($project['project_start_date'] ?? date('Y-m-d')); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2 text-gray-400">
                                        <i class="fas fa-flag-checkered"></i>
                                        <span>Finish: <?php echo $project['project_target_date'] ? format_date_indo($project['project_target_date']) : 'N/A'; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Completion Progress -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-gray-400 text-sm">Completion Progress</span>
                                    <span class="text-white font-bold text-lg"><?php echo $completion_text; ?>%</span>
                                </div>

                                <!-- Progress Bar -->
                                <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-500 <?php echo $completion_text == 100 ? 'bg-green-500' : 'bg-blue-500'; ?>"
                                        style="width: <?php echo $completion_text; ?>%"></div>
                                </div>

                                <p class="text-gray-500 text-xs mt-2">
                                    Based on completed items across all divisions
                                </p>
                            </div>

                        </div>

                        <!-- Card Footer -->
                        <div class="p-6 bg-gray-850 border-t border-gray-800">
                            <a
                                href="../tasks/pon_tasks.php?pon_id=<?php echo $project['pon_id']; ?>"
                                class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 rounded-lg font-semibold transition">
                                <i class="fas fa-eye mr-2"></i>View Details
                            </a>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </main>
</div>

<!-- JavaScript -->
<script>
    // View toggle
    let currentView = 'grid';

    function toggleView(view) {
        currentView = view;
        const container = document.getElementById('projectsContainer');
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');

        if (view === 'grid') {
            container.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6';
            gridBtn.className = 'px-3 py-2 rounded bg-blue-600 text-white transition';
            listBtn.className = 'px-3 py-2 rounded text-gray-400 hover:text-white transition';
        } else {
            container.className = 'space-y-4';
            gridBtn.className = 'px-3 py-2 rounded text-gray-400 hover:text-white transition';
            listBtn.className = 'px-3 py-2 rounded bg-blue-600 text-white transition';
        }
    }

    // Search handler with debounce
    let searchTimeout;

    function handleSearch(value) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const currentUrl = new URL(window.location.href);
            if (value) {
                currentUrl.searchParams.set('search', value);
            } else {
                currentUrl.searchParams.delete('search');
            }
            window.location.href = currentUrl.toString();
        }, 500);
    }

    // Filter handlers
    function filterByStatus(status) {
        const currentUrl = new URL(window.location.href);
        if (status && status !== 'all') {
            currentUrl.searchParams.set('status', status);
        } else {
            currentUrl.searchParams.delete('status');
        }
        window.location.href = currentUrl.toString();
    }

    function filterByType(type) {
        const currentUrl = new URL(window.location.href);
        if (type && type !== 'all') {
            currentUrl.searchParams.set('type', type);
        } else {
            currentUrl.searchParams.delete('type');
        }
        window.location.href = currentUrl.toString();
    }

    function filterByTime(time) {
        const currentUrl = new URL(window.location.href);
        if (time && time !== 'all') {
            currentUrl.searchParams.set('time', time);
        } else {
            currentUrl.searchParams.delete('time');
        }
        window.location.href = currentUrl.toString();
    }

    // Show project info (modal/popup)
    function showProjectInfo(ponId) {
        // Redirect to detail page
        window.location.href = `../pon/detail.php?id=${ponId}`;
    }

    // Auto-refresh setiap 5 menit
    setTimeout(function() {
        location.reload();
    }, 300000);
</script>

<style>
    /* Additional styling untuk hover effects */
    .bg-gray-850 {
        background-color: #1a202e;
    }

    /* Card hover animation */
    .bg-dark-light:hover {
        transform: translateY(-2px);
    }

    /* Smooth progress bar animation */
    .transition-all {
        transition: all 0.3s ease;
    }
</style>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>