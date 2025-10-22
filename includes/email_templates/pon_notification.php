<?php

/**
 * PON Notification Email Template
 * Professional HTML email untuk PON created/updated notification
 * 
 * Usage:
 * $html = generate_pon_email($pon_data, $type);
 */

/**
 * Generate PON email HTML
 * 
 * @param array $pon_data PON data from database
 * @param string $type 'created' atau 'updated'
 * @param array $timeline_changes Array of changed timeline fields (untuk 'updated')
 * @return string HTML email
 */
function generate_pon_email($pon_data, $type = 'created', $timeline_changes = [])
{

    // Email title berdasarkan type
    $email_title = ($type === 'created')
        ? '📋 New PON Created'
        : '📝 PON Timeline Updated';

    // PON detail URL
    $pon_url = BASE_URL . "modules/pon/detail.php?id=" . $pon_data['pon_id'];

    // Start HTML
    ob_start();
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $email_title; ?></title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }

            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .email-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
                padding: 30px 20px;
                text-align: center;
            }

            .email-header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }

            .email-header p {
                margin: 10px 0 0 0;
                font-size: 14px;
                opacity: 0.9;
            }

            .email-body {
                padding: 30px 20px;
            }

            .section-title {
                font-size: 18px;
                font-weight: 600;
                color: #333;
                margin: 20px 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #667eea;
            }

            .info-row {
                display: flex;
                margin-bottom: 12px;
            }

            .info-label {
                flex: 0 0 150px;
                font-weight: 600;
                color: #666;
            }

            .info-value {
                flex: 1;
                color: #333;
            }

            .timeline-card {
                background-color: #f8f9fa;
                border-left: 4px solid;
                padding: 15px;
                margin-bottom: 15px;
                border-radius: 4px;
            }

            .timeline-card.engineering {
                border-left-color: #3b82f6;
            }

            .timeline-card.purchasing {
                border-left-color: #10b981;
            }

            .timeline-card.fabrikasi {
                border-left-color: #f97316;
            }

            .timeline-card.logistik {
                border-left-color: #8b5cf6;
            }

            .timeline-card h3 {
                margin: 0 0 10px 0;
                font-size: 16px;
                font-weight: 600;
            }

            .timeline-info {
                font-size: 14px;
                line-height: 1.6;
                color: #555;
            }

            .badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                margin-left: 10px;
            }

            .badge-urgent {
                background-color: #f97316;
                color: #ffffff;
            }

            .badge-overdue {
                background-color: #ef4444;
                color: #ffffff;
            }

            .badge-ok {
                background-color: #10b981;
                color: #ffffff;
            }

            .badge-tba {
                background-color: #6b7280;
                color: #ffffff;
            }

            .cta-button {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
                padding: 14px 30px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                margin-top: 20px;
                text-align: center;
            }

            .cta-button:hover {
                opacity: 0.9;
            }

            .email-footer {
                background-color: #f8f9fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
                border-top: 1px solid #e5e7eb;
            }

            .change-highlight {
                background-color: #fef3c7;
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: 600;
            }

            .alert-box {
                background-color: #fef2f2;
                border-left: 4px solid #ef4444;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }

            .alert-box p {
                margin: 0;
                color: #991b1b;
                font-weight: 600;
            }
        </style>
    </head>

    <body>
        <div class="email-container">

            <!-- Header -->
            <div class="email-header">
                <h1><?php echo $email_title; ?></h1>
                <p>PT. Wiratama Globalindo Jaya - Project Management System</p>
            </div>

            <!-- Body -->
            <div class="email-body">

                <!-- Alert for Updates -->
                <?php if ($type === 'updated' && !empty($timeline_changes)): ?>
                    <div class="alert-box">
                        <p>⚠️ Timeline has been updated. Please review the changes below.</p>
                    </div>
                <?php endif; ?>

                <!-- PON Information -->
                <div class="section-title">📋 PON Information</div>

                <div class="info-row">
                    <div class="info-label">PON Number:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($pon_data['pon_number']); ?></strong></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Subject:</div>
                    <div class="info-value"><?php echo htmlspecialchars($pon_data['subject']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Project Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($pon_data['project_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Client:</div>
                    <div class="info-value"><?php echo htmlspecialchars($pon_data['client_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Project Start:</div>
                    <div class="info-value"><?php echo date('d M Y', strtotime($pon_data['project_start_date'])); ?></div>
                </div>

                <!-- Division Timeline -->
                <div class="section-title">⏱️ Division Timeline & Deadlines</div>

                <?php
                // Function helper untuk format tanggal
                function format_email_date($date)
                {
                    return !empty($date) ? date('d M Y', strtotime($date)) : '<span style="color: #999;">TBA</span>';
                }

                // Function untuk calculate deadline status
                function get_deadline_badge($finish_date)
                {
                    if (empty($finish_date)) {
                        return '<span class="badge badge-tba">TBA</span>';
                    }

                    $days = (strtotime($finish_date) - strtotime(date('Y-m-d'))) / 86400;

                    if ($days < 0) {
                        return '<span class="badge badge-overdue">OVERDUE</span>';
                    } elseif ($days <= 7) {
                        return '<span class="badge badge-urgent">URGENT</span>';
                    } else {
                        return '<span class="badge badge-ok">ON TRACK</span>';
                    }
                }

                // Check if field changed
                function is_field_changed($field_name, $changes)
                {
                    return in_array($field_name, $changes);
                }

                // Divisions
                $divisions = [
                    [
                        'name' => 'Engineering',
                        'class' => 'engineering',
                        'icon' => '🔵',
                        'start' => 'engineering_start_date',
                        'finish' => 'engineering_finish_date',
                        'pic' => 'engineering_pic'
                    ],
                    [
                        'name' => 'Purchasing',
                        'class' => 'purchasing',
                        'icon' => '🟢',
                        'start' => 'purchasing_start_date',
                        'finish' => 'purchasing_finish_date',
                        'pic' => 'purchasing_pic'
                    ],
                    [
                        'name' => 'Fabrikasi',
                        'class' => 'fabrikasi',
                        'icon' => '🟠',
                        'start' => 'fabrikasi_start_date',
                        'finish' => 'fabrikasi_finish_date',
                        'pic' => 'fabrikasi_pic'
                    ],
                    [
                        'name' => 'Logistik',
                        'class' => 'logistik',
                        'icon' => '🟣',
                        'start' => 'logistik_start_date',
                        'finish' => 'logistik_finish_date',
                        'pic' => 'logistik_pic'
                    ]
                ];

                foreach ($divisions as $div):
                    $start_changed = is_field_changed($div['start'], $timeline_changes);
                    $finish_changed = is_field_changed($div['finish'], $timeline_changes);
                    $pic_changed = is_field_changed($div['pic'], $timeline_changes);
                ?>
                    <div class="timeline-card <?php echo $div['class']; ?>">
                        <h3>
                            <?php echo $div['icon']; ?>
                            <?php echo $div['name']; ?> Division
                            <?php echo get_deadline_badge($pon_data[$div['finish']]); ?>
                        </h3>
                        <div class="timeline-info">
                            <p>
                                📅 <strong>Start Date:</strong>
                                <?php if ($start_changed): ?><span class="change-highlight"><?php endif; ?>
                                    <?php echo format_email_date($pon_data[$div['start']]); ?>
                                    <?php if ($start_changed): ?></span><?php endif; ?>
                            </p>
                            <p>
                                🎯 <strong>Deadline:</strong>
                                <?php if ($finish_changed): ?><span class="change-highlight"><?php endif; ?>
                                    <?php echo format_email_date($pon_data[$div['finish']]); ?>
                                    <?php if ($finish_changed): ?></span><?php endif; ?>
                                <?php
                                // Show days remaining/overdue
                                if (!empty($pon_data[$div['finish']])) {
                                    $days = (strtotime($pon_data[$div['finish']]) - strtotime(date('Y-m-d'))) / 86400;
                                    if ($days >= 0) {
                                        echo ' <span style="color: #666; font-size: 12px;">(' . floor($days) . ' days left)</span>';
                                    } else {
                                        echo ' <span style="color: #ef4444; font-size: 12px; font-weight: 600;">(' . abs(floor($days)) . ' days overdue!)</span>';
                                    }
                                }
                                ?>
                            </p>
                            <p>
                                👤 <strong>PIC:</strong>
                                <?php if ($pic_changed): ?><span class="change-highlight"><?php endif; ?>
                                    <?php echo htmlspecialchars($pon_data[$div['pic']] ?? 'N/A'); ?>
                                    <?php if ($pic_changed): ?></span><?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Call to Action -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo $pon_url; ?>" class="cta-button">
                        🔗 View PON Details
                    </a>
                </div>

                <!-- Important Notes -->
                <?php if ($type === 'created'): ?>
                    <div style="margin-top: 30px; padding: 15px; background-color: #eff6ff; border-radius: 6px; border-left: 4px solid #3b82f6;">
                        <p style="margin: 0; color: #1e40af; font-size: 14px;">
                            ℹ️ <strong>Note:</strong> This PON has been created and assigned to your division.
                            Please review the timeline and contact Admin if you need any adjustments.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 30px; padding: 15px; background-color: #fef3c7; border-radius: 6px; border-left: 4px solid #f59e0b;">
                        <p style="margin: 0; color: #92400e; font-size: 14px;">
                            ⚠️ <strong>Note:</strong> Timeline has been updated by Admin.
                            Highlighted fields indicate changes. Please review and plan accordingly.
                        </p>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p style="margin: 0 0 10px 0; font-weight: 600;">PT. Wiratama Globalindo Jaya</p>
                <p style="margin: 0 0 5px 0;">Project Management System</p>
                <p style="margin: 0; color: #999;">
                    This is an automated notification. Please do not reply to this email.
                </p>
                <p style="margin: 10px 0 0 0; color: #999;">
                    © <?php echo date('Y'); ?> PT. Wiratama Globalindo Jaya. All rights reserved.
                </p>
            </div>

        </div>
    </body>

    </html>
<?php
    return ob_get_clean();
}

/**
 * Generate simple text version (fallback)
 */
function generate_pon_email_text($pon_data, $type = 'created')
{
    $text = "";
    $text .= "========================================\n";
    $text .= $type === 'created' ? "NEW PON CREATED\n" : "PON TIMELINE UPDATED\n";
    $text .= "========================================\n\n";

    $text .= "PON Number: " . $pon_data['pon_number'] . "\n";
    $text .= "Subject: " . $pon_data['subject'] . "\n";
    $text .= "Project: " . $pon_data['project_name'] . "\n";
    $text .= "Client: " . $pon_data['client_name'] . "\n\n";

    $text .= "DIVISION TIMELINE:\n";
    $text .= "----------------------------------------\n";

    $divisions = [
        ['name' => 'Engineering', 'start' => 'engineering_start_date', 'finish' => 'engineering_finish_date', 'pic' => 'engineering_pic'],
        ['name' => 'Purchasing', 'start' => 'purchasing_start_date', 'finish' => 'purchasing_finish_date', 'pic' => 'purchasing_pic'],
        ['name' => 'Fabrikasi', 'start' => 'fabrikasi_start_date', 'finish' => 'fabrikasi_finish_date', 'pic' => 'fabrikasi_pic'],
        ['name' => 'Logistik', 'start' => 'logistik_start_date', 'finish' => 'logistik_finish_date', 'pic' => 'logistik_pic']
    ];

    foreach ($divisions as $div) {
        $text .= "\n" . $div['name'] . ":\n";
        $text .= "  Start: " . ($pon_data[$div['start']] ?? 'TBA') . "\n";
        $text .= "  Deadline: " . ($pon_data[$div['finish']] ?? 'TBA') . "\n";
        $text .= "  PIC: " . ($pon_data[$div['pic']] ?? 'N/A') . "\n";
    }

    $text .= "\n----------------------------------------\n";
    $text .= "View details: " . BASE_URL . "modules/pon/detail.php?id=" . $pon_data['pon_id'] . "\n\n";
    $text .= "PT. Wiratama Globalindo Jaya\n";
    $text .= "Project Management System\n";

    return $text;
}

?>