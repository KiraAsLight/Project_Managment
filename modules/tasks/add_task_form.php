<?php

/**
 * Form untuk menambah task baru (di-load via AJAX)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['pon_id']) || !isset($_GET['division'])) {
    die("Invalid parameters");
}

$pon_id = (int)$_GET['pon_id'];
$division = sanitize_input($_GET['division']);

// Get PON info
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT pon_number, subject FROM pon WHERE pon_id = ?");
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$pon = $stmt->get_result()->fetch_assoc();

// Get users list untuk assignment
$users_query = "SELECT user_id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name";
$users_result = $conn->query($users_query);
$users_list = [];
while ($row = $users_result->fetch_assoc()) {
    $users_list[] = $row;
}
?>

<form id="addTaskForm" method="POST" action="add_task_process.php" class="space-y-4">
    <input type="hidden" name="pon_id" value="<?php echo $pon_id; ?>">
    <input type="hidden" name="responsible_division" value="<?php echo $division; ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-1">Task Name *</label>
            <input type="text" name="task_name" required
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-1">Phase *</label>
            <select name="phase" required class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
                <option value="Engineering">Engineering</option>
                <option value="Fabrication + Trial">Fabrication + Trial</option>
                <option value="Civil Work Start">Civil Work Start</option>
                <option value="Galvanizing + Packing">Galvanizing + Packing</option>
                <option value="Civil Work Finished">Civil Work Finished</option>
                <option value="Delivery">Delivery</option>
                <option value="Erection">Erection</option>
            </select>
        </div>
    </div>

    <div>
        <label class="block text-gray-300 text-sm font-medium mb-1">Description</label>
        <textarea name="description" rows="3"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500"
            placeholder="Describe the task..."></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-1">Start Date *</label>
            <input type="date" name="start_date" required
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-1">Finish Date *</label>
            <input type="date" name="finish_date" required
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-1">Assigned To</label>
            <select name="assigned_to" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500">
                <option value="">Not Assigned</option>
                <?php foreach ($users_list as $user): ?>
                    <option value="<?php echo $user['user_id']; ?>">
                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-300 text-sm font-medium mb-1">PIC Internal</label>
            <input type="text" name="pic_internal"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded text-white focus:border-blue-500"
                placeholder="WGJ, DHJ, CGI">
        </div>
    </div>

    <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="hideAddTaskModal()"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded">
            Cancel
        </button>
        <button type="submit"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
            Create Task
        </button>
    </div>
</form>

<script>
    document.getElementById('addTaskForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error creating task');
            });
    });
</script>