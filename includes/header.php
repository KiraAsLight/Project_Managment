<?php
if (!isLoggedIn()) {
    redirect('modules/auth/login.php');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Chart.js untuk grafik -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Font Awesome untuk icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        /* Smooth transitions */
        * {
            transition: all 0.3s ease;
        }

        /* Gradient backgrounds */
        .gradient-blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .gradient-green {
            background: linear-gradient(135deg, #0ba360 0%, #3cba92 100%);
        }

        .gradient-orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .gradient-red {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        /* Card hover effects */
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        /* Circular progress */
        .circular-progress {
            transform: rotate(-90deg);
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1e293b;
        }

        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Sidebar active state */
        .sidebar-item-active {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding-left: 12px;
        }
    </style>

    <script>
        // Tailwind config
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#8b5cf6',
                        dark: '#0f172a',
                        'dark-light': '#1e293b',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-900 text-gray-100">