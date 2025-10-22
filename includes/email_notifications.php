<?php

/**
 * Email Notification Functions
 * Functions untuk trigger email notifications pada PON events
 */

require_once __DIR__ . '/email_mailer.php';
require_once __DIR__ . '/email_templates/pon_notification.php';
require_once __DIR__ . '/../config/email_config.php';

// ============================================================
// MAIN NOTIFICATION FUNCTIONS
// ============================================================

/**
 * Send PON Created Notification
 * Kirim email ke Admin & semua Divisi ketika PON baru dibuat
 * 
 * @param object $conn Database connection
 * @param int $pon_id PON ID yang baru dibuat
 * @return bool Success status
 */
function send_pon_created_notification($conn, $pon_id)
{
    try {
        // Get PON data
        $pon_data = get_pon_data($conn, $pon_id);

        if (!$pon_data) {
            error_log("Email Error: PON $pon_id not found");
            return false;
        }

        // Get recipients
        $recipients = get_all_division_emails($conn);

        if (empty($recipients)) {
            error_log("Email Warning: No recipients found for PON notification");
            // Kirim ke default admin email
            $recipients = [DEFAULT_ADMIN_EMAIL];
        }

        // Generate email content
        $subject = "📋 New PON Created: " . $pon_data['pon_number'] . " - " . $pon_data['subject'];
        $html_body = generate_pon_email($pon_data, 'created');

        // Send email
        $mailer = new EmailMailer($conn);
        $result = $mailer->send($recipients, $subject, $html_body);

        if ($result) {
            error_log("Email SUCCESS: PON Created notification sent for PON $pon_id to " . count($recipients) . " recipients");
        } else {
            error_log("Email FAILED: PON Created notification for PON $pon_id. Errors: " . implode(', ', $mailer->get_errors()));
        }

        return $result;
    } catch (Exception $e) {
        error_log("Email Exception: send_pon_created_notification - " . $e->getMessage());
        return false;
    }
}

/**
 * Send PON Timeline Updated Notification
 * Kirim email ke Admin & semua Divisi ketika PON timeline diupdate
 * 
 * @param object $conn Database connection
 * @param int $pon_id PON ID yang diupdate
 * @param array $old_data Data PON sebelum update (optional)
 * @return bool Success status
 */
function send_pon_updated_notification($conn, $pon_id, $old_data = [])
{
    try {
        // Get updated PON data
        $new_data = get_pon_data($conn, $pon_id);

        if (!$new_data) {
            error_log("Email Error: PON $pon_id not found");
            return false;
        }

        // Detect timeline changes
        $timeline_changes = [];
        if (!empty($old_data)) {
            $timeline_changes = detect_timeline_changes($old_data, $new_data);
        }

        // Jika tidak ada perubahan timeline, skip email
        if (empty($timeline_changes) && !empty($old_data)) {
            error_log("Email Skipped: No timeline changes detected for PON $pon_id");
            return true; // Return true karena ini bukan error
        }

        // Get recipients
        $recipients = get_all_division_emails($conn);

        if (empty($recipients)) {
            error_log("Email Warning: No recipients found for PON notification");
            $recipients = [DEFAULT_ADMIN_EMAIL];
        }

        // Generate email content
        $subject = "📝 PON Timeline Updated: " . $new_data['pon_number'] . " - " . $new_data['subject'];
        $html_body = generate_pon_email($new_data, 'updated', $timeline_changes);

        // Send email
        $mailer = new EmailMailer($conn);
        $result = $mailer->send($recipients, $subject, $html_body);

        if ($result) {
            error_log("Email SUCCESS: PON Updated notification sent for PON $pon_id to " . count($recipients) . " recipients");
        } else {
            error_log("Email FAILED: PON Updated notification for PON $pon_id. Errors: " . implode(', ', $mailer->get_errors()));
        }

        return $result;
    } catch (Exception $e) {
        error_log("Email Exception: send_pon_updated_notification - " . $e->getMessage());
        return false;
    }
}

/**
 * Send PON notification to specific division only
 * 
 * @param object $conn Database connection
 * @param int $pon_id PON ID
 * @param string $division Division name (Engineering, Purchasing, etc)
 * @param string $message Custom message
 * @return bool Success status
 */
