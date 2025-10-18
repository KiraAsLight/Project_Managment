<?php
// check_upload.php - UPDATED

// Check if uploads directory exists and writable
$upload_dir = __DIR__ . '/uploads/';  // ← PAKAI __DIR__
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "Created upload directory: " . $upload_dir . "<br>";
}

echo "<h3>Upload Directory:</h3>";
echo "Upload dir: " . $upload_dir . "<br>";
echo "Upload dir exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "<br>";
echo "Upload dir writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "<br>";

// Test upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Result:</h3>";

    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        echo "<span style='color: green;'>✅ UPLOAD SUCCESS!</span><br>";

        // Try to move file dengan path yang benar
        $target_path = $upload_dir . 'test_' . time() . '_' . $_FILES['test_file']['name'];

        echo "Target path: " . $target_path . "<br>";
        echo "Temp path: " . $_FILES['test_file']['tmp_name'] . "<br>";

        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $target_path)) {
            echo "<span style='color: green;'>✅ FILE MOVED SUCCESSFULLY!</span><br>";
            echo "Saved to: " . $target_path . "<br>";
            echo "File exists: " . (file_exists($target_path) ? 'YES' : 'NO') . "<br>";
        } else {
            echo "<span style='color: red;'>❌ FAILED TO MOVE FILE</span><br>";
            echo "Error: " . error_get_last()['message'] . "<br>";
        }
    }
}

?>