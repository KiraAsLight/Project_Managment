<?php

/**
 * QC Document Upload & Management
 * Upload dokumen QC dengan approval workflow
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

$conn = getDBConnection();

// Get task_id atau pon_id dari parameter
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$pon_id = isset($_GET['pon_id']) ? (int)$_GET['pon_id'] : null;

// Jika ada task_id, get task info
$task = null;
$pon = null;

if ($task_id) {
    $stmt = $conn->prepare("SELECT t.*, p.pon_number, p.project_name, p.client_name 
                           FROM tasks t 
                           JOIN pon p ON t.pon_id = p.pon_id 
                           WHERE t.task_id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();

    if ($task) {
        $pon_id = $task['pon_id'];
    }
}

// Get PON info
if ($pon_id) {
    $stmt = $conn->prepare("SELECT * FROM pon WHERE pon_id = ?");
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pon = $result->fetch_assoc();
}

// Get all tasks untuk PON ini (untuk dropdown)
$tasks_list = [];
if ($pon_id) {
    $tasks_query = "SELECT task_id, task_name, phase FROM tasks WHERE pon_id = ? ORDER BY start_date";
    $stmt = $conn->prepare($tasks_query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $tasks_result = $stmt->get_result();
    while ($row = $tasks_result->fetch_assoc()) {
        $tasks_list[] = $row;
    }
}

// Get existing documents
$documents = [];
$docs_query = "SELECT 
                d.*,
                t.task_name,
                t.phase,
                u.full_name as uploaded_by_name,
                u.role as uploaded_by_role,
                u2.full_name as approved_by_name
               FROM qc_documents d
               LEFT JOIN tasks t ON d.task_id = t.task_id
               LEFT JOIN users u ON d.uploaded_by = u.user_id
               LEFT JOIN users u2 ON d.approved_by = u2.user_id
               WHERE d.pon_id = ?
               ORDER BY d.uploaded_at DESC";

$stmt = $conn->prepare($docs_query);
$stmt->bind_param("i", $pon_id);
$stmt->execute();
$docs_result = $stmt->get_result();
while ($row = $docs_result->fetch_assoc()) {
    $documents[] = $row;
}

$errors = [];
$success_message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'upload') {
        // Validate permissions
        if (!hasAnyRole(['Admin', 'QC', 'Engineering'])) {
            $errors[] = "Anda tidak memiliki akses untuk upload dokumen";
        } else {
            $upload_task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
            $document_type = sanitize_input($_POST['document_type']);
            $document_number = sanitize_input($_POST['document_number']);
            $issue_date = !empty($_POST['issue_date']) ? sanitize_input($_POST['issue_date']) : null;
            $expiry_date = !empty($_POST['expiry_date']) ? sanitize_input($_POST['expiry_date']) : null;
            $notes = sanitize_input($_POST['notes']);
            $tags = sanitize_input($_POST['tags']);

            // Validate
            if (!$upload_task_id) {
                $errors[] = "Task harus dipilih";
            }

            if (empty($document_type)) {
                $errors[] = "Document Type harus dipilih";
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = "File harus diupload";
            }

            if (empty($errors) && isset($_FILES['file'])) {
                $file = $_FILES['file'];

                // Validate file
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "Error uploading file";
                } else {
                    $file_size = $file['size'];
                    $file_tmp = $file['tmp_name'];
                    $file_name = $file['name'];
                    $file_type = $file['type'];

                    // Check file size (max 10MB)
                    if ($file_size > 10485760) {
                        $errors[] = "File terlalu besar. Maksimal 10MB";
                    }

                    // Get file extension
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    // Allowed extensions
                    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

                    if (!in_array($file_ext, $allowed)) {
                        $errors[] = "Format file tidak didukung. Allowed: " . implode(', ', $allowed);
                    }

                    if (empty($errors)) {
                        // Create upload directory if not exists
                        $upload_dir = '../../assets/uploads/qc_files/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // Generate unique filename
                        $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
                        $file_path = 'assets/uploads/qc_files/' . $new_filename;
                        $full_path = '../../' . $file_path;

                        // Move uploaded file
                        if (move_uploaded_file($file_tmp, $full_path)) {
                            // Insert to database
                            $insert_query = "INSERT INTO qc_documents (
                                task_id, pon_id, document_type, file_name, file_path, 
                                file_type, file_size, document_number, issue_date, 
                                expiry_date, notes, tags, uploaded_by, approval_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

                            $stmt = $conn->prepare($insert_query);
                            $stmt->bind_param(
                                "iissssississi",
                                $upload_task_id,
                                $pon_id,
                                $document_type,
                                $file_name,
                                $file_path,
                                $file_type,
                                $file_size,
                                $document_number,
                                $issue_date,
                                $expiry_date,
                                $notes,
                                $tags,
                                $_SESSION['user_id']
                            );

                            if ($stmt->execute()) {
                                // Log activity
                                log_activity(
                                    $conn,
                                    $_SESSION['user_id'],
                                    'Upload QC Document',
                                    "Upload dokumen QC '{$file_name}' untuk task ID: {$upload_task_id}"
                                );

                                $success_message = "Dokumen berhasil diupload!";

                                // Refresh page
                                header("Location: upload.php?pon_id={$pon_id}&success=1");
                                exit;
                            } else {
                                unlink($full_path); // Delete file if DB insert fails
                                $errors[] = "Gagal menyimpan ke database: " . $stmt->error;
                            }
                        } else {
                            $errors[] = "Gagal mengupload file";
                        }
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'approve' || $_POST['action'] === 'reject') {
        // Handle approval/rejection
        if (!hasAnyRole(['Admin', 'QC'])) {
            $errors[] = "Anda tidak memiliki akses untuk approve/reject dokumen";
        } else {
            $doc_id = (int)$_POST['doc_id'];
            $new_status = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';
            $rejection_reason = $_POST['action'] === 'reject' ? sanitize_input($_POST['rejection_reason']) : null;

            $update_query = "UPDATE qc_documents SET 
                            approval_status = ?,
                            approved_by = ?,
                            approved_at = NOW(),
                            rejection_reason = ?
                            WHERE doc_id = ?";

            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sisi", $new_status, $_SESSION['user_id'], $rejection_reason, $doc_id);

            if ($stmt->execute()) {
                log_activity(
                    $conn,
                    $_SESSION['user_id'],
                    ucfirst($_POST['action']) . ' QC Document',
                    ucfirst($_POST['action']) . " dokumen QC ID: {$doc_id}"
                );

                header("Location: upload.php?pon_id={$pon_id}&success=2");
                exit;
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        // Handle delete
        if (!hasRole('Admin')) {
            $errors[] = "Only Admin can delete documents";
        } else {
            $doc_id = (int)$_POST['doc_id'];

            // Get file path
            $stmt = $conn->prepare("SELECT file_path, file_name FROM qc_documents WHERE doc_id = ?");
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $doc = $result->fetch_assoc();

            if ($doc) {
                // Delete physical file
                $full_path = '../../' . $doc['file_path'];
                if (file_exists($full_path)) {
                    unlink($full_path);
                }

                // Delete from database
                $delete_stmt = $conn->prepare("DELETE FROM qc_documents WHERE doc_id = ?");
                $delete_stmt->bind_param("i", $doc_id);

                if ($delete_stmt->execute()) {
                    log_activity(
                        $conn,
                        $_SESSION['user_id'],
                        'Delete QC Document',
                        "Delete dokumen QC '{$doc['file_name']}' (ID: {$doc_id})"
                    );

                    header("Location: upload.php?pon_id={$pon_id}&success=3");
                    exit;
                }
            }
        }
    }
}

// Success message dari redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $success_message = "Dokumen berhasil diupload!";
    } elseif ($_GET['success'] == 2) {
        $success_message = "Status dokumen berhasil diupdate!";
    } elseif ($_GET['success'] == 3) {
        $success_message = "Dokumen berhasil dihapus!";
    }
}

$page_title = "QC Document Management";
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
                    <h1 class="text-3xl font-bold text-white mb-2">QC Document Management</h1>
                    <?php if ($pon): ?>
                        <div class="flex items-center space-x-3 text-gray-400">
                            <a href="../pon/detail.php?id=<?php echo $pon['pon_id']; ?>" class="text-blue-400 hover:text-blue-300">
                                <?php echo htmlspecialchars($pon['pon_number']); ?>
                            </a>
                            <span>·</span>
                            <span><?php echo htmlspecialchars($pon['subject']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-3">
                    <?php if ($task): ?>
                        <a href="../tasks/detail.php?id=<?php echo $task_id; ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Task</span>
                        </a>
                    <?php elseif ($pon): ?>
                        <a href="../pon/detail.php?id=<?php echo $pon_id; ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to PON</span>
                        </a>
                    <?php endif; ?>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            $total_docs = count($documents);
            $approved_docs = count(array_filter($documents, function ($d) {
                return $d['approval_status'] == 'Approved';
            }));
            $pending_docs = count(array_filter($documents, function ($d) {
                return $d['approval_status'] == 'Pending';
            }));
            $rejected_docs = count(array_filter($documents, function ($d) {
                return $d['approval_status'] == 'Rejected';
            }));
            ?>

            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-blue-500">
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-file-alt text-blue-400 text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold text-white"><?php echo $total_docs; ?></h3>
                        <p class="text-gray-400 text-sm">Total Documents</p>
                    </div>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-green-500">
                <div class="flex items-center space-x-4">
                    <div class="bg-green-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold text-white"><?php echo $approved_docs; ?></h3>
                        <p class="text-gray-400 text-sm">Approved</p>
                    </div>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-yellow-500">
                <div class="flex items-center space-x-4">
                    <div class="bg-yellow-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-clock text-yellow-400 text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold text-white"><?php echo $pending_docs; ?></h3>
                        <p class="text-gray-400 text-sm">Pending</p>
                    </div>
                </div>
            </div>

            <div class="bg-dark-light rounded-xl p-6 shadow-xl border-l-4 border-red-500">
                <div class="flex items-center space-x-4">
                    <div class="bg-red-500 bg-opacity-20 rounded-lg p-3">
                        <i class="fas fa-times-circle text-red-400 text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold text-white"><?php echo $rejected_docs; ?></h3>
                        <p class="text-gray-400 text-sm">Rejected</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Upload Form -->
            <?php if (hasAnyRole(['Admin', 'QC', 'Engineering'])): ?>
                <div class="lg:col-span-1">
                    <div class="bg-dark-light rounded-xl p-6 shadow-xl sticky top-8">
                        <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-700 pb-3">
                            <i class="fas fa-upload text-green-400 mr-2"></i>
                            Upload Document
                        </h2>

                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="upload">

                            <!-- Task Selection -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    Task <span class="text-red-500">*</span>
                                </label>
                                <select
                                    name="task_id"
                                    required
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Task</option>
                                    <?php foreach ($tasks_list as $t): ?>
                                        <option value="<?php echo $t['task_id']; ?>" <?php echo ($task && $t['task_id'] == $task_id) ? 'selected' : ''; ?>>
                                            [<?php echo $t['phase']; ?>] <?php echo htmlspecialchars($t['task_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Document Type -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    Document Type <span class="text-red-500">*</span>
                                </label>
                                <select
                                    name="document_type"
                                    required
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Type</option>
                                    <option value="WPS/PQR">WPS/PQR</option>
                                    <option value="Galvanizing Certificate">Galvanizing Certificate</option>
                                    <option value="Mill Certificate SM490">Mill Certificate SM490</option>
                                    <option value="Inspection Report">Inspection Report</option>
                                    <option value="Mill Certificate Deck Plate">Mill Certificate Deck Plate</option>
                                    <option value="Visual Welding Report">Visual Welding Report</option>
                                    <option value="Mill Certificate Pipe/WF/RB">Mill Certificate Pipe/WF/RB</option>
                                    <option value="Dimensional Report">Dimensional Report</option>
                                    <option value="Photo Inspection">Photo Inspection</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- File Upload -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">
                                    File <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="file"
                                    name="file"
                                    required
                                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                <p class="text-gray-500 text-sm mt-1">Max 10MB. Format: jpg, png, pdf, doc, xls</p>
                            </div>

                            <!-- Document Number -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Document Number</label>
                                <input
                                    type="text"
                                    name="document_number"
                                    placeholder="DOC-2024-001"
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <!-- Issue Date -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Issue Date</label>
                                <input
                                    type="date"
                                    name="issue_date"
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <!-- Expiry Date -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Expiry Date</label>
                                <input
                                    type="date"
                                    name="expiry_date"
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Notes</label>
                                <textarea
                                    name="notes"
                                    rows="3"
                                    placeholder="Catatan dokumen..."
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>

                            <!-- Tags -->
                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Tags</label>
                                <input
                                    type="text"
                                    name="tags"
                                    placeholder="welding, inspection, final"
                                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                <p class="text-gray-500 text-sm mt-1">Pisahkan dengan koma</p>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition flex items-center justify-center space-x-2">
                                <i class="fas fa-upload"></i>
                                <span>Upload Document</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Documents List -->
            <div class="<?php echo hasAnyRole(['Admin', 'QC', 'Engineering']) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
                <div class="bg-dark-light rounded-xl shadow-xl">
                    <div class="p-6 border-b border-gray-700">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-folder-open text-blue-400 mr-2"></i>
                            Document List (<?php echo count($documents); ?>)
                        </h2>
                    </div>

                    <?php if (empty($documents)): ?>
                        <div class="p-12 text-center">
                            <i class="fas fa-file-alt text-6xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400 text-lg">Belum ada dokumen QC</p>
                        </div>
                    <?php else: ?>
                        <div class="p-6 space-y-4">
                            <?php foreach ($documents as $doc): ?>
                                <div class="bg-gray-800 rounded-lg p-5 hover:bg-gray-750 transition border border-gray-700">
                                    <div class="flex items-start justify-between">

                                        <!-- Document Info -->
                                        <div class="flex items-start space-x-4 flex-1">
                                            <!-- Icon -->
                                            <div class="bg-green-600 bg-opacity-20 rounded-lg p-4">
                                                <?php if (strpos($doc['file_type'], 'image') !== false): ?>
                                                    <i class="fas fa-image text-green-400 text-2xl"></i>
                                                <?php elseif (strpos($doc['file_type'], 'pdf') !== false): ?>
                                                    <i class="fas fa-file-pdf text-green-400 text-2xl"></i>
                                                <?php elseif (strpos($doc['file_type'], 'word') !== false || strpos($doc['file_type'], 'doc') !== false): ?>
                                                    <i class="fas fa-file-word text-green-400 text-2xl"></i>
                                                <?php elseif (strpos($doc['file_type'], 'excel') !== false || strpos($doc['file_type'], 'sheet') !== false): ?>
                                                    <i class="fas fa-file-excel text-green-400 text-2xl"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file text-green-400 text-2xl"></i>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Details -->
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div>
                                                        <h4 class="text-white font-bold text-lg mb-1">
                                                            <?php echo htmlspecialchars($doc['file_name']); ?>
                                                        </h4>
                                                        <div class="flex items-center space-x-3 text-sm">
                                                            <span class="px-3 py-1 rounded-full bg-purple-900 text-purple-200 font-semibold">
                                                                <?php echo $doc['document_type']; ?>
                                                            </span>
                                                            <span class="text-gray-400">
                                                                <?php echo round($doc['file_size'] / 1024, 2); ?> KB
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <!-- Status Badge -->
                                                    <span class="px-4 py-2 rounded-full text-sm font-bold <?php
                                                                                                            echo $doc['approval_status'] == 'Approved' ? 'bg-green-600' : ($doc['approval_status'] == 'Rejected' ? 'bg-red-600' : 'bg-yellow-600');
                                                                                                            ?> text-white">
                                                        <?php echo $doc['approval_status']; ?>
                                                    </span>
                                                </div>

                                                <!-- Task Info -->
                                                <p class="text-sm text-gray-400 mb-2">
                                                    <i class="fas fa-tasks mr-1"></i>
                                                    <span class="font-semibold">[<?php echo $doc['phase']; ?>]</span> <?php echo htmlspecialchars($doc['task_name']); ?>
                                                </p>

                                                <?php if (!empty($doc['document_number'])): ?>
                                                    <p class="text-sm text-gray-400 mb-1">
                                                        <i class="fas fa-hashtag mr-1"></i>
                                                        Doc No: <?php echo htmlspecialchars($doc['document_number']); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($doc['issue_date'])): ?>
                                                    <p class="text-sm text-gray-400 mb-1">
                                                        <i class="fas fa-calendar-alt mr-1"></i>
                                                        Issue: <?php echo format_date_indo($doc['issue_date']); ?>
                                                        <?php if (!empty($doc['expiry_date'])): ?>
                                                            · Expiry: <?php echo format_date_indo($doc['expiry_date']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>

                                                <p class="text-xs text-gray-500 mt-2">
                                                    Uploaded by <span class="font-semibold text-blue-400"><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></span> ·
                                                    <?php echo format_date_indo(date('Y-m-d', strtotime($doc['uploaded_at']))); ?>
                                                </p>

                                                <?php if (!empty($doc['notes'])): ?>
                                                    <p class="text-sm text-gray-300 mt-2 p-3 bg-gray-900 rounded">
                                                        <?php echo htmlspecialchars($doc['notes']); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if ($doc['approval_status'] == 'Rejected' && !empty($doc['rejection_reason'])): ?>
                                                    <div class="mt-2 p-3 bg-red-900 bg-opacity-30 border border-red-700 rounded">
                                                        <p class="text-sm text-red-200">
                                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                                            <span class="font-semibold">Rejection Reason:</span> <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($doc['approval_status'] == 'Approved' && !empty($doc['approved_by_name'])): ?>
                                                    <p class="text-xs text-green-400 mt-2">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Approved by <?php echo htmlspecialchars($doc['approved_by_name']); ?> ·
                                                        <?php echo format_date_indo(date('Y-m-d', strtotime($doc['approved_at']))); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex flex-col space-y-2 ml-4">
                                            <!-- Download -->
                                            <a
                                                href="<?php echo BASE_URL . $doc['file_path']; ?>"
                                                target="_blank"
                                                download
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center transition"
                                                title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>

                                            <!-- Preview (for images and PDFs) -->
                                            <?php if (strpos($doc['file_type'], 'image') !== false || strpos($doc['file_type'], 'pdf') !== false): ?>
                                                <a
                                                    href="<?php echo BASE_URL . $doc['file_path']; ?>"
                                                    target="_blank"
                                                    class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-center transition"
                                                    title="Preview">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Approve/Reject (QC & Admin only) -->
                                            <?php if (hasAnyRole(['Admin', 'QC']) && $doc['approval_status'] == 'Pending'): ?>
                                                <button
                                                    onclick="approveDocument(<?php echo $doc['doc_id']; ?>)"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-center transition"
                                                    title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button
                                                    onclick="rejectDocument(<?php echo $doc['doc_id']; ?>)"
                                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-center transition"
                                                    title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>

                                            <!-- Delete (Admin only) -->
                                            <?php if (hasRole('Admin')): ?>
                                                <button
                                                    onclick="deleteDocument(<?php echo $doc['doc_id']; ?>, '<?php echo htmlspecialchars($doc['file_name']); ?>')"
                                                    class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-center transition"
                                                    title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>

</div>

<!-- Approve Form (Hidden) -->
<form id="approveForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="doc_id" id="approve_doc_id">
</form>

<!-- Reject Form (Hidden) -->
<form id="rejectForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="doc_id" id="reject_doc_id">
    <input type="hidden" name="rejection_reason" id="rejection_reason">
</form>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="doc_id" id="delete_doc_id">
</form>

<!-- JavaScript -->
<script>
    function approveDocument(docId) {
        if (confirm('Approve dokumen ini?')) {
            document.getElementById('approve_doc_id').value = docId;
            document.getElementById('approveForm').submit();
        }
    }

    function rejectDocument(docId) {
        const reason = prompt('Alasan rejection:');
        if (reason !== null && reason.trim() !== '') {
            document.getElementById('reject_doc_id').value = docId;
            document.getElementById('rejection_reason').value = reason;
            document.getElementById('rejectForm').submit();
        }
    }

    function deleteDocument(docId, fileName) {
        if (confirm(`Delete dokumen "${fileName}"?\n\nFile akan dihapus permanen!`)) {
            document.getElementById('delete_doc_id').value = docId;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php
closeDBConnection($conn);
include '../../includes/footer.php';
?>