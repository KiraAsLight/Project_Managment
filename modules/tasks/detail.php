<?php
/**
 * Task Detail Page
 * Menampilkan detail task dengan kemampuan update progress dan upload dokumen
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Task ID");
}

$task_id = (int)$_GET['id'];
$conn = getDBConnection();

// Get task data dengan informasi PON
$stmt = $conn->prepare("SELECT 
                        t.*,
                        p.pon_number,
                        p.project_name,
                        p.client_name,
                        p.subject,
                        u1.full_name as assigned_to_name,
                        u1.role as assigned_role,
                        u2.full_name as created_by_name
                      FROM tasks t
                      JOIN pon p ON t.pon_id = p.pon_id
                      LEFT JOIN users u1 ON t.assigned_to = u1.user_id
                      LEFT JOIN users u2 ON p.created_by = u2.user_id
                      WHERE t.task_id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();

if (!$task) {
    die("Task tidak ditemukan");
}

// Get QC documents untuk task ini
$docs_query = "SELECT 
                d.*,
                u.full_name as uploaded_by_name,
                u.role as uploaded_by_role
               FROM qc_documents d
               LEFT JOIN users u ON d.uploaded_by = u.user_id
               WHERE d.task_id = ?
               ORDER BY d.uploaded_at DESC";
$stmt = $conn->prepare($docs_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$docs_result = $stmt->get_result();
$documents = [];
while ($row = $docs_result->fetch_assoc()) {
    $documents[] = $row;
}

// Get activity logs untuk task ini
$activity_query = "SELECT 
                    al.*,
                    u.full_name,
                    u.role
                   FROM activity_logs al
                   JOIN users u ON al.user_id = u.user_id
                   WHERE al.description LIKE CONCAT('%Task ID: ', ?, '%')
                   ORDER BY al.created_at DESC
                   LIMIT 20";
$stmt = $conn->prepare($activity_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$activity_result = $stmt->get_result();
$activities = [];
while ($row = $activity_result->fetch_assoc()) {
    $activities[] = $row;
}

// Calculate days remaining
$days_remaining = days_difference($task['finish_date']);
$is_overdue = ($days_remaining < 0 && $task['status'] != 'Completed');

// Handle form submission untuk update task
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_progress') {
        // Update progress
        $new_progress = (float)$_POST['progress'];
        $new_status = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['notes']);
        
        // Validasi
        if ($new_progress < 0 || $new_progress > 100) {
            $errors[] = "Progress harus antara 0-100%";
        }
        
        if (empty($errors)) {
            $update_query = "UPDATE tasks SET 
                            progress = ?,
                            status = ?,
                            notes = ?,
                            updated_at = NOW()";
            
            // Jika progress 100%, set completed date
            if ($new_progress == 100) {
                $update_query .= ", completed_at = NOW()";
                $new_status = 'Completed';
            }
            
            $update_query .= " WHERE task_id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("dssi", $new_progress, $new_status, $notes, $task_id);
            
            if ($stmt->execute()) {
                // Log activity
                log_activity(
                    $conn,
                    $_SESSION['user_id'],
                    'Update Task Progress',
                    "Update progress task '{$task['task_name']}' menjadi {$new_progress}% (Task ID: {$task_id})"
                );
                
                $success_message = "Progress berhasil diupdate!";
                
                // Refresh task data
                header("Location: detail.php?id={$task_id}&success=1");
                exit;
            } else {
                $errors[] = "Gagal update progress: " . $stmt->error;
            }
        }
    }
    
    elseif ($_POST['action'] === 'add_issue') {
        // Add issue/kendala
        $issue_text = sanitize_input($_POST['issue_text']);
        
        if (empty($issue_text)) {
            $errors[] = "Issue tidak boleh kosong";
        } else {
            $current_issues = $task['issues'] ?? '';
            $timestamp = date('Y-m-d H:i:s');
            $new_issue = "[{$timestamp}] {$_SESSION['full_name']}: {$issue_text}";
            
            $updated_issues = empty($current_issues) 
                ? $new_issue 
                : $current_issues . "\n" . $new_issue;
            
            $stmt = $conn->prepare("UPDATE tasks SET issues = ? WHERE task_id = ?");
            $stmt->bind_param("si", $updated_issues, $task_id);
            
            if ($stmt->execute()) {
                log_activity(
                    $conn,
                    $_SESSION['user_id'],
                    'Add Task Issue',
                    "Menambahkan issue pada task '{$task['task_name']}' (Task ID: {$task_id})"
                );
                
                header("Location: detail.php?id={$task_id}&success=2");
                exit;
            }
        }
    }
}

// Success message dari redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $success_message = "Progress berhasil diupdate!";
    } elseif ($_GET['success'] == 2) {
        $success_message = "Issue berhasil ditambahkan!";
    } elseif ($_GET['success'] == 3) {
        $success_message = "Dokumen berhasil diupload!";
    }
}

$page_title = "Task Detail - " . $task['task_name'];
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
                            <?php echo htmlspecialchars($task['task_name']); ?>
                        </h1>
                        <span class="px-4 py-1 rounded-full text-sm font-bold <?php echo get_status_color($task['status']); ?> text-white">
                            <?php echo $task['status']; ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-4 text-gray-400">
                        <span>
                            <i class="fas fa-project-diagram mr-2"></i>
                            <a href="../pon/detail.php?id=<?php echo $task['pon_id']; ?>" class="text-blue-400 hover:text-blue-300">
                                <?php echo htmlspecialchars($task['pon_number']); ?>
                            </a>
                        </span>
                        <span>·</span>
                        <span><?php echo htmlspecialchars($task['phase']); ?></span>
                        <span>·</span>
                        <span><?php echo htmlspecialchars($task['responsible_division']); ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="manage.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to List</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-900 border-l-4 border-green-500 text-green-200 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <p><?php echo $success_message; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-900 border-l-4 border-red-500 text-red-200 p-4 mb-6 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3 mt-1"></i>
                    <div>
                        <p class="font-bold mb-2">Error!</p>
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column - Main Info -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Progress Overview Card -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-700 pb-3">
                        <i class="fas fa-chart-line text-blue-400 mr-2"></i>
                        Progress Overview
                    </h2>
                    
                    <div class="grid grid-cols-3 gap-6">
                        
                        <!-- Circular Progress -->
                        <div class="flex flex-col items-center justify-center">
                            <div class="relative inline-flex items-center justify-center mb-4">
                                <svg class="w-32 h-32 transform -rotate-90">
                                    <circle cx="64" cy="64" r="56" stroke="#1e293b" stroke-width="8" fill="none"/>
                                    <circle 
                                        cx="64" cy="64" r="56"
                                        stroke="<?php echo $task['status'] == 'Completed' ? '#10b981' : '#3b82f6'; ?>"
                                        stroke-width="8"
                                        fill="none"
                                        stroke-dasharray="<?php echo 2 * pi() * 56; ?>"
                                        stroke-dashoffset="<?php echo 2 * pi() * 56 * (1 - $task['progress'] / 100); ?>"
                                        stroke-linecap="round"
                                    />
                                </svg>
                                <div class="absolute">
                                    <span class="text-3xl font-bold text-white"><?php echo round($task['progress']); ?>%</span>
                                </div>
                            </div>
                            <p class="text-gray-400 text-sm">Current Progress</p>
                        </div>
                        
                        <!-- Timeline Info -->
                        <div class="col-span-2 space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-800 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-calendar-alt text-blue-400 text-xl"></i>
                                    <div>
                                        <p class="text-gray-400 text-sm">Start Date</p>
                                        <p class="text-white font-semibold"><?php echo format_date_indo($task['start_date']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-gray-800 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-flag-checkered text-green-400 text-xl"></i>
                                    <div>
                                        <p class="text-gray-400 text-sm">Target Date</p>
                                        <p class="text-white font-semibold"><?php echo format_date_indo($task['finish_date']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-gray-800 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-clock <?php echo $is_overdue ? 'text-red-400' : 'text-yellow-400'; ?> text-xl"></i>
                                    <div>
                                        <p class="text-gray-400 text-sm">Days Remaining</p>
                                        <p class="text-white font-semibold">
                                            <?php 
                                            if ($task['status'] == 'Completed') {
                                                echo 'Completed';
                                            } elseif ($is_overdue) {
                                                echo '<span class="text-red-400">' . abs($days_remaining) . ' days overdue</span>';
                                            } else {
                                                echo $days_remaining . ' days';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Update Progress Form -->
                <?php if (hasAnyRole(['Admin', 'Engineering']) || $_SESSION['user_id'] == $task['assigned_to']): ?>
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-700 pb-3">
                        <i class="fas fa-edit text-yellow-400 mr-2"></i>
                        Update Progress
                    </h2>
                    
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="action" value="update_progress">
                        
                        <!-- Progress Slider -->
                        <div>
                            <label class="block text-gray-300 font-medium mb-2">
                                Progress: <span id="progressValue" class="text-blue-400 font-bold"><?php echo round($task['progress']); ?>%</span>
                            </label>
                            <input 
                                type="range" 
                                name="progress" 
                                id="progressSlider"
                                min="0" 
                                max="100" 
                                value="<?php echo round($task['progress']); ?>"
                                class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                oninput="updateProgressValue(this.value)"
                            >
                            <div class="flex justify-between text-xs text-gray-500 mt-2">
                                <span>0%</span>
                                <span>25%</span>
                                <span>50%</span>
                                <span>75%</span>
                                <span>100%</span>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <label class="block text-gray-300 font-medium mb-2">Status</label>
                            <select 
                                name="status" 
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="Not Started" <?php echo $task['status'] == 'Not Started' ? 'selected' : ''; ?>>Not Started</option>
                                <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="On Hold" <?php echo $task['status'] == 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="Completed" <?php echo $task['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <!-- Notes -->
                        <div>
                            <label class="block text-gray-300 font-medium mb-2">Notes / Update</label>
                            <textarea 
                                name="notes" 
                                rows="4"
                                placeholder="Catatan update progress..."
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            ><?php echo htmlspecialchars($task['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center space-x-2"
                        >
                            <i class="fas fa-save"></i>
                            <span>Update Progress</span>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Task Description -->
                <?php if (!empty($task['description'])): ?>
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-align-left text-purple-400 mr-2"></i>
                        Description
                    </h2>
                    <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($task['description']); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Issues / Kendala -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                        Issues / Kendala
                    </h2>
                    
                    <!-- Issues List -->
                    <?php if (!empty($task['issues'])): ?>
                        <div class="bg-gray-800 rounded-lg p-4 mb-4 max-h-64 overflow-y-auto">
                            <pre class="text-gray-300 text-sm whitespace-pre-wrap font-mono"><?php echo htmlspecialchars($task['issues']); ?></pre>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4 mb-4">Belum ada issue yang dilaporkan</p>
                    <?php endif; ?>
                    
                    <!-- Add Issue Form -->
                    <?php if (hasAnyRole(['Admin', 'Engineering', 'QC']) || $_SESSION['user_id'] == $task['assigned_to']): ?>
                    <form method="POST" action="" class="space-y-4">
                        <input type="hidden" name="action" value="add_issue">
                        
                        <textarea 
                            name="issue_text" 
                            rows="3"
                            placeholder="Laporkan kendala atau issue..."
                            required
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-red-500 focus:ring-2 focus:ring-red-500"
                        ></textarea>
                        
                        <button 
                            type="submit" 
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg transition flex items-center justify-center space-x-2"
                        >
                            <i class="fas fa-plus"></i>
                            <span>Add Issue</span>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <!-- QC Documents -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-4 border-b border-gray-700 pb-3">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-file-alt text-green-400 mr-2"></i>
                            QC Documents (<?php echo count($documents); ?>)
                        </h2>
                        <?php if (hasAnyRole(['Admin', 'QC', 'Engineering'])): ?>
                        <a 
                            href="../qc/upload.php?task_id=<?php echo $task_id; ?>" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2"
                        >
                            <i class="fas fa-upload"></i>
                            <span>Upload</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <p class="text-gray-500 text-center py-8">Belum ada dokumen QC</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($documents as $doc): ?>
                                <div class="bg-gray-800 rounded-lg p-4 hover:bg-gray-750 transition">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start space-x-3">
                                            <div class="bg-green-600 bg-opacity-20 rounded-lg p-3">
                                                <?php if (strpos($doc['file_type'], 'image') !== false): ?>
                                                    <i class="fas fa-image text-green-400 text-xl"></i>
                                                <?php elseif (strpos($doc['file_type'], 'pdf') !== false): ?>
                                                    <i class="fas fa-file-pdf text-green-400 text-xl"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file text-green-400 text-xl"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h4 class="text-white font-semibold mb-1">
                                                    <?php echo htmlspecialchars($doc['file_name']); ?>
                                                </h4>
                                                <p class="text-sm text-gray-400">
                                                    <?php echo $doc['document_type']; ?> · 
                                                    <?php echo round($doc['file_size'] / 1024, 2); ?> KB
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Uploaded by <?php echo htmlspecialchars($doc['uploaded_by_name']); ?> · 
                                                    <?php echo format_date_indo(date('Y-m-d', strtotime($doc['uploaded_at']))); ?>
                                                </p>
                                                <?php if (!empty($doc['notes'])): ?>
                                                    <p class="text-sm text-gray-400 mt-2"><?php echo htmlspecialchars($doc['notes']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold 
                                                <?php 
                                                echo $doc['approval_status'] == 'Approved' ? 'bg-green-600' : 
                                                    ($doc['approval_status'] == 'Rejected' ? 'bg-red-600' : 'bg-yellow-600'); 
                                                ?> text-white">
                                                <?php echo $doc['approval_status']; ?>
                                            </span>
                                            <a 
                                                href="<?php echo BASE_URL . $doc['file_path']; ?>" 
                                                target="_blank"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg"
                                                title="Download"
                                            >
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Activity History -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-history text-orange-400 mr-2"></i>
                        Activity History
                    </h2>
                    
                    <?php if (empty($activities)): ?>
                        <p class="text-gray-500 text-center py-4">Belum ada aktivitas</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                $time_diff = time() - strtotime($activity['created_at']);
                                if ($time_diff < 60) $time_ago = 'baru saja';
                                elseif ($time_diff < 3600) $time_ago = floor($time_diff / 60) . ' menit lalu';
                                elseif ($time_diff < 86400) $time_ago = floor($time_diff / 3600) . ' jam lalu';
                                else $time_ago = floor($time_diff / 86400) . ' hari lalu';
                                ?>
                                <div class="flex items-start space-x-3 p-3 bg-gray-800 rounded-lg">
                                    <div class="flex-shrink-0 mt-1">
                                        <i class="fas fa-circle text-blue-400 text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-white">
                                            <span class="font-semibold text-blue-400"><?php echo htmlspecialchars($activity['full_name']); ?></span>
                                            <span class="text-gray-300"> <?php echo htmlspecialchars($activity['description']); ?></span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo $time_ago; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
            
            <!-- Right Column - Side Info -->
            <div class="space-y-6">
                
                <!-- Project Info -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                        Project Info
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-400 text-sm mb-1">PON Number</p>
                            <a href="../pon/detail.php?id=<?php echo $task['pon_id']; ?>" class="text-blue-400 hover:text-blue-300 font-semibold">
                                <?php echo htmlspecialchars($task['pon_number']); ?>
                            </a>
                        </div>
                        
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Project Name</p>
                            <p class="text-white font-semibold text-sm"><?php echo htmlspecialchars($task['subject']); ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Client</p>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($task['client_name']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Task Info -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-tasks text-purple-400 mr-2"></i>
                        Task Details
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Phase</p>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-purple-900 text-purple-200">
                                <?php echo htmlspecialchars($task['phase']); ?>
                            </span>
                        </div>
                        
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Division</p>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($task['responsible_division']); ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-400 text-sm mb-1">PIC Internal</p>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($task['pic_internal'] ?? '-'); ?></p>
                        </div>
                        
                        <?php if (!empty($task['pic_external'])): ?>
                        <div>
                            <p class="text-gray-400 text-sm mb-1">PIC External</p>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($task['pic_external']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Assigned To</p>
                            <p class="text-white font-semibold"><?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Not assigned'); ?></p>
                            <?php if ($task['assigned_role']): ?>
                                <p class="text-xs text-gray-500"><?php echo $task['assigned_role']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($task['weight_value'] > 0): ?>
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Weight</p>
                            <p class="text-white font-semibold">
                                <?php echo number_format($task['weight_value'], 2); ?> <?php echo $task['weight_unit']; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Timeline Card -->
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-calendar-alt text-green-400 mr-2"></i>
                        Timeline
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-600 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-play text-blue-400"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs">Start Date</p>
                                <p class="text-white font-semibold"><?php echo format_date_indo($task['start_date']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <div class="bg-green-600 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-flag-checkered text-green-400"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs">Target Date</p>
                                <p class="text-white font-semibold"><?php echo format_date_indo($task['finish_date']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($task['actual_start_date']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="bg-yellow-600 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-clock text-yellow-400"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs">Actual Start</p>
                                <p class="text-white font-semibold"><?php echo format_date_indo($task['actual_start_date']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($task['completed_at']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="bg-green-600 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs">Completed At</p>
                                <p class="text-white font-semibold"><?php echo format_date_indo(date('Y-m-d', strtotime($task['completed_at']))); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($task['etd_date']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="bg-purple-600 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-shipping-fast text-purple-400"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs">ETD</p>
                                <p class="text-white font-semibold"><?php echo format_date_indo($task['etd_date']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($task['eta_date']): ?>
                        <div class="flex items-center space-x-3">
                            <div class="bg-cyan-600 bg-opacity-20 rounded-lg p-2">
                                <i class="fas fa-truck text-cyan-400"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-xs">ETA</p>
                                <p class="text-white font-semibold"><?php echo format_date_indo($task['eta_date']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <?php if (hasAnyRole(['Admin', 'Engineering'])): ?>
                <div class="bg-dark-light rounded-xl p-6 shadow-xl">
                    <h2 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">
                        <i class="fas fa-bolt text-yellow-400 mr-2"></i>
                        Quick Actions
                    </h2>
                    
                    <div class="space-y-3">
                        <a 
                            href="edit.php?id=<?php echo $task_id; ?>"
                            class="block w-full bg-yellow-600 hover:bg-yellow-700 text-white text-center py-2 rounded-lg font-semibold transition"
                        >
                            <i class="fas fa-edit mr-2"></i>Edit Task
                        </a>
                        
                        <a 
                            href="../qc/upload.php?task_id=<?php echo $task_id; ?>"
                            class="block w-full bg-green-600 hover:bg-green-700 text-white text-center py-2 rounded-lg font-semibold transition"
                        >
                            <i class="fas fa-upload mr-2"></i>Upload Document
                        </a>
                        
                        <button 
                            onclick="if(confirm('Delete this task?')) window.location.href='delete.php?id=<?php echo $task_id; ?>'"
                            class="block w-full bg-red-600 hover:bg-red-700 text-white text-center py-2 rounded-lg font-semibold transition"
                        >
                            <i class="fas fa-trash mr-2"></i>Delete Task
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
        </div>
        
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
        if (confirm('Progress mencapai 100%. Set status menjadi Completed?')) {
            // Status sudah di-set
        } else {
            this.value = 99;
            updateProgressValue(99);
        }
    }
});
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>