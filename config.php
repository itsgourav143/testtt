<?php

// Database Configuration
define('DB_HOST', 'YOUR_DATABASE_HOST');         // e.g., 'localhost' or '127.0.0.1'
define('DB_NAME', 'YOUR_DATABASE_NAME');       // The name of your database
define('DB_USER', 'YOUR_DATABASE_USER');         // Your database username
define('DB_PASS', 'YOUR_DATABASE_PASSWORD');     // Your database password

// OneSignal Configuration
define('ONESIGNAL_APP_ID', 'YOUR_ONESIGNAL_APP_ID'); // Your OneSignal App ID
define('ONESIGNAL_REST_API_KEY', 'YOUR_ONESIGNAL_REST_API_KEY'); // Your OneSignal REST API Key

// Google OAuth Configuration (Add if you plan to integrate Google Sign-In server-side)
// define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
// define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// define('GOOGLE_REDIRECT_URI', 'YOUR_GOOGLE_REDIRECT_URI'); // e.g., 'http://localhost/index.php'

/**
 * It's recommended to store sensitive information like API keys and database credentials
 * in environment variables or a configuration file outside of the web root
 * for better security in a production environment.
 *
 * For example, using getenv():
 * define('DB_PASS', getenv('DATABASE_PASSWORD'));
 *
 * Or by including a file from a non-web-accessible directory:
 * require_once '/path/to/secure/config.php';
 */

?>
