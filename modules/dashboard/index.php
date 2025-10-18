<?php

/**
 * Dashboard Utama
 * Menampilkan overview proyek, progress divisi, deadline, dan aktivitas
 */

// Load configurations
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication
require_login();

// Get database connection
$conn = getDBConnection();

// Get dashboard data
$stats = get_dashboard_stats($conn);
$division_progress = get_division_progress($conn);
$upcoming_deadlines = get_upcoming_deadlines($conn, 7);
$recent_projects = get_recent_projects($conn, 3);
$recent_activities = get_recent_activities($conn, 10);
$top_projects = get_top_projects_by_weight($conn, 4);

// Calculate percentage
$completion_rate = $stats['total_projects'] > 0
    ? round(($stats['completed_projects'] / $stats['total_projects']) * 100, 1)
    : 0;

$page_title = "Dashboard";
include '../../includes/header.php';
?>

<div class="flex">

    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Dashboard</h1>
            <p class="text-gray-400">
                <span class="text-blue-400"><?php echo $_SESSION['full_name']; ?></span> ·
                Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12 ·
                <?php echo date('d/m/Y, H:i:s') . ' WIB'; ?>
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Total Projects -->
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-6 card-hover shadow-xl border-t-4 border-blue-400">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-blue-100 text-sm mb-1">Total Projects</p>
                        <h2 class="text-4xl font-bold text-white mb-1"><?php echo $stats['total_projects']; ?></h2>
                        <p class="text-blue-200 text-xs">
                            <i class="fas fa-info-circle"></i> <?php echo $stats['active_projects']; ?> aktif
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-folder-open text-white text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Completed -->
            <div class="bg-gradient-to-br from-green-600 to-green-700 rounded-xl p-6 card-hover shadow-xl border-t-4 border-green-400">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-green-100 text-sm mb-1">Completed</p>
                        <h2 class="text-4xl font-bold text-white mb-1"><?php echo $stats['completed_projects']; ?></h2>
                        <p class="text-green-200 text-xs">
                            <i class="fas fa-check-circle"></i> <?php echo $completion_rate; ?>% rate
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-check-double text-white text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Delayed -->
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 card-hover shadow-xl border-t-4 border-orange-400">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-orange-100 text-sm mb-1">Delayed</p>
                        <h2 class="text-4xl font-bold text-white mb-1"><?php echo $stats['delayed_projects']; ?></h2>
                        <p class="text-orange-200 text-xs">
                            <i class="fas fa-exclamation-triangle"></i> Perlu perhatian
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-clock text-white text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Weight -->
            <div class="bg-gradient-to-br from-red-600 to-red-700 rounded-xl p-6 card-hover shadow-xl border-t-4 border-red-400">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-red-100 text-sm mb-1">Total Weight (Fabrikasi + Logistik)</p>
                        <h2 class="text-4xl font-bold text-white mb-1"><?php echo number_format($stats['total_weight'], 2); ?></h2>
                        <p class="text-red-200 text-xs">
                            <i class="fas fa-industry"></i> Fabrikasi: <?php echo number_format($stats['fabrication_weight'], 2); ?> ton
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-weight text-white text-2xl"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Progress Divisi Terintegrasi -->
        <div class="bg-dark-light rounded-xl p-6 shadow-xl mb-8">
            <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-700 pb-3">
                <i class="fas fa-chart-line text-blue-400 mr-2"></i>
                Progres Divisi Terintegrasi
            </h2>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">

                <?php foreach ($division_progress as $division => $data): ?>
                    <div class="text-center">
                        <!-- Circular Progress -->
                        <div class="relative inline-flex items-center justify-center">
                            <svg class="w-32 h-32 transform -rotate-90">
                                <!-- Background Circle -->
                                <circle
                                    cx="64" cy="64" r="56"
                                    stroke="#1e293b"
                                    stroke-width="8"
                                    fill="none" />
                                <!-- Progress Circle -->
                                <circle
                                    cx="64" cy="64" r="56"
                                    stroke="<?php
                                            $color = '#3b82f6'; // Default blue
                                            if ($division == 'Engineering') $color = '#3b82f6';
                                            if ($division == 'Purchasing') $color = '#8b5cf6';
                                            if ($division == 'Fabrikasi') $color = '#f59e0b';
                                            if ($division == 'Logistik') $color = '#10b981';
                                            echo $color;
                                            ?>"
                                    stroke-width="8"
                                    fill="none"
                                    stroke-dasharray="<?php echo 2 * pi() * 56; ?>"
                                    stroke-dashoffset="<?php echo 2 * pi() * 56 * (1 - $data['progress_percentage'] / 100); ?>"
                                    stroke-linecap="round"
                                    class="transition-all duration-1000" />
                            </svg>
                            <!-- Percentage Text -->
                            <div class="absolute">
                                <span class="text-3xl font-bold text-white">
                                    <?php echo $data['progress_percentage']; ?>%
                                </span>
                            </div>
                        </div>

                        <!-- Division Name -->
                        <h3 class="text-lg font-semibold text-white mt-4 mb-1">
                            <?php echo $division; ?>
                        </h3>
                        <p class="text-sm text-gray-400">
                            <?php echo $data['completed_tasks']; ?>/<?php echo $data['total_tasks']; ?> tasks
                        </p>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- Deadline Mendatang & Proyek Terbaru -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Deadline Mendatang (7 Hari) -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-calendar-alt text-yellow-400 mr-2"></i>
                    Deadline Mendatang (7 Hari)
                </h2>

                <div class="space-y-3">
                    <?php if (empty($upcoming_deadlines)): ?>
                        <p class="text-gray-500 text-center py-4">Tidak ada deadline dalam 7 hari ke depan</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_deadlines as $deadline): ?>
                            <?php
                            $days_left = days_difference($deadline['finish_date']);
                            $urgency_color = $days_left <= 3 ? 'bg-red-600' : 'bg-yellow-600';
                            ?>
                            <div class="bg-gray-800 rounded-lg p-4 hover:bg-gray-750 transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="text-xs font-bold px-2 py-1 rounded <?php echo $urgency_color; ?> text-white">
                                                <?php echo $deadline['phase']; ?>
                                            </span>
                                            <span class="text-xs text-gray-400"><?php echo $deadline['pon_number']; ?></span>
                                        </div>
                                        <h4 class="font-semibold text-white text-sm mb-1">
                                            <?php echo htmlspecialchars($deadline['task_name']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-400">
                                            <?php echo htmlspecialchars(substr($deadline['project_name'], 0, 60)); ?>...
                                        </p>
                                    </div>
                                    <div class="text-right ml-4">
                                        <p class="text-sm font-bold <?php echo $days_left <= 3 ? 'text-red-400' : 'text-yellow-400'; ?>">
                                            <?php echo $days_left; ?> hari
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo format_date_indo($deadline['finish_date']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Proyek Terbaru -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-folder-plus text-blue-400 mr-2"></i>
                    Proyek Terbaru
                </h2>

                <div class="space-y-4">
                    <?php if (empty($recent_projects)): ?>
                        <p class="text-gray-500 text-center py-4">Belum ada proyek</p>
                    <?php else: ?>
                        <?php foreach ($recent_projects as $project): ?>
                            <?php
                            $status_color = get_status_color($project['status']);
                            $progress = round($project['overall_progress'] ?? 0, 0);
                            ?>
                            <div class="bg-gray-800 rounded-lg p-4 hover:bg-gray-750 transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="text-xs font-mono font-bold text-blue-400">
                                                <?php echo $project['pon_number']; ?>
                                            </span>
                                            <span class="text-xs px-2 py-1 rounded <?php echo $status_color; ?> text-white">
                                                <?php echo $project['status']; ?>
                                            </span>
                                        </div>
                                        <h4 class="font-semibold text-white text-sm mb-1">
                                            <?php echo htmlspecialchars($project['subject']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-400">
                                            <?php echo htmlspecialchars($project['client_name']); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-white"><?php echo $progress; ?>%</p>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                        style="width: <?php echo $progress; ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Berat Proyek Terbanyak & Riwayat Aktivitas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Berat Proyek Terbanyak -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-weight-hanging text-purple-400 mr-2"></i>
                    Berat Proyek Terbanyak
                </h2>

                <div class="space-y-3">
                    <?php if (empty($top_projects)): ?>
                        <p class="text-gray-500 text-center py-4">Belum ada data</p>
                    <?php else: ?>
                        <?php
                        $max_weight = max(array_column($top_projects, 'total_weight'));
                        $rank = 1;
                        foreach ($top_projects as $project):
                            $weight_percentage = $max_weight > 0 ? ($project['total_weight'] / $max_weight) * 100 : 0;
                        ?>
                            <div class="bg-gray-800 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-xl font-bold text-blue-400">#<?php echo $rank++; ?></span>
                                        <div>
                                            <h4 class="font-semibold text-white text-sm">
                                                <?php echo $project['pon_number']; ?>
                                            </h4>
                                            <p class="text-xs text-gray-400">
                                                <?php echo htmlspecialchars(substr($project['project_name'], 0, 40)); ?>...
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-white">
                                            <?php echo number_format($project['total_weight'], 2); ?>
                                        </p>
                                        <p class="text-xs text-gray-400">ton</p>
                                    </div>
                                </div>

                                <!-- Weight Bar -->
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full"
                                        style="width: <?php echo $weight_percentage; ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Riwayat Aktivitas -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-history text-green-400 mr-2"></i>
                    Riwayat Aktivitas
                </h2>

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-gray-500 text-center py-4">Belum ada aktivitas</p>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <?php
                            $time_diff = time() - strtotime($activity['created_at']);
                            $time_ago = '';
                            if ($time_diff < 60) $time_ago = 'baru saja';
                            elseif ($time_diff < 3600) $time_ago = floor($time_diff / 60) . ' menit lalu';
                            elseif ($time_diff < 86400) $time_ago = floor($time_diff / 3600) . ' jam lalu';
                            else $time_ago = floor($time_diff / 86400) . ' hari lalu';

                            // Icon berdasarkan action
                            $icon = 'fa-circle';
                            $icon_color = 'text-gray-400';
                            if (strpos($activity['action'], 'Login') !== false) {
                                $icon = 'fa-sign-in-alt';
                                $icon_color = 'text-green-400';
                            } elseif (strpos($activity['action'], 'Created') !== false) {
                                $icon = 'fa-plus-circle';
                                $icon_color = 'text-blue-400';
                            } elseif (strpos($activity['action'], 'Updated') !== false) {
                                $icon = 'fa-edit';
                                $icon_color = 'text-yellow-400';
                            } elseif (strpos($activity['action'], 'Completed') !== false) {
                                $icon = 'fa-check-circle';
                                $icon_color = 'text-green-400';
                            }
                            ?>
                            <div class="flex items-start space-x-3 p-3 bg-gray-800 rounded-lg hover:bg-gray-750 transition">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas <?php echo $icon; ?> <?php echo $icon_color; ?>"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-white">
                                        <span class="font-semibold text-blue-400"><?php echo $activity['full_name']; ?></span>
                                        <span class="text-gray-300"> <?php echo $activity['description']; ?></span>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo $time_ago; ?> · <?php echo $activity['role']; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>

</div>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>