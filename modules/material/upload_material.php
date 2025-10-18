<?php
// modules/material/upload_material.php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();
if (!hasAnyRole(['Admin', 'Engineering'])) {
    die("Access denied");
}

$pon_id = isset($_GET['pon_id']) ? (int)$_GET['pon_id'] : 0;
?>

<div class="space-y-4">
    <div class="bg-blue-900 bg-opacity-20 border border-blue-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <i class="fas fa-info-circle text-blue-400 mt-1"></i>
            <div>
                <p class="text-blue-300 font-semibold">Informasi Upload</p>
                <p class="text-blue-200 text-sm mt-1">
                    Upload file Excel untuk auto-import ke sistem, atau PDF untuk dokumentasi.
                    File Excel harus mengikuti format kolom material_lists.
                </p>
            </div>
        </div>
    </div>

    <form id="uploadForm" enctype="multipart/form-data">
        <input type="hidden" name="pon_id" value="<?php echo $pon_id; ?>">
        <input type="hidden" name="division" value="Engineering">
        
        <!-- File Type Selection -->
        <div class="mb-4">
            <label class="block text-gray-300 text-sm font-medium mb-2">Jenis File</label>
            <div class="flex space-x-4">
                <label class="flex items-center">
                    <input type="radio" name="file_type" value="excel" checked class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-gray-300">Excel (.xlsx, .xls)</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="file_type" value="pdf" class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-gray-300">PDF (.pdf)</span>
                </label>
            </div>
        </div>

        <!-- File Upload -->
        <div class="mb-4">
            <label class="block text-gray-300 text-sm font-medium mb-2">
                Pilih File <span class="text-red-500">*</span>
            </label>
            <div class="flex items-center justify-center w-full">
                <label for="material_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-600 rounded-lg cursor-pointer bg-gray-800 hover:bg-gray-750 transition">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-400">
                            <span class="font-semibold">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-xs text-gray-500 mt-1" id="fileRequirements">
                            XLSX, XLS (MAX. 10MB) - Format sesuai material_lists
                        </p>
                    </div>
                    <input id="material_file" name="material_file" type="file" class="hidden" accept=".xlsx,.xls,.pdf" required>
                </label>
            </div>
            <div id="fileName" class="text-sm text-gray-400 mt-2 hidden"></div>
        </div>

        <!-- Excel Format Info -->
        <div id="excelInfo" class="bg-gray-800 rounded-lg p-4 mb-4">
            <h4 class="text-white font-semibold mb-2">Format Kolom Excel:</h4>
            <div class="text-sm text-gray-300 space-y-1">
                <!-- ... format table sama seperti sebelumnya ... -->
            </div>
        </div>

        <!-- PDF Info -->
        <div id="pdfInfo" class="bg-gray-800 rounded-lg p-4 mb-4 hidden">
            <h4 class="text-white font-semibold mb-2">Upload PDF:</h4>
            <p class="text-sm text-gray-300">
                File PDF akan disimpan sebagai dokumentasi material list. 
                Tidak ada auto-import data, hanya untuk arsip.
            </p>
        </div>

        <!-- Description -->
        <div class="mb-4">
            <label class="block text-gray-300 text-sm font-medium mb-2">Keterangan</label>
            <textarea name="description" rows="2" 
                      class="w-full bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 focus:border-blue-500"
                      placeholder="Tambahkan keterangan tentang file ini..."></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="hideUploadModal()"
                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
                Batal
            </button>
            <button type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center space-x-2">
                <i class="fas fa-upload"></i>
                <span>Upload File</span>
            </button>
        </div>
    </form>
</div>