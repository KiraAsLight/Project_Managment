<?php

/**
 * Export PON List to Excel
 * Simple export menggunakan HTML table yang bisa dibuka di Excel
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_role(['Admin']);

$conn = getDBConnection();

// Get all PON data
$query = "SELECT 
            p.*,
            (SELECT AVG(progress) FROM tasks WHERE pon_id = p.pon_id) as overall_progress,
            (SELECT COUNT(*) FROM tasks WHERE pon_id = p.pon_id) as total_tasks,
            (SELECT SUM(weight_value) FROM tasks WHERE pon_id = p.pon_id) as total_weight_calculated
          FROM pon p
          ORDER BY p.created_at DESC";

$result = $conn->query($query);

// Set headers untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="PON_List_' . date('Y-m-d_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>PON List Export</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }

        .number {
            mso-number-format: "0.00";
        }

        .date {
            mso-number-format: "dd/mm/yyyy";
        }
    </style>
</head>

<body>
    <h2>Project Order Notification (PON) List</h2>
    <p>Exported: <?php echo date('d/m/Y H:i:s'); ?></p>
    <p>PT. Wiratama Globalindo Jaya</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>PON Number</th>
                <th>Offer Number</th>
                <th>Subject</th>
                <th>Project Name</th>
                <th>Client Name</th>
                <th>Contract Number</th>
                <th>QTY/Configuration</th>
                <th>Start Date</th>
                <th>Status</th>
                <th>Progress (%)</th>
                <th>Total Tasks</th>
                <th>Total Weight (Kg)</th>
                <th>Project Manager</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = $result->fetch_assoc()):
                $progress = round($row['overall_progress'] ?? 0, 2);
                $weight = $row['total_weight_calculated'] ?? $row['total_weight'] ?? 0;
            ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['pon_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['offer_number'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['subject']); ?></td>
                    <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['contract_number'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['qty_configuration'] ?? '-'); ?></td>
                    <td class="date"><?php echo $row['project_start_date']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                    <td class="number"><?php echo $progress; ?></td>
                    <td><?php echo $row['total_tasks']; ?></td>
                    <td class="number"><?php echo number_format($weight, 2); ?></td>
                    <td><?php echo htmlspecialchars($row['project_manager'] ?? '-'); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <br><br>
    <p><strong>Note:</strong> Data ini diekspor dari sistem Project Management PT. Wiratama Globalindo Jaya</p>
</body>

</html>
<?php
closeDBConnection($conn);
?>