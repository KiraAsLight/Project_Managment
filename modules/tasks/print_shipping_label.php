<?php

/**
 * Print Shipping Label
 * File: modules/tasks/print_shipping_label.php
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

$delivery_id = isset($_GET['delivery_id']) ? (int)$_GET['delivery_id'] : 0;

if (!$delivery_id) {
    die("Invalid delivery ID");
}

$conn = getDBConnection();

// Get delivery data with join
$query = "SELECT 
            d.*,
            mo.supplier_name,
            mo.material_type,
            mo.quantity as order_quantity,
            mo.unit,
            p.pon_number,
            p.project_name,
            p.client_name
          FROM deliveries d
          JOIN material_orders mo ON d.order_id = mo.order_id
          JOIN pon p ON mo.pon_id = p.pon_id
          WHERE d.delivery_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$result = $stmt->get_result();
$delivery = $result->fetch_assoc();

if (!$delivery) {
    die("Delivery not found");
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Label - <?php echo $delivery['delivery_number']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body {
                background: white;
            }

            .no-print {
                display: none;
            }

            .print-area {
                page-break-after: always;
            }
        }

        @page {
            size: A4;
            margin: 0;
        }

        .barcode {
            font-family: 'Libre Barcode 128', cursive;
            font-size: 48px;
            letter-spacing: 0;
        }
    </style>
</head>

<body class="bg-gray-100 p-8">

    <!-- Print Button -->
    <div class="no-print mb-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-print mr-2"></i>Shipping Label Preview
        </h1>
        <div class="space-x-2">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-print mr-2"></i>Print Label
            </button>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>
    </div>

    <!-- Shipping Label -->
    <div class="print-area bg-white shadow-lg mx-auto" style="width: 210mm; min-height: 297mm; padding: 20mm;">

        <!-- Header -->
        <div class="border-4 border-black p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-4xl font-bold">PT. WIRATAMA GLOBALINDO JAYA</h1>
                    <p class="text-gray-600 mt-2">Project Management System</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold">SHIPPING LABEL</div>
                    <div class="text-gray-600"><?php echo date('d F Y'); ?></div>
                </div>
            </div>
        </div>

        <!-- Delivery Number & Barcode -->
        <div class="border-2 border-black p-4 mb-6 text-center">
            <div class="text-sm text-gray-600 mb-2">DELIVERY NUMBER</div>
            <div class="text-4xl font-bold mb-4"><?php echo htmlspecialchars($delivery['delivery_number']); ?></div>
            <?php if ($delivery['tracking_number']): ?>
                <div class="text-sm text-gray-600 mb-1">TRACKING NUMBER</div>
                <div class="barcode text-5xl"><?php echo htmlspecialchars($delivery['tracking_number']); ?></div>
                <div class="text-lg font-mono mt-2"><?php echo htmlspecialchars($delivery['tracking_number']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-2 gap-6 mb-6">

            <!-- FROM (Supplier) -->
            <div class="border-2 border-black p-4">
                <div class="bg-black text-white px-2 py-1 mb-3 text-center font-bold">FROM (SUPPLIER)</div>
                <div class="space-y-2">
                    <div class="font-bold text-xl"><?php echo htmlspecialchars($delivery['supplier_name']); ?></div>
                    <div class="text-gray-700 text-sm mt-4">
                        <i class="fas fa-box mr-2"></i>
                        Material: <?php echo htmlspecialchars($delivery['material_type']); ?>
                    </div>
                    <div class="text-gray-700 text-sm">
                        <i class="fas fa-weight mr-2"></i>
                        Quantity: <?php echo number_format($delivery['order_quantity']); ?> <?php echo $delivery['unit']; ?>
                    </div>
                </div>
            </div>

            <!-- TO (Destination) -->
            <div class="border-2 border-black p-4">
                <div class="bg-black text-white px-2 py-1 mb-3 text-center font-bold">TO (DESTINATION)</div>
                <div class="space-y-2">
                    <div class="font-bold text-xl">PT. WIRATAMA GLOBALINDO JAYA</div>
                    <div class="text-gray-700 text-sm mt-2">
                        Workshop & Fabrication Site
                    </div>
                    <div class="text-gray-700 text-sm mt-4">
                        <i class="fas fa-project-diagram mr-2"></i>
                        Project: <?php echo htmlspecialchars($delivery['project_name']); ?>
                    </div>
                    <div class="text-gray-700 text-sm">
                        <i class="fas fa-file-alt mr-2"></i>
                        PON: <?php echo htmlspecialchars($delivery['pon_number']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carrier Information -->
        <div class="border-2 border-black p-4 mb-6">
            <div class="bg-gray-200 px-2 py-1 mb-3 font-bold">CARRIER INFORMATION</div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <div class="text-sm text-gray-600">Carrier</div>
                    <div class="font-bold"><?php echo htmlspecialchars($delivery['carrier_name']); ?></div>
                </div>
                <div>
                    <div class="text-sm text-gray-600">Driver</div>
                    <div class="font-bold"><?php echo htmlspecialchars($delivery['driver_name'] ?: '-'); ?></div>
                </div>
                <div>
                    <div class="text-sm text-gray-600">Vehicle</div>
                    <div class="font-bold"><?php echo htmlspecialchars($delivery['vehicle_number'] ?: '-'); ?></div>
                </div>
            </div>
        </div>

        <!-- Schedule -->
        <div class="border-2 border-black p-4 mb-6">
            <div class="bg-gray-200 px-2 py-1 mb-3 font-bold">SCHEDULE</div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <div class="text-sm text-gray-600">Delivery Date</div>
                    <div class="font-bold text-lg"><?php echo date('d F Y', strtotime($delivery['delivery_date'])); ?></div>
                </div>
                <?php if ($delivery['estimated_arrival']): ?>
                    <div>
                        <div class="text-sm text-gray-600">Estimated Arrival</div>
                        <div class="font-bold"><?php echo date('d M Y H:i', strtotime($delivery['estimated_arrival'])); ?></div>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="text-sm text-gray-600">Status</div>
                    <div class="font-bold text-lg text-blue-600"><?php echo $delivery['status']; ?></div>
                </div>
            </div>
        </div>

        <!-- Special Instructions -->
        <?php if ($delivery['notes']): ?>
            <div class="border-2 border-black p-4 mb-6">
                <div class="bg-yellow-200 px-2 py-1 mb-3 font-bold">
                    <i class="fas fa-exclamation-triangle mr-2"></i>SPECIAL INSTRUCTIONS / NOTES
                </div>
                <div class="text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($delivery['notes']); ?></div>
            </div>
        <?php endif; ?>

        <!-- Signature Section -->
        <div class="grid grid-cols-2 gap-6 mt-8">
            <div class="border-2 border-black p-4">
                <div class="text-center mb-16">
                    <div class="font-bold mb-2">SENDER SIGNATURE</div>
                    <div class="text-sm text-gray-600">Supplier / Carrier Representative</div>
                </div>
                <div class="border-t-2 border-black pt-2">
                    <div class="text-center">
                        <div>Name: _________________________</div>
                        <div class="mt-2">Date: _________________________</div>
                    </div>
                </div>
            </div>

            <div class="border-2 border-black p-4">
                <div class="text-center mb-16">
                    <div class="font-bold mb-2">RECEIVER SIGNATURE</div>
                    <div class="text-sm text-gray-600">Logistik / Warehouse Staff</div>
                </div>
                <div class="border-t-2 border-black pt-2">
                    <div class="text-center">
                        <div>Name: _________________________</div>
                        <div class="mt-2">Date: _________________________</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 pt-4 border-t-2 border-gray-300 text-center text-xs text-gray-500">
            <p>This is a computer-generated shipping label. Please verify all information before dispatch.</p>
            <p class="mt-1">Generated on <?php echo date('d F Y H:i:s'); ?> | System User: <?php echo $_SESSION['full_name']; ?></p>
        </div>

    </div>

    <!-- Print Instructions -->
    <div class="no-print mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-bold text-blue-800 mb-2">
            <i class="fas fa-info-circle mr-2"></i>Print Instructions:
        </h3>
        <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
            <li>Recommended paper size: A4 (210mm x 297mm)</li>
            <li>Print in portrait orientation</li>
            <li>Use high-quality printer for barcode clarity</li>
            <li>Attach this label securely to the shipment package</li>
        </ul>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() { window.print(); };
    </script>

</body>

</html>