<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar -->
<aside class="w-64 bg-dark-light min-h-screen fixed left-0 top-0 shadow-2xl">

    <!-- Logo -->
    <div class="p-6 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-project-diagram text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white">PT. Wiratama</h1>
                <p class="text-xs text-gray-400">Globalindo Jaya</p>
            </div>
        </div>
    </div>

    <!-- User Info -->
    <div class="p-4 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                <span class="text-white font-bold">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </span>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-white"><?php echo $_SESSION['full_name']; ?></p>
                <p class="text-xs text-gray-400"><?php echo $_SESSION['role']; ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="p-4">
        <ul class="space-y-2">

            <!-- Dashboard -->
            <li>
                <a href="<?php echo BASE_URL; ?>modules/dashboard/"
                    class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 <?php echo $current_page == 'index' ? 'sidebar-item-active' : ''; ?>">
                    <i class="fas fa-home text-blue-400"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- PON Management (Admin & All Division) -->
            <?php if (hasAnyRole(['Admin', 'Engineering', 'Purchasing', 'Fabrikasi', 'Logistik', 'QC'])): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/pon/list.php"
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 <?php echo $current_page == 'list' ? 'sidebar-item-active' : ''; ?>">
                        <i class="fas fa-file-alt text-green-400"></i>
                        <span>PON</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Task List -->
            <li>
                <a href="<?php echo BASE_URL; ?>modules/tasks/manage.php"
                    class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 <?php echo $current_page == 'manage' ? 'sidebar-item-active' : ''; ?>">
                    <i class="fas fa-tasks text-yellow-400"></i>
                    <span>Task List</span>
                </a>
            </li>

            <!-- QC Documents (QC & Admin) -->
            <?php if (hasAnyRole(['Admin', 'QC'])): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/qc/upload.php"
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-file-upload text-purple-400"></i>
                        <span>QC Documents</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Reports (Admin) -->
            <?php if (hasRole('Admin')): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/reports/index.php"
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-chart-bar text-pink-400"></i>
                        <span>Reports</span>
                    </a>
                </li>
            <?php endif; ?>

        </ul>
    </nav>

    <!-- Logout -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700">
        <a href="<?php echo BASE_URL; ?>modules/auth/logout.php"
            class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-red-600 text-red-400 hover:text-white">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>