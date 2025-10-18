<?php

/**
 * Edit Task Page
 * Form untuk update task details
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Only Admin & Engineering can edit tasks
require_role(['Admin', 'Engineering']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Task ID");
}

$task_id = (int)$_GET['id'];
$conn = getDBConnection();

// Get task data
$stmt = $conn->prepare("SELECT t.*, p.pon_number, p.project_name 
                        FROM tasks t 
                        JOIN pon p ON t.pon_id = p.pon_id 
                        WHERE t.task_id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();

if (!$task) {
    die("Task tidak ditemukan");
}

// Get list users untuk assignment
$users_query = "SELECT user_id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name";
$users_result = $conn->query($users_query);
$users_list = [];
while ($row = $users_result->fetch_assoc()) {
    $users_list[] = $row;
}

// Get list PON untuk dropdown (jika ingin pindah task ke PON lain)
$pon_query = "SELECT pon_id, pon_number, subject FROM pon WHERE status != 'Cancelled' ORDER BY pon_number DESC";
$pon_result = $conn->query($pon_query);
$pon_list = [];
while ($row = $pon_result->fetch_assoc()) {
    $pon_list[] = $row;
}

$errors = [];

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi dan sanitasi input
    $pon_id = (int)$_POST['pon_id'];
    $phase = sanitize_input($_POST['phase']);
    $responsible_division = sanitize_input($_POST['responsible_division']);
    $task_name = sanitize_input($_POST['task_name']);
    $description = sanitize_input($_POST['description']);

    $pic_internal = sanitize_input($_POST['pic_internal']);
    $pic_external = sanitize_input($_POST['pic_external']);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    $start_date = sanitize_input($_POST['start_date']);
    $finish_date = sanitize_input($_POST['finish_date']);
    $actual_start_date = !empty($_POST['actual_start_date']) ? sanitize_input($_POST['actual_start_date']) : null;
    $actual_finish_date = !empty($_POST['actual_finish_date']) ? sanitize_input($_POST['actual_finish_date']) : null;

    $etd_date = !empty($_POST['etd_date']) ? sanitize_input($_POST['etd_date']) : null;
    $eta_date = !empty($_POST['eta_date']) ? sanitize_input($_POST['eta_date']) : null;

    $weight_value = !empty($_POST['weight_value']) ? (float)$_POST['weight_value'] : 0;
    $weight_unit = sanitize_input($_POST['weight_unit']);

    $progress = (float)$_POST['progress'];
    $status = sanitize_input($_POST['status']);

    $notes = sanitize_input($_POST['notes']);
    $issues = sanitize_input($_POST['issues']);

    // Validasi required fields
    if (empty($task_name)) $errors[] = "Task Name wajib diisi";
    if (empty($phase)) $errors[] = "Phase wajib diisi";
    if (empty($responsible_division)) $errors[] = "Division wajib diisi";
    if (empty($start_date)) $errors[] = "Start Date wajib diisi";
    if (empty($finish_date)) $errors[] = "Finish Date wajib diisi";

    // Validasi dates
    if (strtotime($start_date) > strtotime($finish_date)) {
        $errors[] = "Start Date tidak boleh lebih besar dari Finish Date";
    }

    // Validasi progress
    if ($progress < 0 || $progress > 100) {
        $errors[] = "Progress harus antara 0-100%";
    }

    // Jika tidak ada error, update ke database
    if (empty($errors)) {
        $update_query = "UPDATE tasks SET 
            pon_id = ?,
            phase = ?,
            responsible_division = ?,
            task_name = ?,
            description = ?,
            pic_internal = ?,
            pic_external = ?,
            assigned_to = ?,
            start_date = ?,
            finish_date = ?,
            actual_start_date = ?,
            actual_finish_date = ?,
            etd_date = ?,
            eta_date = ?,
            weight_value = ?,
            weight_unit = ?,
            progress = ?,
            status = ?,
            notes = ?,
            issues = ?,
            updated_at = NOW()
            WHERE task_id = ?";

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param(
            "issssssisssssdsdssi",
            $pon_id,
            $phase,
            $responsible_division,
            $task_name,
            $description,
            $pic_internal,
            $pic_external,
            $assigned_to,
            $start_date,
            $finish_date,
            $actual_start_date,
            $actual_finish_date,
            $etd_date,
            $eta_date,
            $weight_value,
            $weight_unit,
            $progress,
            $status,
            $notes,
            $issues,
            $task_id
        );

        if ($stmt->execute()) {
            // Log activity
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'Update Task',
                "Update task '{$task_name}' (Task ID: {$task_id})"
            );

            $_SESSION['success_message'] = "Task berhasil diupdate!";
            redirect("modules/tasks/detail.php?id={$task_id}");
        } else {
            $errors[] = "Gagal update task: " . $stmt->error;
        }
    }
} else {
    // Populate form dengan data existing
    $_POST = $task;
}

$page_title = "Edit Task - " . $task['task_name'];
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
                    <h1 class="text-3xl font-bold text-white mb-2">Edit Task</h1>
                    <p class="text-gray-400"><?php echo htmlspecialchars($task['task_name']); ?></p>
                </div>
                <a href="detail.php?id=<?php echo $task_id; ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Detail</span>
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

                    <!-- PON -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            PON / Project <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="pon_id"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($pon_list as $pon): ?>
                                <option value="<?php echo $pon['pon_id']; ?>" <?php echo $pon['pon_id'] == $_POST['pon_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pon['pon_number']); ?> - <?php echo htmlspecialchars($pon['subject']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Phase -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Phase <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="phase"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <option value="Engineering" <?php echo $_POST['phase'] == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                            <option value="Fabrication + Trial" <?php echo $_POST['phase'] == 'Fabrication + Trial' ? 'selected' : ''; ?>>Fabrication + Trial</option>
                            <option value="Civil Work Start" <?php echo $_POST['phase'] == 'Civil Work Start' ? 'selected' : ''; ?>>Civil Work Start</option>
                            <option value="Galvanizing + Packing" <?php echo $_POST['phase'] == 'Galvanizing + Packing' ? 'selected' : ''; ?>>Galvanizing + Packing</option>
                            <option value="Civil Work Finished" <?php echo $_POST['phase'] == 'Civil Work Finished' ? 'selected' : ''; ?>>Civil Work Finished</option>
                            <option value="Delivery" <?php echo $_POST['phase'] == 'Delivery' ? 'selected' : ''; ?>>Delivery</option>
                            <option value="Erection" <?php echo $_POST['phase'] == 'Erection' ? 'selected' : ''; ?>>Erection</option>
                        </select>
                    </div>

                    <!-- Responsible Division -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Responsible Division <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="responsible_division"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <option value="Engineering" <?php echo $_POST['responsible_division'] == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                            <option value="Purchasing" <?php echo $_POST['responsible_division'] == 'Purchasing' ? 'selected' : ''; ?>>Purchasing</option>
                            <option value="Fabrikasi" <?php echo $_POST['responsible_division'] == 'Fabrikasi' ? 'selected' : ''; ?>>Fabrikasi</option>
                            <option value="Logistik" <?php echo $_POST['responsible_division'] == 'Logistik' ? 'selected' : ''; ?>>Logistik</option>
                            <option value="QC" <?php echo $_POST['responsible_division'] == 'QC' ? 'selected' : ''; ?>>QC</option>
                            <option value="All" <?php echo $_POST['responsible_division'] == 'All' ? 'selected' : ''; ?>>All Divisions</option>
                        </select>
                    </div>

                    <!-- Task Name -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">
                            Task Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="task_name"
                            required
                            placeholder="Engineering Design & Drawing"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['task_name'] ?? ''); ?>">
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">Description</label>
                        <textarea
                            name="description"
                            rows="4"
                            placeholder="Detail pekerjaan..."
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                </div>
            </div>

            <!-- Section 2: PIC & Assignment -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-users text-green-400 mr-2"></i>
                    PIC & Assignment
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- PIC Internal -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PIC Internal</label>
                        <input
                            type="text"
                            name="pic_internal"
                            placeholder="WGJ, DHJ, CGI"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['pic_internal'] ?? ''); ?>">
                    </div>

                    <!-- PIC External -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">PIC External</label>
                        <input
                            type="text"
                            name="pic_external"
                            placeholder="Supplier/Partner external"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo htmlspecialchars($_POST['pic_external'] ?? ''); ?>">
                    </div>

                    <!-- Assigned To -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Assigned To (User)</label>
                        <select
                            name="assigned_to"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <option value="">Not Assigned</option>
                            <?php foreach ($users_list as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $user['user_id'] == ($_POST['assigned_to'] ?? null) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>

            <!-- Section 3: Timeline -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-calendar-alt text-yellow-400 mr-2"></i>
                    Timeline
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Start Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Start Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="start_date"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['start_date'] ?? ''; ?>">
                    </div>

                    <!-- Finish Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Finish Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="finish_date"
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['finish_date'] ?? ''; ?>">
                    </div>

                    <!-- Actual Start Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Actual Start Date</label>
                        <input
                            type="date"
                            name="actual_start_date"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['actual_start_date'] ?? ''; ?>">
                    </div>

                    <!-- Actual Finish Date -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Actual Finish Date</label>
                        <input
                            type="date"
                            name="actual_finish_date"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['actual_finish_date'] ?? ''; ?>">
                    </div>

                    <!-- ETD (Estimated Time of Departure) -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">ETD (Estimated Time of Departure)</label>
                        <input
                            type="date"
                            name="etd_date"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['etd_date'] ?? ''; ?>">
                        <p class="text-gray-500 text-sm mt-1">Khusus untuk Delivery phase</p>
                    </div>

                    <!-- ETA (Estimated Time of Arrival) -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">ETA (Estimated Time of Arrival)</label>
                        <input
                            type="date"
                            name="eta_date"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['eta_date'] ?? ''; ?>">
                        <p class="text-gray-500 text-sm mt-1">Khusus untuk Delivery phase</p>
                    </div>

                </div>
            </div>

            <!-- Section 4: Progress & Status -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-chart-line text-purple-400 mr-2"></i>
                    Progress & Status
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Progress -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Progress: <span id="progressValue" class="text-blue-400 font-bold"><?php echo round($_POST['progress'] ?? 0); ?>%</span>
                        </label>
                        <input
                            type="range"
                            name="progress"
                            id="progressSlider"
                            min="0"
                            max="100"
                            value="<?php echo round($_POST['progress'] ?? 0); ?>"
                            class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-600"
                            oninput="updateProgressValue(this.value)">
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Status</label>
                        <select
                            name="status"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <option value="Not Started" <?php echo ($_POST['status'] ?? '') == 'Not Started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="In Progress" <?php echo ($_POST['status'] ?? '') == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="On Hold" <?php echo ($_POST['status'] ?? '') == 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Completed" <?php echo ($_POST['status'] ?? '') == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Delayed" <?php echo ($_POST['status'] ?? '') == 'Delayed' ? 'selected' : ''; ?>>Delayed</option>
                        </select>
                    </div>

                    <!-- Weight Value -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Weight Value</label>
                        <input
                            type="number"
                            name="weight_value"
                            step="0.01"
                            placeholder="0.00"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            value="<?php echo $_POST['weight_value'] ?? '0'; ?>">
                    </div>

                    <!-- Weight Unit -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Weight Unit</label>
                        <select
                            name="weight_unit"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            <option value="ton" <?php echo ($_POST['weight_unit'] ?? 'ton') == 'ton' ? 'selected' : ''; ?>>ton</option>
                            <option value="kg" <?php echo ($_POST['weight_unit'] ?? 'ton') == 'kg' ? 'selected' : ''; ?>>kg</option>
                        </select>
                    </div>

                </div>
            </div>

            <!-- Section 5: Notes & Issues -->
            <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                    <i class="fas fa-sticky-note text-orange-400 mr-2"></i>
                    Notes & Issues
                </h2>

                <div class="space-y-6">

                    <!-- Notes -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Notes</label>
                        <textarea
                            name="notes"
                            rows="4"
                            placeholder="Catatan tambahan..."
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Issues -->
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Issues / Kendala</label>
                        <textarea
                            name="issues"
                            rows="6"
                            placeholder="Log kendala dan masalah..."
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-red-500 focus:ring-2 focus:ring-red-500"><?php echo htmlspecialchars($_POST['issues'] ?? ''); ?></textarea>
                        <p class="text-gray-500 text-sm mt-1">Format: [YYYY-MM-DD HH:MM:SS] Username: Issue description</p>
                    </div>

                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-between space-x-4">
                <a
                    href="detail.php?id=<?php echo $task_id; ?>"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>

                <button
                    type="submit"
                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition flex items-center space-x-2">
                    <i class="fas fa-save"></i>
                    <span>Update Task</span>
                </button>
            </div>

        </form>

    </main>

</div>

<!-- JavaScript -->
<script>
    // Update progress value display
    function updateProgressValue(value) {
        document.getElementById('progressValue').textContent = Math.round(value) + '%';
    }

    // Auto-complete when progress reaches 100%
    document.getElementById('progressSlider').addEventListener('change', function() {
        if (this.value == 100) {
            document.querySelector('select[name="status"]').value = 'Completed';
            alert('Progress mencapai 100%. Status diubah menjadi Completed.');
        }
    });
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>