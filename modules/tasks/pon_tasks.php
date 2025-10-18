<?php

/**
 * PON Tasks Overview
 * Menampilkan semua tasks dalam 1 PON dengan breakdown per divisi
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['pon_id']) || empty($_GET['pon_id'])) {
    die("Invalid PON ID");
}

$pon_id = (int)$_GET['pon_id'];
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

// Get all tasks untuk PON ini
$tasks_query = "SELECT 
                t.*,
                u.full_name as assigned_to_name
              FROM tasks t
              LEFT JOIN users u ON t.assigned_to = u.user_id
              WHERE t.pon_id = ?
              ORDER BY t.start_date ASC";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$tasks_result = $stmt->get_result();
$all_tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $all_tasks[] = $row;
}

// Calculate statistics
$total_tasks = count($all_tasks);
$completed_tasks = count(array_filter($all_tasks, function ($t) {
    return $t['status'] == 'Completed';
}));
$in_progress_tasks = count(array_filter($all_tasks, function ($t) {
    return $t['status'] == 'In Progress';
}));
$on_hold_tasks = count(array_filter($all_tasks, function ($t) {
    return $t['status'] == 'On Hold';
}));
$waiting_approval = count(array_filter($all_tasks, function ($t) {
    return $t['status'] == 'Not Started';
}));

// Calculate overall progress
$total_progress = 0;
foreach ($all_tasks as $task) {
    $total_progress += $task['progress'];
}
$overall_progress = $total_tasks > 0 ? round($total_progress / $total_tasks, 2) : 0;

// Group tasks by division
$divisions = ['Engineering', 'Purchasing', 'Fabrikasi', 'Logistik', 'QC'];
$division_stats = [];

foreach ($divisions as $division) {
    $div_tasks = array_filter($all_tasks, function ($t) use ($division) {
        return $t['responsible_division'] == $division;
    });

    $div_total = count($div_tasks);
    $div_completed = count(array_filter($div_tasks, function ($t) {
        return $t['status'] == 'Completed';
    }));
    $div_in_progress = count(array_filter($div_tasks, function ($t) {
        return $t['status'] == 'In Progress';
    }));
    $div_todo = count(array_filter($div_tasks, function ($t) {
        return $t['status'] == 'Not Started';
    }));

    // Calculate average progress
    $div_progress = 0;
    foreach ($div_tasks as $task) {
        $div_progress += $task['progress'];
    }
    $div_avg_progress = $div_total > 0 ? round($div_progress / $div_total, 2) : 0;

    $division_stats[$division] = [
        'total' => $div_total,
        'completed' => $div_completed,
        'in_progress' => $div_in_progress,
        'todo' => $div_todo,
        'avg_progress' => $div_avg_progress,
        'tasks' => array_values($div_tasks)
    ];
}

$page_title = "Task List - " . $pon['pon_number'];
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
                    <h1 class="text-3xl font-bold text-white mb-2">
                        Task List > <?php echo htmlspecialchars($pon['pon_number']); ?>
                    </h1>
                    <p class="text-gray-400">
                        <?php echo htmlspecialchars($pon['subject']); ?> · <?php echo htmlspecialchars($pon['client_name']); ?>
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="../pon/detail.php?id=<?php echo $pon_id; ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-folder-open"></i>
                        <span>PON Detail</span>
                    </a>
                    <a href="manage.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Top Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Total Task & Item Keseluruhan -->
            <div class="bg-dark-light rounded-xl p-8 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-6 text-center">Total Task & Item Keseluruhan</h2>

                <div class="flex items-center justify-center mb-6">
                    <!-- Circular Chart -->
                    <div class="relative inline-flex items-center justify-center">
                        <svg class="w-64 h-64 transform -rotate-90">
                            <?php
                            $radius = 100;
                            $circumference = 2 * pi() * $radius;

                            // Calculate angles for each segment
                            $todo_angle = $total_tasks > 0 ? ($waiting_approval / $total_tasks) * 360 : 0;
                            $progress_angle = $total_tasks > 0 ? ($in_progress_tasks / $total_tasks) * 360 : 0;
                            $hold_angle = $total_tasks > 0 ? ($on_hold_tasks / $total_tasks) * 360 : 0;
                            $waiting_angle = 0; // Untuk "Waiting Approve"
                            $done_angle = $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 360 : 0;

                            $start_angle = 0;

                            // Function to draw arc
                            function drawArc($cx, $cy, $radius, $start_angle, $end_angle, $color)
                            {
                                $start_rad = deg2rad($start_angle);
                                $end_rad = deg2rad($end_angle);

                                $x1 = $cx + $radius * cos($start_rad);
                                $y1 = $cy + $radius * sin($start_rad);
                                $x2 = $cx + $radius * cos($end_rad);
                                $y2 = $cy + $radius * sin($end_rad);

                                $large_arc = ($end_angle - $start_angle) > 180 ? 1 : 0;

                                return "<path d='M $cx $cy L $x1 $y1 A $radius $radius 0 $large_arc 1 $x2 $y2 Z' fill='$color' stroke='#1e293b' stroke-width='2'/>";
                            }

                            // Draw segments
                            if ($waiting_approval > 0) {
                                echo drawArc(128, 128, $radius, $start_angle, $start_angle + $todo_angle, '#6b7280');
                                $start_angle += $todo_angle;
                            }

                            if ($in_progress_tasks > 0) {
                                echo drawArc(128, 128, $radius, $start_angle, $start_angle + $progress_angle, '#3b82f6');
                                $start_angle += $progress_angle;
                            }

                            if ($on_hold_tasks > 0) {
                                echo drawArc(128, 128, $radius, $start_angle, $start_angle + $hold_angle, '#f59e0b');
                                $start_angle += $hold_angle;
                            }

                            if ($completed_tasks > 0) {
                                echo drawArc(128, 128, $radius, $start_angle, $start_angle + $done_angle, '#10b981');
                            }
                            ?>

                            <!-- Center circle (donut hole) -->
                            <circle cx="128" cy="128" r="70" fill="#1e293b" />
                        </svg>

                        <!-- Center text -->
                        <div class="absolute text-center">
                            <div class="text-5xl font-bold text-white"><?php echo $total_tasks; ?></div>
                            <div class="text-lg text-gray-400">Total</div>
                            <div class="text-sm text-blue-400 mt-1">Selesai: <?php echo $overall_progress; ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="flex items-center justify-center space-x-6 text-sm">
                    <?php if ($waiting_approval > 0): ?>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-gray-500 rounded"></div>
                            <span class="text-gray-300">To Do (<?php echo $waiting_approval; ?>)</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($in_progress_tasks > 0): ?>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-blue-500 rounded"></div>
                            <span class="text-gray-300">On Progress (<?php echo $in_progress_tasks; ?>)</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($on_hold_tasks > 0): ?>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                            <span class="text-gray-300">Hold (<?php echo $on_hold_tasks; ?>)</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($completed_tasks > 0): ?>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-green-500 rounded"></div>
                            <span class="text-gray-300">Done (<?php echo $completed_tasks; ?>)</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-4 text-gray-400 text-sm">
                    Total Items: <?php echo $total_tasks; ?><br>
                    Progress Aktual: <?php echo $overall_progress; ?>% (berdasarkan item selesai)
                </div>
            </div>

            <!-- Jumlah Task & Item Per-Divisi -->
            <div class="bg-dark-light rounded-xl p-8 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-6">Jumlah Task & Item Per-Divisi</h2>

                <div class="space-y-6">
                    <?php foreach ($divisions as $division): ?>
                        <?php
                        $stats = $division_stats[$division];
                        if ($stats['total'] == 0) continue;

                        $completed_percent = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100) : 0;
                        $progress_percent = $stats['total'] > 0 ? round(($stats['in_progress'] / $stats['total']) * 100) : 0;
                        $todo_percent = $stats['total'] > 0 ? round(($stats['todo'] / $stats['total']) * 100) : 0;
                        ?>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-white font-semibold"><?php echo $division; ?></span>
                                <span class="text-gray-400 text-sm"><?php echo $stats['total']; ?></span>
                            </div>

                            <!-- Stacked Progress Bar -->
                            <div class="w-full h-8 bg-gray-800 rounded-lg overflow-hidden flex">
                                <?php if ($stats['todo'] > 0): ?>
                                    <div
                                        class="bg-gray-600 flex items-center justify-center text-white text-xs font-bold"
                                        style="width: <?php echo $todo_percent; ?>%"
                                        title="To Do: <?php echo $stats['todo']; ?>">
                                        <?php if ($todo_percent > 10): ?>To Do (<?php echo $stats['todo']; ?>)<?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($stats['in_progress'] > 0): ?>
                                    <div
                                        class="bg-blue-500 flex items-center justify-center text-white text-xs font-bold"
                                        style="width: <?php echo $progress_percent; ?>%"
                                        title="On Progress: <?php echo $stats['in_progress']; ?>">
                                        <?php if ($progress_percent > 10): ?>On Process (<?php echo $stats['in_progress']; ?>)<?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($stats['completed'] > 0): ?>
                                    <div
                                        class="bg-green-500 flex items-center justify-center text-white text-xs font-bold"
                                        style="width: <?php echo $completed_percent; ?>%"
                                        title="Done: <?php echo $stats['completed']; ?>">
                                        <?php if ($completed_percent > 10): ?>Done (<?php echo $stats['completed']; ?>)<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Progress Per-Divisi (Circular Charts) -->
        <div class="mb-8">
            <h2 class="text-xl font-bold text-white mb-6">Progress Per-Divisi</h2>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                <?php foreach ($divisions as $division): ?>
                    <?php
                    $stats = $division_stats[$division];
                    $progress = $stats['avg_progress'];

                    // Determine color based on progress
                    $color = '#3b82f6'; // blue default
                    if ($progress == 100) {
                        $color = '#10b981'; // green
                    } elseif ($progress == 0) {
                        $color = '#6b7280'; // gray
                    }
                    ?>

                    <div class="bg-dark-light rounded-xl p-6 shadow-xl text-center">
                        <!-- Circular Progress -->
                        <div class="flex items-center justify-center mb-4">
                            <div class="relative inline-flex items-center justify-center">
                                <svg class="w-32 h-32 transform -rotate-90">
                                    <circle cx="64" cy="64" r="56" stroke="#1e293b" stroke-width="8" fill="none" />
                                    <circle
                                        cx="64" cy="64" r="56"
                                        stroke="<?php echo $color; ?>"
                                        stroke-width="8"
                                        fill="none"
                                        stroke-dasharray="<?php echo 2 * pi() * 56; ?>"
                                        stroke-dashoffset="<?php echo 2 * pi() * 56 * (1 - $progress / 100); ?>"
                                        stroke-linecap="round" />
                                </svg>
                                <div class="absolute">
                                    <div class="text-3xl font-bold text-white"><?php echo $stats['total']; ?></div>
                                    <div class="text-xs text-gray-400">tasks</div>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-white font-bold mb-2"><?php echo $division; ?></h3>

                        <!-- MODIFIED: Tombol untuk melihat detail tugas divisi -->
                        <button
                            onclick="window.location.href='division_tasks.php?pon_id=<?php echo $pon_id; ?>&division=<?php echo $division; ?>'"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-semibold transition">
                            Lihat Tugas
                        </button>

                        <!-- Optional: Quick stats -->
                        <div class="mt-2 text-xs text-gray-400">
                            <?php echo $stats['completed']; ?> selesai dari <?php echo $stats['total']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Task Details Per Division (Hidden by default) -->
        <?php foreach ($divisions as $division): ?>
            <?php if ($division_stats[$division]['total'] > 0): ?>
                <div id="tasks_<?php echo $division; ?>" class="hidden mb-8">
                    <div class="bg-dark-light rounded-xl shadow-xl">
                        <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                            <h2 class="text-xl font-bold text-white">
                                <i class="fas fa-tasks text-blue-400 mr-2"></i>
                                Tasks - <?php echo $division; ?>
                            </h2>
                            <button
                                onclick="hideDivisionTasks('<?php echo $division; ?>')"
                                class="text-gray-400 hover:text-white">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-800 text-gray-400 text-sm">
                                    <tr>
                                        <th class="px-6 py-4 text-left">No</th>
                                        <th class="px-6 py-4 text-left">Task Name</th>
                                        <th class="px-6 py-4 text-left">Phase</th>
                                        <th class="px-6 py-4 text-left">PIC</th>
                                        <th class="px-6 py-4 text-center">Progress</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php
                                    $no = 1;
                                    foreach ($division_stats[$division]['tasks'] as $task):
                                    ?>
                                        <tr class="hover:bg-gray-800 transition">
                                            <td class="px-6 py-4 text-gray-300"><?php echo $no++; ?></td>
                                            <td class="px-6 py-4">
                                                <p class="text-white font-semibold"><?php echo htmlspecialchars($task['task_name']); ?></p>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-xs px-3 py-1 rounded-full bg-purple-900 text-purple-200 font-semibold">
                                                    <?php echo $task['phase']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-300 text-sm">
                                                <?php echo htmlspecialchars($task['pic_internal'] ?? $task['assigned_to_name'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="flex flex-col items-center">
                                                    <span class="text-white font-bold text-lg"><?php echo round($task['progress']); ?>%</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo get_status_color($task['status']); ?> text-white">
                                                    <?php echo $task['status']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <a
                                                    href="detail.php?id=<?php echo $task['task_id']; ?>"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition inline-block">
                                                    <i class="fas fa-eye mr-1"></i>Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

    </main>

</div>

<!-- JavaScript -->
<script>
    function showDivisionTasks(division) {
        // Hide all task tables first
        const allTables = document.querySelectorAll('[id^="tasks_"]');
        allTables.forEach(table => {
            table.classList.add('hidden');
        });

        // Show selected division tasks
        const targetTable = document.getElementById('tasks_' + division);
        if (targetTable) {
            targetTable.classList.remove('hidden');
            targetTable.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    function hideDivisionTasks(division) {
        const targetTable = document.getElementById('tasks_' + division);
        if (targetTable) {
            targetTable.classList.add('hidden');
        }
    }
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>