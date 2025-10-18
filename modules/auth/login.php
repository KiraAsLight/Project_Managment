<?php

/**
 * Halaman Login
 * Mendukung multi-role authentication
 */

require_once '../../config/database.php';
require_once '../../config/config.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('modules/dashboard/');
}

$error_message = '';

// Proses login saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan sanitasi input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input tidak boleh kosong
    if (empty($username) || empty($password)) {
        $error_message = "Username dan password harus diisi!";
    } else {
        $conn = getDBConnection();

        // Prepared statement untuk mencegah SQL Injection
        $stmt = $conn->prepare("SELECT user_id, username, password, full_name, role, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Cek apakah user aktif
            if ($user['is_active'] == 0) {
                $error_message = "Akun Anda telah dinonaktifkan. Hubungi administrator.";
            }
            // Verifikasi password
            elseif (password_verify($password, $user['password'])) {
                // Login berhasil - Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();

                // Log aktivitas
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'Login', 'User login ke sistem', ?)");
                $log_stmt->bind_param("is", $user['user_id'], $ip_address);
                $log_stmt->execute();

                // Redirect ke dashboard
                redirect('modules/dashboard/');
            } else {
                $error_message = "Username atau password salah!";
            }
        } else {
            $error_message = "Username atau password salah!";
        }

        $stmt->close();
        closeDBConnection($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md">
        <!-- Card Login -->
        <div class="bg-white rounded-lg shadow-2xl overflow-hidden">

            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
                <h1 class="text-2xl font-bold text-center">Project Management System</h1>
                <p class="text-center text-blue-100 mt-1">PT. Wiratama Globalindo Jaya</p>
            </div>

            <!-- Form Login -->
            <div class="p-8">

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <p class="font-medium">Error!</p>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">

                    <!-- Username -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Username</label>
                        <input
                            type="text"
                            name="username"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Masukkan username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Password</label>
                        <input
                            type="password"
                            name="password"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Masukkan password">
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg">
                        Login
                    </button>

                </form>

                <!-- Info Default User -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                    <p class="font-semibold mb-2">Default Login:</p>
                    <p>Username: <code class="bg-gray-200 px-2 py-1 rounded">admin</code></p>
                    <p>Password: <code class="bg-gray-200 px-2 py-1 rounded">admin123</code></p>
                </div>

            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-white mt-6 text-sm">
            © 2025 PT. Wiratama Globalindo Jaya
        </p>
    </div>

</body>

</html>