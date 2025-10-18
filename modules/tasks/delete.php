<?php

/**
 * Delete Task
 * Menghapus task beserta semua dokumen QC terkait
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Only Admin can delete tasks
require_role(['Admin']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Task ID");
}

$task_id = (int)$_GET['id'];
$conn = getDBConnection();

// Get task info untuk log dan redirect
$stmt = $conn->prepare("SELECT t.task_name, t.pon_id, p.pon_number 
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

// Confirmation page
if (!isset($_POST['confirm_delete'])) {
    // Get QC documents count
    $docs_query = "SELECT COUNT(*) as doc_count FROM qc_documents WHERE task_id = ?";
    $stmt = $conn->prepare($docs_query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $docs_result = $stmt->get_result();
    $docs_data = $docs_result->fetch_assoc();
    $doc_count = $docs_data['doc_count'];

    $page_title = "Delete Task - Confirmation";
    include '../../includes/header.php';
?>

    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>

        <main class="flex-1 ml-64 p-8 bg-gray-900 min-h-screen">

            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">Delete Task Confirmation</h1>
                <p class="text-gray-400">Konfirmasi penghapusan task</p>
            </div>

            <!-- Confirmation Card -->
            <div class="max-w-2xl mx-auto">
                <div class="bg-dark-light rounded-xl p-8 shadow-xl border-l-4 border-red-500">

                    <!-- Warning Icon -->
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-red-600 bg-opacity-20 rounded-full mb-4">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2">Are You Sure?</h2>
                        <p class="text-gray-400">This action cannot be undone!</p>
                    </div>

                    <!-- Task Details -->
                    <div class="bg-gray-800 rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2">
                            Task yang akan dihapus:
                        </h3>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Task Name:</span>
                                <span class="text-white font-semibold"><?php echo htmlspecialchars($task['task_name']); ?></span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">PON Number:</span>
                                <span class="text-white font-semibold"><?php echo htmlspecialchars($task['pon_number']); ?></span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">QC Documents:</span>
                                <span class="text-white font-semibold"><?php echo $doc_count; ?> files</span>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Message -->
                    <div class="bg-red-900 bg-opacity-30 border border-red-700 rounded-lg p-4 mb-6">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-red-400 mt-1"></i>
                            <div class="text-sm text-red-200">
                                <p class="font-semibold mb-2">Perhatian:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Task ini akan dihapus secara permanen</li>
                                    <li>Semua QC documents terkait akan ikut terhapus (<?php echo $doc_count; ?> files)</li>
                                    <li>Activity logs terkait task ini akan dihapus</li>
                                    <li>Data tidak dapat dikembalikan setelah dihapus</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <form method="POST" action="">
                        <div class="flex items-center space-x-4">
                            <a
                                href="detail.php?id=<?php echo $task_id; ?>"
                                class="flex-1 bg-gray-700 hover:bg-gray-600 text-white text-center py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>

                            <button
                                type="submit"
                                name="confirm_delete"
                                value="1"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white text-center py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-trash mr-2"></i>Yes, Delete Task
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </main>
    </div>

<?php
    closeDBConnection($conn);
    include '../../includes/footer.php';
    exit;
}

// Process deletion
if (isset($_POST['confirm_delete'])) {

    // Get all QC documents untuk delete files
    $docs_query = "SELECT file_path FROM qc_documents WHERE task_id = ?";
    $stmt = $conn->prepare($docs_query);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $docs_result = $stmt->get_result();

    $files_to_delete = [];
    while ($doc = $docs_result->fetch_assoc()) {
        $files_to_delete[] = $doc['file_path'];
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete physical files
        foreach ($files_to_delete as $file_path) {
            $full_path = '../../' . $file_path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }

        // Delete QC documents records (akan cascade delete karena foreign key)
        $delete_docs = $conn->prepare("DELETE FROM qc_documents WHERE task_id = ?");
        $delete_docs->bind_param("i", $task_id);
        $delete_docs->execute();

        // Delete task (akan cascade delete semua dependencies)
        $delete_task = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
        $delete_task->bind_param("i", $task_id);
        $delete_task->execute();

        // Log activity
        log_activity(
            $conn,
            $_SESSION['user_id'],
            'Delete Task',
            "Menghapus task '{$task['task_name']}' dari PON {$task['pon_number']} (Task ID: {$task_id})"
        );

        // Commit transaction
        $conn->commit();

        // Success - Redirect ke PON detail
        $_SESSION['success_message'] = "Task '{$task['task_name']}' berhasil dihapus!";
        redirect("modules/pon/detail.php?id={$task['pon_id']}");
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        die("Error deleting task: " . $e->getMessage());
    }
}

closeDBConnection($conn);
?>