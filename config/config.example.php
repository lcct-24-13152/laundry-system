<?php
/*
 * CML Laundry — settings
 * ----------------------------------------------------------
 * 1. Copy this file and name the copy  config.php  (same folder).
 * 2. Put your real values in config.php.
 * config.php is ignored by Git, so your passwords never go to GitHub.
 * If config.php doesn't exist, the system uses the values below
 * (default Laragon/XAMPP database, email and SMS turned off).
 */

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'laundry_db');

// Website address used in QR codes and password-reset links.
// Leave empty to detect it automatically (works on localhost and on hosting).
// Example for ngrok or hosting: 'https://your-site.infinityfreeapp.com'
define('APP_URL', '');

// Gmail used to send password-reset and stock-alert emails.
// Use a Gmail App Password, not your normal Gmail password.
define('MAIL_USER', '');
define('MAIL_PASS', '');
define('MAIL_FROM_NAME', 'CML Laundry Management System');
define('ALERT_EMAIL', '');   // where low/critical stock alerts are sent

// Semaphore SMS API key (https://semaphore.co). Leave empty to turn SMS off.
define('SEMAPHORE_API_KEY', '');
