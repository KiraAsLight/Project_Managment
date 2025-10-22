<?php

/**
 * Email Mailer Class - FIXED VERSION
 * Menggunakan PHPMailer dengan SMTP (Guaranteed to work!)
 */

require_once __DIR__ . '/../config/email_config.php';

// Load PHPMailer
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================================
// EMAIL MAILER CLASS
// ============================================================

class EmailMailer
{
    private $conn;
    private $errors = [];

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;

        // Validate config
        if (!validate_email_config()) {
            $this->errors[] = "Email configuration invalid. Check email_config.php";
        }
    }

    /**
     * Send email menggunakan PHPMailer + SMTP
     * 
     * @param array $to_emails Array of recipient emails
     * @param string $subject Email subject
     * @param string $html_body HTML email body
     * @param array $options Optional settings (cc, bcc, attachments)
     * @return bool Success status
     */
    public function send($to_emails, $subject, $html_body, $options = [])
    {
        // Check if email enabled
        if (!EMAIL_ENABLED) {
            error_log("Email skipped: EMAIL_ENABLED = false");
            return true;
        }

        // Debug mode
        if (EMAIL_DEBUG_MODE) {
            $this->debug_email($to_emails, $subject, $html_body);
            return true;
        }

        // Validate recipients
        if (empty($to_emails)) {
            $this->errors[] = "No recipients specified";
            return false;
        }

        // Ensure array
        if (!is_array($to_emails)) {
            $to_emails = [$to_emails];
        }

        // Filter valid emails
        $to_emails = array_filter($to_emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (empty($to_emails)) {
            $this->errors[] = "No valid email addresses";
            return false;
        }

        // Send using PHPMailer
        return $this->send_with_phpmailer($to_emails, $subject, $html_body, $options);
    }

    /**
     * Send email menggunakan PHPMailer + SMTP
     * This is the ONLY method we use now!
     */
    private function send_with_phpmailer($to_emails, $subject, $html_body, $options)
    {
        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            // Debug output (optional, disable for production)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

            // Charset
            $mail->CharSet = 'UTF-8';

            // Sender
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

            // Recipients
            foreach ($to_emails as $email) {
                $mail->addAddress($email);
            }

            // Reply-To
            if (defined('SMTP_REPLY_TO') && SMTP_REPLY_TO) {
                $mail->addReplyTo(SMTP_REPLY_TO);
            }

            // CC (optional)
            if (!empty($options['cc'])) {
                $cc_emails = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
                foreach ($cc_emails as $cc_email) {
                    if (filter_var($cc_email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cc_email);
                    }
                }
            }

            // BCC (optional)
            if (!empty($options['bcc'])) {
                $bcc_emails = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
                foreach ($bcc_emails as $bcc_email) {
                    if (filter_var($bcc_email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($bcc_email);
                    }
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_body;
            $mail->AltBody = strip_tags($html_body); // Plain text version

            // Send
            $mail->send();

            // Log success
            $this->log_email_sent($to_emails, $subject, true);
            error_log("Email SUCCESS: Sent to " . implode(', ', $to_emails));

            return true;
        } catch (Exception $e) {
            $error_msg = "PHPMailer Error: {$mail->ErrorInfo}";
            $this->errors[] = $error_msg;
            $this->log_email_sent($to_emails, $subject, false, $error_msg);
            error_log("Email FAILED: " . $error_msg);
            return false;
        }
    }

    /**
     * Debug mode - print email info tanpa kirim
     */
    private function debug_email($to_emails, $subject, $html_body)
    {
        if (!is_array($to_emails)) {
            $to_emails = [$to_emails];
        }

        echo "<div style='background: #f0f0f0; padding: 20px; margin: 10px; border: 2px solid #333;'>";
        echo "<h3 style='color: #d9534f;'>📧 EMAIL DEBUG MODE</h3>";
        echo "<p><strong>From:</strong> " . SMTP_FROM_NAME . " &lt;" . SMTP_FROM_EMAIL . "&gt;</p>";
        echo "<p><strong>To:</strong> " . implode(', ', $to_emails) . "</p>";
        echo "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
        echo "<hr>";
        echo "<h4>Email Body Preview:</h4>";
        echo "<div style='background: white; padding: 15px; border: 1px solid #ddd;'>";
        echo $html_body;
        echo "</div>";
        echo "<hr>";
        echo "<p style='color: green;'><strong>✅ Email would be sent successfully</strong></p>";
        echo "<p><em>To actually send emails, set EMAIL_DEBUG_MODE = false in config/email_config.php</em></p>";
        echo "</div>";

        error_log("EMAIL DEBUG: To=" . implode(',', $to_emails) . " Subject=$subject");
    }

    /**
     * Log email activity
     */
    private function log_email_sent($recipients, $subject, $success, $error_message = null)
    {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $log_message = "Email $status: To=" . implode(',', $recipients) . " Subject='$subject'";

        if ($error_message) {
            $log_message .= " Error: $error_message";
        }

        error_log($log_message);
    }

    /**
     * Get error messages
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * Check if has errors
     */
    public function has_errors()
    {
        return !empty($this->errors);
    }
}

// ============================================================
// HELPER FUNCTION
// ============================================================

/**
 * Quick send email function (wrapper)
 */
function send_email($conn, $to_emails, $subject, $html_body)
{
    $mailer = new EmailMailer($conn);
    return $mailer->send($to_emails, $subject, $html_body);
}
?>