function send_division_notification($conn, $pon_id, $division, $message)
{
    try {
        $pon_data = get_pon_data($conn, $pon_id);

        if (!$pon_data) {
            return false;
        }

        // Get division emails only
        $recipients = get_division_emails($conn, $division);

        if (empty($recipients)) {
            error_log("Email Warning: No email found for division: $division");
            return false;
        }

        // Simple subject
        $subject = "⚠️ PON Alert: " . $pon_data['pon_number'] . " - $division";

        // Simple HTML
        $html_body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;'>
                <h2 style='color: #333;'>$subject</h2>
                <p style='color: #666; line-height: 1.6;'>$message</p>
                <p style='margin-top: 20px;'>
                    <a href='" . BASE_URL . "modules/pon/detail.php?id=$pon_id' 
                       style='display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>
                        View PON Details
                    </a>
                </p>
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                <p style='color: #999; font-size: 12px; text-align: center;'>
                    PT. Wiratama Globalindo Jaya - Project Management System
                </p>
            </div>
        </div>
        ";

        // Send
        $mailer = new EmailMailer($conn);
        return $mailer->send($recipients, $subject, $html_body);
    } catch (Exception $e) {
        error_log("Email Exception: send_division_notification - " . $e->getMessage());
        return false;
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Get PON data from database
 * 
 * @param object $conn Database connection
 * @param int $pon_id PON ID
 * @return array|null PON data or null if not found
 */
function get_pon_data($conn, $pon_id)
{
    $query = "SELECT * FROM pon WHERE pon_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pon_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

/**
 * Detect timeline changes between old and new data
 * 
 * @param array $old_data Old PON data
 * @param array $new_data New PON data
 * @return array Array of changed field names
 */
function detect_timeline_changes($old_data, $new_data)
{
    $timeline_fields = [
        'engineering_start_date',
        'engineering_finish_date',
        'engineering_pic',
        'purchasing_start_date',
        'purchasing_finish_date',
        'purchasing_pic',
        'fabrikasi_start_date',
        'fabrikasi_finish_date',
        'fabrikasi_pic',
        'logistik_start_date',
        'logistik_finish_date',
        'logistik_pic'
    ];

    $changes = [];

    foreach ($timeline_fields as $field) {
        $old_value = $old_data[$field] ?? null;
        $new_value = $new_data[$field] ?? null;

        // Compare values
        if ($old_value !== $new_value) {
            $changes[] = $field;
        }
    }

    return $changes;
}

/**
 * Validate email recipients
 * 
 * @param array $emails Array of email addresses
 * @return array Valid email addresses
 */
function validate_email_recipients($emails)
{
    return array_filter($emails, function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
}

/**
 * Log email notification attempt
 * 
 * @param object $conn Database connection
 * @param int $pon_id PON ID
 * @param string $type Notification type (created, updated)
 * @param bool $success Success status
 * @param string $error_message Error message if failed
 */
function log_email_notification($conn, $pon_id, $type, $success, $error_message = null)
{
    // Optional: Create email_notifications table
    // Untuk tracking email yang dikirim

    $status = $success ? 'sent' : 'failed';

    error_log("Email Notification Log: PON=$pon_id Type=$type Status=$status" .
        ($error_message ? " Error=$error_message" : ""));

    // TODO: Insert to database jika perlu tracking
    /*
    $query = "INSERT INTO email_notifications 
              (pon_id, notification_type, status, error_message, sent_at) 
              VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $pon_id, $type, $status, $error_message);
    $stmt->execute();
    */
}

// ============================================================
// SCHEDULED NOTIFICATIONS (untuk Phase 5 - Reminder System)
// ============================================================

/**
 * Send deadline reminder emails
 * Untuk dijalankan via cron job daily
 * 
 * @param object $conn Database connection
 * @param int $days_before Days before deadline to send reminder (default: 7)
 * @return int Number of reminders sent
 */
function send_deadline_reminders($conn, $days_before = 7)
{
    // TODO: Implement untuk Phase 5
    // Logic:
    // 1. Find PON dengan deadline = TODAY + $days_before
    // 2. Loop each PON
    // 3. Send reminder email ke division yang punya deadline

    error_log("Deadline Reminder: Feature coming in Phase 5");
    return 0;
}

/**
 * Send overdue alerts
 * Untuk PON yang sudah melewati deadline
 * 
 * @param object $conn Database connection
 * @return int Number of alerts sent
 */
function send_overdue_alerts($conn)
{
    // TODO: Implement untuk Phase 5
    // Logic:
    // 1. Find PON dengan finish_date < TODAY dan status != Completed
    // 2. Send urgent email ke Admin & division terkait

    error_log("Overdue Alert: Feature coming in Phase 5");
    return 0;
}
?>