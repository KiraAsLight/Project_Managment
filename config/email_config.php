<?php

/**
 * Email Configuration
 * SMTP settings untuk pengiriman email notifikasi
 */

// ============================================================
// SMTP CONFIGURATION
// ============================================================

// Gmail SMTP (Recommended untuk testing)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // TLS port
define('SMTP_SECURE', 'tls'); // 'tls' atau 'ssl'
define('SMTP_AUTH', true);

// SMTP Credentials
// IMPORTANT: Gunakan App Password jika pakai Gmail dengan 2FA
// Cara buat App Password: https://support.google.com/accounts/answer/185833
define('SMTP_USERNAME', 'aufamunadil9@gmail.com'); // Ganti dengan email Anda
define('SMTP_PASSWORD', 'igvjttpcogmcapvb'); // Ganti dengan App Password

// Sender Info
define('SMTP_FROM_EMAIL', 'noreply@wiratama.com'); // Email pengirim
define('SMTP_FROM_NAME', 'PT. Wiratama Globalindo Jaya - PM System');

// Reply-To Email (optional)
define('SMTP_REPLY_TO', 'admin@wiratama.com');

// ============================================================
// ALTERNATIVE: SMTP Lokal (Untuk Production)
// ============================================================
/*
define('SMTP_HOST', 'mail.yourcompany.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'aufamunadil9@gmail.com');
define('SMTP_PASSWORD', 'igvj ttpc ogmc apvb');
define('SMTP_FROM_EMAIL', 'noreply@yourcompany.com');
define('SMTP_FROM_NAME', 'Project Management System');
*/

// ============================================================
// EMAIL SETTINGS
// ============================================================

// Enable/Disable Email Notification (untuk testing)
define('EMAIL_ENABLED', true); // Set false untuk disable semua email

// Debug Mode (akan print email info tanpa kirim)
define('EMAIL_DEBUG_MODE', false); // Set true untuk testing

// Default Admin Email (jika user email kosong)
define('DEFAULT_ADMIN_EMAIL', 'admin@wiratama.com');

// Email Template Path
define('EMAIL_TEMPLATE_PATH', __DIR__ . '/../includes/email_templates/');

// ============================================================
// DIVISION EMAIL MAPPING
// ============================================================

/**
 * Mapping divisi ke email addresses
 * Bisa diambil dari database atau hardcode
 */
function get_division_emails($conn, $division)
{
    // Query untuk ambil email users berdasarkan division/role
    $query = "SELECT email FROM users 
              WHERE role = ? 
              AND is_active = 1 
              AND email IS NOT NULL 
              AND email != ''";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $division);
    $stmt->execute();
    $result = $stmt->get_result();

    $emails = [];
    while ($row = $result->fetch_assoc()) {
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $emails[] = $row['email'];
        }
    }

    return $emails;
}

/**
 * Get Admin emails
 */
function get_admin_emails($conn)
{
    return get_division_emails($conn, 'Admin');
}

/**
 * Get ALL division emails (untuk broadcast)
 */
function get_all_division_emails($conn)
{
    $divisions = ['Admin', 'Engineering', 'Purchasing', 'Fabrikasi', 'Logistik'];
    $all_emails = [];

    foreach ($divisions as $division) {
        $emails = get_division_emails($conn, $division);
        $all_emails = array_merge($all_emails, $emails);
    }

    // Remove duplicates
    return array_unique($all_emails);
}

// ============================================================
// VALIDATION
// ============================================================

/**
 * Validate email configuration
 */
function validate_email_config()
{
    $required = [
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_FROM_EMAIL'
    ];

    $missing = [];
    foreach ($required as $const) {
        if (!defined($const) || empty(constant($const))) {
            $missing[] = $const;
        }
    }

    if (!empty($missing)) {
        error_log("Email Config Error: Missing constants: " . implode(', ', $missing));
        return false;
    }

    // Validate email format
    if (!filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        error_log("Email Config Error: Invalid SMTP_FROM_EMAIL format");
        return false;
    }

    return true;
}

// ============================================================
// NOTES & INSTRUCTIONS
// ============================================================

/*
SETUP INSTRUCTIONS:

1. GMAIL SETUP (Recommended untuk testing):
   - Enable 2-Factor Authentication di Google Account
   - Generate App Password: https://myaccount.google.com/apppasswords
   - Copy App Password ke SMTP_PASSWORD
   - Ganti SMTP_USERNAME dengan email Gmail Anda

2. PRODUCTION SETUP:
   - Gunakan SMTP server company
   - Update SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD
   - Test dengan EMAIL_DEBUG_MODE = true dulu

3. TROUBLESHOOTING:
   - Jika gagal kirim, check:
     * SMTP credentials benar
     * Port tidak diblock firewall
     * Less Secure Apps enabled (Gmail legacy)
     * App Password generated (Gmail with 2FA)

4. DATABASE REQUIREMENT:
   - Pastikan kolom 'email' ada di tabel 'users'
   - Format email valid (test@example.com)
   - Email tidak boleh NULL untuk terima notifikasi

5. TESTING:
   - Set EMAIL_DEBUG_MODE = true
   - Check error_log untuk debug info
   - Test kirim email ke email sendiri dulu
*/
