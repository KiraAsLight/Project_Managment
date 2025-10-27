<?php

/**
 * AUTO-GENERATE TASKS FOR PON
 * 
 * Script ini akan membuat tasks otomatis untuk setiap divisi
 * berdasarkan alur kerja yang telah didefinisikan.
 * 
 * Dipanggil saat:
 * 1. PON baru dibuat (di create PON form)
 * 2. Manual trigger (jika PON sudah ada tapi belum punya tasks)
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

/**
 * Generate tasks untuk PON berdasarkan workflow divisi
 * 
 * @param int $pon_id ID dari PON yang akan di-generate tasksnya
 * @param mysqli $conn Database connection
 * @return array Result dengan status dan message
 */
function generateTasksForPON($pon_id, $conn)
{
    // Ambil data PON
    $stmt = $conn->prepare("SELECT * FROM pon WHERE pon_id = ?");
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pon = $result->fetch_assoc();

    if (!$pon) {
        return ['success' => false, 'message' => 'PON tidak ditemukan'];
    }

    // ========== TAMBAHAN: CEK DIVISI YANG SUDAH ADA TASKS ==========
    $divisions_to_check = ['Engineering', 'Purchasing', 'Fabrikasi', 'Logistik', 'QC'];
    $divisions_with_tasks = [];

    foreach ($divisions_to_check as $div) {
        $stmt_check = $conn->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE pon_id = ? AND responsible_division = ?");
        $stmt_check->bind_param("is", $pon_id, $div);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $div_count = $result_check->fetch_assoc()['cnt'];

        if ($div_count > 0) {
            $divisions_with_tasks[] = $div;
        }
    }
    // ========== END TAMBAHAN ==========

    // Template tasks untuk setiap divisi
    $task_templates = getTaskTemplates($pon);

    // Insert tasks ke database
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($task_templates as $task) {
        // ========== SKIP DIVISI YANG SUDAH ADA TASKS ==========
        if (in_array($task['responsible_division'], $divisions_with_tasks)) {
            $skipped++;
            continue;
        }
        // ========== END SKIP ==========

        $stmt = $conn->prepare("
            INSERT INTO tasks (
                pon_id, phase, responsible_division, task_name, description,
                start_date, finish_date, status, progress, depends_on_material
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssssssdi",
            $task['pon_id'],
            $task['phase'],
            $task['responsible_division'],
            $task['task_name'],
            $task['description'],
            $task['start_date'],
            $task['finish_date'],
            $task['status'],
            $task['progress'],
            $task['depends_on_material']
        );

        if ($stmt->execute()) {
            $inserted++;
        } else {
            $errors[] = "Failed to insert: {$task['task_name']} - " . $stmt->error;
        }
    }

    // ========== UPDATED RESPONSE MESSAGE ==========
    $message = "Berhasil generate {$inserted} tasks untuk PON {$pon['pon_number']}";
    if ($skipped > 0) {
        $message .= " ({$skipped} tasks di-skip karena divisi sudah ada tasks)";
    }
    // ========== END ==========

    return [
        'success' => true,
        'message' => $message,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}

/**
 * Define task templates untuk setiap divisi
 * 
 * @param array $pon Data PON
 * @return array Array of task templates
 */
function getTaskTemplates($pon)
{
    $pon_id = $pon['pon_id'];
    $tasks = [];

    // ============================================
    // ENGINEERING TASKS
    // ============================================
    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Engineering',
        'responsible_division' => 'Engineering',
        'task_name' => 'Upload Gambar Teknik (Drawings)',
        'description' => 'Upload semua file gambar teknik (PDF, DWG, atau format lainnya) yang diperlukan untuk proyek ini. Gambar harus lengkap dan sesuai spesifikasi client.',
        'start_date' => $pon['engineering_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['engineering_finish_date'] ?? date('Y-m-d', strtotime('+7 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 0
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Engineering',
        'responsible_division' => 'Engineering',
        'task_name' => 'Upload Material List (Excel)',
        'description' => 'Upload file Excel yang berisi daftar lengkap material yang dibutuhkan. Format harus sesuai template (Assy Marking, Material Name, Qty, Dimensions, dll). File ini akan digunakan oleh Purchasing untuk membuat PO.',
        'start_date' => $pon['engineering_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['engineering_finish_date'] ?? date('Y-m-d', strtotime('+7 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 0
    ];

    // ============================================
    // PURCHASING TASKS
    // ============================================
    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Engineering',
        'responsible_division' => 'Purchasing',
        'task_name' => 'Input Data Supplier',
        'description' => 'Menambahkan atau memperbarui data supplier yang akan digunakan untuk pengadaan material. Termasuk informasi kontak, alamat, dan term pembayaran.',
        'start_date' => $pon['purchasing_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['purchasing_finish_date'] ?? date('Y-m-d', strtotime('+14 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1 // Bergantung pada material list dari Engineering
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Engineering',
        'responsible_division' => 'Purchasing',
        'task_name' => 'Membuat Purchase Order (PO)',
        'description' => 'Membuat PO untuk setiap material yang ada di Material List. Setiap PO harus mencantumkan supplier, harga, qty, delivery date, dan term pembayaran yang telah disepakati.',
        'start_date' => $pon['purchasing_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['purchasing_finish_date'] ?? date('Y-m-d', strtotime('+14 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Engineering',
        'responsible_division' => 'Purchasing',
        'task_name' => 'Monitoring Pemesanan Material',
        'description' => 'Memantau status setiap PO yang telah dibuat: konfirmasi dari supplier, progress produksi material (jika custom), estimasi pengiriman, dan update delivery schedule.',
        'start_date' => $pon['purchasing_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['purchasing_finish_date'] ?? date('Y-m-d', strtotime('+21 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    // ============================================
    // FABRIKASI TASKS
    // ============================================
    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Fabrication + Trial',
        'responsible_division' => 'Fabrikasi',
        'task_name' => 'Mengelola Material (Raw & Finished)',
        'description' => 'Mengelola inventory material yang masuk ke workshop. Update status material: Raw Material (baru datang), In Progress (sedang dikerjakan), atau Finished Goods (sudah jadi). Termasuk tracking lokasi penyimpanan dan qty available.',
        'start_date' => $pon['fabrikasi_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['fabrikasi_finish_date'] ?? date('Y-m-d', strtotime('+30 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Fabrication + Trial',
        'responsible_division' => 'Fabrikasi',
        'task_name' => 'Produksi & Workshop Management',
        'description' => 'Mengelola proses produksi di workshop: cutting, welding, assembly, painting, dll. Tracking progress per item/assy, man-hours, dan masalah yang terjadi di lapangan.',
        'start_date' => $pon['fabrikasi_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['fabrikasi_finish_date'] ?? date('Y-m-d', strtotime('+30 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    // ============================================
    // LOGISTIK TASKS
    // ============================================
    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Delivery',
        'responsible_division' => 'Logistik',
        'task_name' => 'Monitoring Delivery dari Supplier',
        'description' => 'Memantau pengiriman material dari supplier. Track nomor resi, estimasi kedatangan, status pengiriman (in transit, arrived, dll), dan koordinasi dengan gudang untuk penerimaan barang.',
        'start_date' => $pon['logistik_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['logistik_finish_date'] ?? date('Y-m-d', strtotime('+45 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Delivery',
        'responsible_division' => 'Logistik',
        'task_name' => 'Koordinasi Pengiriman ke Site Client',
        'description' => 'Mengatur pengiriman barang jadi dari workshop ke lokasi project client. Termasuk pemilihan vendor transport, packaging, loading, tracking pengiriman, dan konfirmasi penerimaan di site.',
        'start_date' => $pon['logistik_start_date'] ?? date('Y-m-d'),
        'finish_date' => $pon['logistik_finish_date'] ?? date('Y-m-d', strtotime('+50 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    // ============================================
    // QC TASKS
    // ============================================
    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Fabrication + Trial',
        'responsible_division' => 'QC',
        'task_name' => 'Pengujian Material (Material Testing)',
        'description' => 'Melakukan pengujian material yang datang dari supplier sesuai spesifikasi: uji tarik, uji kekerasan, chemical composition, dll. Upload hasil test report, foto sample, dan certificate dari lab.',
        'start_date' => date('Y-m-d'),
        'finish_date' => date('Y-m-d', strtotime('+60 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Fabrication + Trial',
        'responsible_division' => 'QC',
        'task_name' => 'Inspeksi Workshop (In-Process Inspection)',
        'description' => 'Melakukan inspeksi berkala selama proses produksi di workshop: dimensional check, visual inspection, weld quality, painting thickness, dll. Upload foto dokumentasi, checklist form, dan NCR jika ada temuan.',
        'start_date' => date('Y-m-d'),
        'finish_date' => date('Y-m-d', strtotime('+60 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Erection',
        'responsible_division' => 'QC',
        'task_name' => 'Inspeksi Lapangan (Site Inspection)',
        'description' => 'Inspeksi struktur yang sudah terpasang di site client. Cek alignment, verticality, foundation, dll. Upload foto kondisi di lapangan dan laporan inspeksi.',
        'start_date' => date('Y-m-d'),
        'finish_date' => date('Y-m-d', strtotime('+70 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    $tasks[] = [
        'pon_id' => $pon_id,
        'phase' => 'Erection',
        'responsible_division' => 'QC',
        'task_name' => 'Cek Camber & Pengencangan Baut',
        'description' => 'Melakukan pengecekan camber beam dan torque baut sesuai spesifikasi. Upload data measurement, foto sebelum-sesudah, checklist pengencangan baut, dan report final inspection.',
        'start_date' => date('Y-m-d'),
        'finish_date' => date('Y-m-d', strtotime('+75 days')),
        'status' => 'Not Started',
        'progress' => 0,
        'depends_on_material' => 1
    ];

    return $tasks;
}

// ============================================
// MAIN EXECUTION (Jika diakses langsung)
// ============================================

// Untuk testing atau manual trigger
if (isset($_GET['pon_id']) && isset($_GET['action']) && $_GET['action'] == 'generate') {
    require_login();

    $pon_id = (int)$_GET['pon_id'];
    $conn = getDBConnection();

    $result = generateTasksForPON($pon_id, $conn);

    closeDBConnection($conn);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Jika diinclude dari file lain, function generateTasksForPON() bisa dipanggil
// Contoh: di create_pon.php setelah insert PON berhasil

?>