<?php

/**
 * PON Detail Page - WITH DIVISION TIMELINE DISPLAY
 * Menampilkan informasi lengkap PON dengan timeline visualization
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid PON ID");
}

$pon_id = (int)$_GET['id'];
$conn = getDBConnection();

// Get PON data
$stmt = $conn->prepare("SELECT p.*, u.full_name as created_by_name 
                        FROM pon p 
                        LEFT JOIN users u ON p.created_by = u.user_id 
                        WHERE p.pon_id = ?");
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$result = $stmt->get_result();
$pon = $result->fetch_assoc();

if (!$pon) {
    die("PON tidak ditemukan");
}

// Get tasks untuk PON ini
$tasks_query = "SELECT 
                    t.*,
                    u.full_name as assigned_to_name,
                    (SELECT COUNT(*) FROM qc_documents WHERE task_id = t.task_id) as doc_count
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.user_id
                WHERE t.pon_id = ?
                ORDER BY t.start_date ASC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$tasks_result = $stmt->get_result();
$tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $tasks[] = $row;
}

// Calculate overall progress
$total_progress = 0;
$task_count = count($tasks);
if ($task_count > 0) {
    foreach ($tasks as $task) {
        $total_progress += $task['progress'];
    }
    $overall_progress = round($total_progress / $task_count, 2);
} else {
    $overall_progress = 0;
}

// Get QC documents count
$qc_query = "SELECT COUNT(*) as total FROM qc_documents WHERE pon_id = ?";
$stmt = $conn->prepare($qc_query);
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$qc_result = $stmt->get_result();
$qc_data = $qc_result->fetch_assoc();
$total_qc_docs = $qc_data['total'];

// Get material orders
$material_query = "SELECT * FROM material_orders WHERE pon_id = ? ORDER BY order_date DESC";
$stmt = $conn->prepare($material_query);
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$material_result = $stmt->get_result();
$materials = [];
while ($row = $material_result->fetch_assoc()) {
    $materials[] = $row;
}

// Calculate timeline span untuk Gantt chart
$earliest_date = null;
$latest_date = null;
foreach ($tasks as $task) {
    if (!$earliest_date || strtotime($task['start_date']) < strtotime($earliest_date)) {
        $earliest_date = $task['start_date'];
    }
    if (!$latest_date || strtotime($task['finish_date']) > strtotime($latest_date)) {
        $latest_date = $task['finish_date'];
    }
}

// Hitung total days untuk timeline
if ($earliest_date && $latest_date) {
    $timeline_start = new DateTime($earliest_date);
    $timeline_end = new DateTime($latest_date);
    $total_days = $timeline_start->diff($timeline_end)->days;
} else {
    $total_days = 0;
}

$page_title = "PON Detail - " . $pon['pon_number'];
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
                    <div class="flex items-center space-x-3 mb-2">
                        <h1 class="text-3xl font-bold text-white">
                            PON: <?php echo htmlspecialchars($pon['pon_number']); ?>
                        </h1>
                        <span class="px-4 py-1 rounded-full text-sm font-bold <?php echo get_status_color($pon['status']); ?> text-white">
                            <?php echo $pon['status']; ?>
                        </span>
                    </div>
                    <p class="text-gray-400"><?php echo htmlspecialchars($pon['subject']); ?></p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="edit.php?id=<?php echo $pon_id; ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </a>
                    <a href="list.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-900 border-l-4 border-green-500 text-green-200 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <p><?php echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

            <!-- Overall Progress -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Overall Progress</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $overall_progress; ?>%</h2>
                    </div>
                    <div class="bg-blue-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-chart-line text-blue-400 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Tasks -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Total Tasks</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $task_count; ?></h2>
                        <p class="text-green-400 text-xs mt-1">
                            <?php
                            $completed = array_filter($tasks, function ($t) {
                                return $t['status'] == 'Completed';
                            });
                            echo count($completed);
                            ?> completed
                        </p>
                    </div>
                    <div class="bg-green-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-tasks text-green-400 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- QC Documents -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">QC Documents</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $total_qc_docs; ?></h2>
                        <p class="text-purple-400 text-xs mt-1">files uploaded</p>
                    </div>
                    <div class="bg-purple-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-file-alt text-purple-400 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Days Remaining -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Project Duration</p>
                        <h2 class="text-4xl font-bold text-white"><?php echo $total_days; ?></h2>
                        <p class="text-orange-400 text-xs mt-1">days timeline</p>
                    </div>
                    <div class="bg-orange-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-calendar-alt text-orange-400 text-3xl"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Project Information -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- Basic Info -->
            <div class="lg:col-span-2 bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    Project Information
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">PON Number</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['pon_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Offer Number</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['offer_number'] ?? '-'); ?></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-gray-400 text-sm mb-1">Project Name</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['project_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">QTY / Configuration</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['qty_configuration'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Scope of Work</p>
                        <p class="text-white font-semibold text-sm"><?php echo htmlspecialchars($pon['scope_of_work'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Project Manager</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['project_manager'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Created By</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['created_by_name'] ?? '-'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Client Info -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-building text-green-400 mr-2"></i>
                    Client Details
                </h2>

                <div class="space-y-4">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Client Name</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['client_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Director</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['director_name'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">PIC</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['pic_name'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Contact</p>
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($pon['pic_contact'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Contract Number</p>
                        <p class="text-white font-semibold text-sm"><?php echo htmlspecialchars($pon['contract_number'] ?? '-'); ?></p>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================================================ -->
        <!-- NEW SECTION: Division Timeline & Deadline Display -->
        <!-- ============================================================ -->

        <!-- Division Timeline & Deadlines -->
        <div class="bg-gradient-to-br from-indigo-900 to-purple-900 rounded-xl p-6 shadow-xl border-2 border-indigo-500 mb-8">
            <div class="flex items-center justify-between mb-6 border-b border-indigo-400 pb-3">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-clock text-yellow-400 mr-2"></i>
                    Division Timeline & Deadlines
                </h2>
                <?php if (hasRole('Admin')): ?>
                    <a href="edit.php?id=<?php echo $pon_id; ?>#timeline-section"
                        class="bg-yellow-500 hover:bg-yellow-600 text-black px-4 py-2 rounded-lg flex items-center space-x-2 text-sm font-bold transition">
                        <i class="fas fa-edit"></i>
                        <span>Edit Timeline</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Engineering Division Timeline -->
                <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-blue-400 flex items-center">
                            <i class="fas fa-drafting-compass mr-2"></i>
                            Engineering
                        </h3>
                        <?php
                        // Check if deadline is near (7 days) or passed
                        $eng_deadline_status = '';
                        $eng_badge_class = 'bg-gray-600';

                        if (!empty($pon['engineering_finish_date'])) {
                            $days_remaining = days_difference($pon['engineering_finish_date']);

                            if ($days_remaining < 0) {
                                $eng_deadline_status = 'OVERDUE';
                                $eng_badge_class = 'bg-red-600 animate-pulse';
                            } elseif ($days_remaining <= 7) {
                                $eng_deadline_status = 'URGENT';
                                $eng_badge_class = 'bg-orange-600';
                            } else {
                                $eng_deadline_status = 'ON TRACK';
                                $eng_badge_class = 'bg-green-600';
                            }
                        }
                        ?>
                        <?php if ($eng_deadline_status): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white <?php echo $eng_badge_class; ?>">
                                <?php echo $eng_deadline_status; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3">
                        <!-- Start Date -->
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-alt text-blue-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Start Date</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    echo !empty($pon['engineering_start_date'])
                                        ? format_date_indo($pon['engineering_start_date'])
                                        : '<span class="text-gray-500">TBA</span>';
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- Finish Date / Deadline -->
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-check text-blue-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Deadline</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    if (!empty($pon['engineering_finish_date'])) {
                                        echo format_date_indo($pon['engineering_finish_date']);

                                        // Show days remaining
                                        $days_remaining = days_difference($pon['engineering_finish_date']);
                                        if ($days_remaining >= 0) {
                                            echo ' <span class="text-xs text-gray-400">(' . $days_remaining . ' days left)</span>';
                                        } else {
                                            echo ' <span class="text-xs text-red-400">(' . abs($days_remaining) . ' days overdue)</span>';
                                        }
                                    } else {
                                        echo '<span class="text-gray-500">TBA</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- PIC -->
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-500 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-user text-blue-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">PIC</p>
                                <p class="text-white font-semibold">
                                    <?php echo htmlspecialchars($pon['engineering_pic'] ?? 'N/A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchasing Division Timeline -->
                <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border-l-4 border-green-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-green-400 flex items-center">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Purchasing
                        </h3>
                        <?php
                        $pur_deadline_status = '';
                        $pur_badge_class = 'bg-gray-600';

                        if (!empty($pon['purchasing_finish_date'])) {
                            $days_remaining = days_difference($pon['purchasing_finish_date']);

                            if ($days_remaining < 0) {
                                $pur_deadline_status = 'OVERDUE';
                                $pur_badge_class = 'bg-red-600 animate-pulse';
                            } elseif ($days_remaining <= 7) {
                                $pur_deadline_status = 'URGENT';
                                $pur_badge_class = 'bg-orange-600';
                            } else {
                                $pur_deadline_status = 'ON TRACK';
                                $pur_badge_class = 'bg-green-600';
                            }
                        }
                        ?>
                        <?php if ($pur_deadline_status): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white <?php echo $pur_badge_class; ?>">
                                <?php echo $pur_deadline_status; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <div class="bg-green-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-alt text-green-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Start Date</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    echo !empty($pon['purchasing_start_date'])
                                        ? format_date_indo($pon['purchasing_start_date'])
                                        : '<span class="text-gray-500">TBA</span>';
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="bg-green-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-check text-green-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Deadline</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    if (!empty($pon['purchasing_finish_date'])) {
                                        echo format_date_indo($pon['purchasing_finish_date']);

                                        $days_remaining = days_difference($pon['purchasing_finish_date']);
                                        if ($days_remaining >= 0) {
                                            echo ' <span class="text-xs text-gray-400">(' . $days_remaining . ' days left)</span>';
                                        } else {
                                            echo ' <span class="text-xs text-red-400">(' . abs($days_remaining) . ' days overdue)</span>';
                                        }
                                    } else {
                                        echo '<span class="text-gray-500">TBA</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="bg-green-500 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-user text-green-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">PIC</p>
                                <p class="text-white font-semibold">
                                    <?php echo htmlspecialchars($pon['purchasing_pic'] ?? 'N/A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fabrikasi Division Timeline -->
                <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-orange-400 flex items-center">
                            <i class="fas fa-hammer mr-2"></i>
                            Fabrikasi
                        </h3>
                        <?php
                        $fab_deadline_status = '';
                        $fab_badge_class = 'bg-gray-600';

                        if (!empty($pon['fabrikasi_finish_date'])) {
                            $days_remaining = days_difference($pon['fabrikasi_finish_date']);

                            if ($days_remaining < 0) {
                                $fab_deadline_status = 'OVERDUE';
                                $fab_badge_class = 'bg-red-600 animate-pulse';
                            } elseif ($days_remaining <= 7) {
                                $fab_deadline_status = 'URGENT';
                                $fab_badge_class = 'bg-orange-600';
                            } else {
                                $fab_deadline_status = 'ON TRACK';
                                $fab_badge_class = 'bg-green-600';
                            }
                        }
                        ?>
                        <?php if ($fab_deadline_status): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white <?php echo $fab_badge_class; ?>">
                                <?php echo $fab_deadline_status; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <div class="bg-orange-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-alt text-orange-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Start Date</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    echo !empty($pon['fabrikasi_start_date'])
                                        ? format_date_indo($pon['fabrikasi_start_date'])
                                        : '<span class="text-gray-500">TBA</span>';
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="bg-orange-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-check text-orange-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Deadline</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    if (!empty($pon['fabrikasi_finish_date'])) {
                                        echo format_date_indo($pon['fabrikasi_finish_date']);

                                        $days_remaining = days_difference($pon['fabrikasi_finish_date']);
                                        if ($days_remaining >= 0) {
                                            echo ' <span class="text-xs text-gray-400">(' . $days_remaining . ' days left)</span>';
                                        } else {
                                            echo ' <span class="text-xs text-red-400">(' . abs($days_remaining) . ' days overdue)</span>';
                                        }
                                    } else {
                                        echo '<span class="text-gray-500">TBA</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="bg-orange-500 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-user text-orange-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">PIC</p>
                                <p class="text-white font-semibold">
                                    <?php echo htmlspecialchars($pon['fabrikasi_pic'] ?? 'N/A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logistik Division Timeline -->
                <div class="bg-gray-800 bg-opacity-50 rounded-lg p-5 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-purple-400 flex items-center">
                            <i class="fas fa-truck mr-2"></i>
                            Logistik
                        </h3>
                        <?php
                        $log_deadline_status = '';
                        $log_badge_class = 'bg-gray-600';

                        if (!empty($pon['logistik_finish_date'])) {
                            $days_remaining = days_difference($pon['logistik_finish_date']);

                            if ($days_remaining < 0) {
                                $log_deadline_status = 'OVERDUE';
                                $log_badge_class = 'bg-red-600 animate-pulse';
                            } elseif ($days_remaining <= 7) {
                                $log_deadline_status = 'URGENT';
                                $log_badge_class = 'bg-orange-600';
                            } else {
                                $log_deadline_status = 'ON TRACK';
                                $log_badge_class = 'bg-green-600';
                            }
                        }
                        ?>
                        <?php if ($log_deadline_status): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white <?php echo $log_badge_class; ?>">
                                <?php echo $log_deadline_status; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <div class="bg-purple-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-alt text-purple-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Start Date</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    echo !empty($pon['logistik_start_date'])
                                        ? format_date_indo($pon['logistik_start_date'])
                                        : '<span class="text-gray-500">TBA</span>';
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="bg-purple-500 bg-opacity-20 rounded-lg p-2">
                                <i class="far fa-calendar-check text-purple-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Deadline</p>
                                <p class="text-white font-semibold">
                                    <?php
                                    if (!empty($pon['logistik_finish_date'])) {
                                        echo format_date_indo($pon['logistik_finish_date']);

                                        $days_remaining = days_difference($pon['logistik_finish_date']);
                                        if ($days_remaining >= 0) {
                                            echo ' <span class="text-xs text-gray-400">(' . $days_remaining . ' days left)</span>';
                                        } else {
                                            echo ' <span class="text-xs text-red-400">(' . abs($days_remaining) . ' days overdue)</span>';
                                        }
                                    } else {
                                        echo '<span class="text-gray-500">TBA</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <div class="bg-purple-500 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-user text-purple-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">PIC</p>
                                <p class="text-white font-semibold">
                                    <?php echo htmlspecialchars($pon['logistik_pic'] ?? 'N/A'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Timeline Summary Bar (Optional - Visual Progress) -->
            <div class="mt-6 bg-gray-800 bg-opacity-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-400 mb-3">Timeline Overview</h4>
                <div class="grid grid-cols-4 gap-2">
                    <?php
                    $divisions = [
                        ['name' => 'Engineering', 'date' => $pon['engineering_finish_date'], 'color' => 'blue'],
                        ['name' => 'Purchasing', 'date' => $pon['purchasing_finish_date'], 'color' => 'green'],
                        ['name' => 'Fabrikasi', 'date' => $pon['fabrikasi_finish_date'], 'color' => 'orange'],
                        ['name' => 'Logistik', 'date' => $pon['logistik_finish_date'], 'color' => 'purple']
                    ];

                    foreach ($divisions as $div):
                        $status_icon = '⏱️';
                        $status_text = 'TBA';

                        if (!empty($div['date'])) {
                            $days = days_difference($div['date']);
                            if ($days < 0) {
                                $status_icon = '🔴';
                                $status_text = 'Overdue';
                            } elseif ($days <= 7) {
                                $status_icon = '🟡';
                                $status_text = 'Urgent';
                            } else {
                                $status_icon = '🟢';
                                $status_text = 'On Track';
                            }
                        }
                    ?>
                        <div class="bg-gray-700 rounded p-2 text-center">
                            <div class="text-2xl mb-1"><?php echo $status_icon; ?></div>
                            <p class="text-xs text-gray-400"><?php echo $div['name']; ?></p>
                            <p class="text-xs font-semibold text-white"><?php echo $status_text; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Admin Note -->
            <?php if (!hasRole('Admin')): ?>
                <div class="mt-4 bg-blue-900 bg-opacity-30 border-l-4 border-blue-500 p-3 rounded">
                    <p class="text-blue-200 text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        Timeline ini adalah <strong>deadline reminder</strong> untuk divisi Anda. Hubungi Admin jika ada perubahan jadwal.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- END NEW SECTION: Division Timeline -->

        <!-- Timeline Visualization (Gantt-like) -->
        <div class="bg-dark-light rounded-xl p-6 shadow-xl mb-8">
            <div class="flex items-center justify-between mb-6 border-b border-gray-700 pb-3">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-chart-bar text-yellow-400 mr-2"></i>
                    Project Timeline
                </h2>
                <div class="flex items-center space-x-4 text-sm">
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-blue-500 rounded"></div>
                        <span class="text-gray-400">In Progress</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-green-500 rounded"></div>
                        <span class="text-gray-400">Completed</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-red-500 rounded"></div>
                        <span class="text-gray-400">Delayed</span>
                    </div>
                </div>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-calendar-times text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400">Belum ada task untuk PON ini</p>
                    <a href="../tasks/manage.php?pon_id=<?php echo $pon_id; ?>" class="text-blue-400 hover:text-blue-300 mt-2 inline-block">
                        <i class="fas fa-plus-circle"></i> Tambah Task
                    </a>
                </div>
            <?php else: ?>

                <!-- Timeline Header (Months) -->
                <div class="mb-4">
                    <div class="flex items-center space-x-2 text-xs text-gray-500">
                        <div class="w-48"></div>
                        <?php
                        // Generate month headers
                        $current_date = new DateTime($earliest_date);
                        $end_date = new DateTime($latest_date);
                        $months = [];

                        while ($current_date <= $end_date) {
                            $month_key = $current_date->format('Y-m');
                            if (!in_array($month_key, $months)) {
                                $months[] = $month_key;
                                echo '<div class="flex-1 text-center font-semibold text-gray-400">';
                                echo $current_date->format('M Y');
                                echo '</div>';
                            }
                            $current_date->modify('+1 month');
                        }
                        ?>
                    </div>
                </div>

                <!-- Tasks Timeline -->
                <div class="space-y-2">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        // Calculate position and width
                        $task_start = new DateTime($task['start_date']);
                        $task_end = new DateTime($task['finish_date']);
                        $task_duration = $task_start->diff($task_end)->days;

                        $start_offset = $timeline_start->diff($task_start)->days;
                        $left_percent = ($start_offset / $total_days) * 100;
                        $width_percent = ($task_duration / $total_days) * 100;

                        // Determine color based on status
                        $bar_color = 'bg-blue-500';
                        if ($task['status'] == 'Completed') {
                            $bar_color = 'bg-green-500';
                        } elseif (strtotime($task['finish_date']) < time() && $task['status'] != 'Completed') {
                            $bar_color = 'bg-red-500';
                        }
                        ?>

                        <div class="flex items-center">
                            <!-- Task Name -->
                            <div class="w-48 pr-4">
                                <p class="text-sm font-semibold text-white truncate" title="<?php echo htmlspecialchars($task['task_name']); ?>">
                                    <?php echo htmlspecialchars($task['task_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo $task['phase']; ?></p>
                            </div>

                            <!-- Timeline Bar -->
                            <div class="flex-1 relative h-10 bg-gray-800 rounded-lg">
                                <div
                                    class="absolute h-full <?php echo $bar_color; ?> rounded-lg flex items-center justify-center transition-all hover:opacity-80 cursor-pointer group"
                                    style="left: <?php echo $left_percent; ?>%; width: <?php echo max($width_percent, 2); ?>%;"
                                    title="<?php echo format_date_indo($task['start_date']) . ' - ' . format_date_indo($task['finish_date']); ?>">
                                    <span class="text-xs font-bold text-white"><?php echo round($task['progress']); ?>%</span>

                                    <!-- Tooltip on hover -->
                                    <div class="absolute bottom-full mb-2 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-nowrap z-10">
                                        <p class="font-semibold mb-1"><?php echo htmlspecialchars($task['task_name']); ?></p>
                                        <p><?php echo format_date_indo($task['start_date']); ?> - <?php echo format_date_indo($task['finish_date']); ?></p>
                                        <p>Progress: <?php echo round($task['progress']); ?>%</p>
                                        <p>Status: <?php echo $task['status']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Today Marker -->
                <?php
                $today = new DateTime();
                if ($today >= $timeline_start && $today <= $timeline_end) {
                    $today_offset = $timeline_start->diff($today)->days;
                    $today_percent = ($today_offset / $total_days) * 100;
                ?>
                    <div class="relative mt-4" style="margin-left: 12rem;">
                        <div class="absolute h-full border-l-2 border-red-400 border-dashed" style="left: <?php echo $today_percent; ?>%;">
                            <span class="absolute top-0 -translate-x-1/2 bg-red-500 text-white text-xs px-2 py-1 rounded whitespace-nowrap">
                                Today
                            </span>
                        </div>
                    </div>
                <?php } ?>

            <?php endif; ?>
        </div>

        <!-- Tasks Detail Table -->
        <div class="bg-dark-light rounded-xl shadow-xl mb-8">
            <div class="p-6 border-b border-gray-700">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-list text-blue-400 mr-2"></i>
                        Tasks Detail
                    </h2>
                    <?php if (hasAnyRole(['Admin'])): ?>
                        <a href="../tasks/manage.php?pon_id=<?php echo $pon_id; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Task</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800 text-gray-400 text-sm">
                        <tr>
                            <th class="px-6 py-4 text-left">Phase</th>
                            <th class="px-6 py-4 text-left">Task Name</th>
                            <th class="px-6 py-4 text-left">Division</th>
                            <th class="px-6 py-4 text-left">PIC</th>
                            <th class="px-6 py-4 text-left">Timeline</th>
                            <th class="px-6 py-4 text-center">Progress</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Documents</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    Belum ada task
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr class="hover:bg-gray-800 transition">
                                    <!-- Phase -->
                                    <td class="px-6 py-4">
                                        <span class="text-xs px-3 py-1 rounded-full bg-purple-900 text-purple-200 font-semibold">
                                            <?php echo $task['phase']; ?>
                                        </span>
                                    </td>

                                    <!-- Task Name -->
                                    <td class="px-6 py-4">
                                        <p class="text-white font-semibold"><?php echo htmlspecialchars($task['task_name']); ?></p>
                                        <?php if (!empty($task['description'])): ?>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?>...</p>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Division -->
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-300"><?php echo $task['responsible_division']; ?></span>
                                    </td>

                                    <!-- PIC -->
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-white"><?php echo htmlspecialchars($task['pic_internal'] ?? '-'); ?></p>
                                        <?php if (!empty($task['assigned_to_name'])): ?>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($task['assigned_to_name']); ?></p>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Timeline -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <p class="text-gray-400">Start: <span class="text-white"><?php echo format_date_indo($task['start_date']); ?></span></p>
                                            <p class="text-gray-400">End: <span class="text-white"><?php echo format_date_indo($task['finish_date']); ?></span></p>
                                            <?php
                                            $days_left = days_difference($task['finish_date']);
                                            if ($task['status'] != 'Completed' && $days_left < 0): ?>
                                                <p class="text-red-400 text-xs mt-1">
                                                    <i class="fas fa-exclamation-triangle"></i> <?php echo abs($days_left); ?> days overdue
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Progress -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center">
                                            <div class="relative w-16 h-16">
                                                <svg class="w-16 h-16 transform -rotate-90">
                                                    <circle cx="32" cy="32" r="28" stroke="#1e293b" stroke-width="4" fill="none" />
                                                    <circle
                                                        cx="32" cy="32" r="28"
                                                        stroke="<?php echo $task['status'] == 'Completed' ? '#10b981' : '#3b82f6'; ?>"
                                                        stroke-width="4"
                                                        fill="none"
                                                        stroke-dasharray="<?php echo 2 * pi() * 28; ?>"
                                                        stroke-dashoffset="<?php echo 2 * pi() * 28 * (1 - $task['progress'] / 100); ?>"
                                                        stroke-linecap="round" />
                                                </svg>
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="text-sm font-bold text-white"><?php echo round($task['progress']); ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo get_status_color($task['status']); ?> text-white">
                                            <?php echo $task['status']; ?>
                                        </span>
                                    </td>

                                    <!-- Documents -->
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($task['doc_count'] > 0): ?>
                                            <a href="../qc/upload.php?task_id=<?php echo $task['task_id']; ?>" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-file-alt"></i> <?php echo $task['doc_count']; ?> docs
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Material Orders & QA Requirements -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Material Orders -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-boxes text-orange-400 mr-2"></i>
                    Material Orders
                </h2>

                <?php if (empty($materials)): ?>
                    <p class="text-gray-500 text-center py-4">Belum ada data material orders</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($materials as $material): ?>
                            <div class="bg-gray-800 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <p class="text-white font-semibold"><?php echo $material['material_type']; ?></p>
                                        <p class="text-sm text-gray-400">Supplier: <?php echo htmlspecialchars($material['supplier_name']); ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $material['status'] == 'Received' ? 'bg-green-600' : ($material['status'] == 'Ordered' ? 'bg-blue-600' : 'bg-gray-600'); ?> text-white">
                                        <?php echo $material['status']; ?>
                                    </span>
                                </div>
                                <?php if ($material['order_date']): ?>
                                    <p class="text-xs text-gray-500">Order: <?php echo format_date_indo($material['order_date']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quality Assurance Requirements -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-clipboard-check text-green-400 mr-2"></i>
                    QA Requirements
                </h2>

                <div class="grid grid-cols-2 gap-3">
                    <?php
                    $qa_requirements = [
                        'require_wps_pqr' => 'WPS/PQR',
                        'require_galvanizing_cert' => 'Galvanizing Cert',
                        'require_mill_cert_sm490' => 'Mill Cert SM490',
                        'require_inspection_report' => 'Inspection Report',
                        'require_mill_cert_deck' => 'Mill Cert Deck',
                        'require_visual_welding' => 'Visual Welding',
                        'require_mill_cert_pipe' => 'Mill Cert Pipe',
                        'require_dimensional_report' => 'Dimensional Report'
                    ];

                    foreach ($qa_requirements as $key => $label):
                        $is_required = $pon[$key] == 'YES';
                    ?>
                        <div class="flex items-center space-x-2 p-3 bg-gray-800 rounded-lg">
                            <?php if ($is_required): ?>
                                <i class="fas fa-check-circle text-green-400"></i>
                                <span class="text-white text-sm"><?php echo $label; ?></span>
                            <?php else: ?>
                                <i class="fas fa-times-circle text-gray-600"></i>
                                <span class="text-gray-500 text-sm"><?php echo $label; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Additional Notes -->
        <?php if (!empty($pon['notes'])): ?>
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-sticky-note text-yellow-400 mr-2"></i>
                    Additional Notes
                </h2>
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($pon['notes']); ?></p>
            </div>
        <?php endif; ?>

    </main>

</div>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>