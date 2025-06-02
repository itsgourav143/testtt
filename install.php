<?php
// Installation Script for Task Management Application

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_file_path = __DIR__ . '/config.php';
$schema_file_path = __DIR__ . '/schema.sql';

$step = 1;
$messages = ['success' => [], 'errors' => [], 'info' => []];

// --- Helper Functions ---

/**
 * Writes a message to the appropriate message array.
 * @param string $type 'success', 'errors', or 'info'
 * @param string $message The message to store.
 */
function add_message($type, $message) {
    global $messages;
    if (isset($messages[$type])) {
        $messages[$type][] = $message;
    }
}

/**
 * Updates the config.php file with provided credentials.
 */
function update_config_file($db_host, $db_name, $db_user, $db_pass, $config_file_path) {
    if (!is_writable($config_file_path) && file_exists($config_file_path)) {
        if (!chmod($config_file_path, 0666)) { // Try to make it writable
            add_message('errors', "Config file ($config_file_path) is not writable. Please adjust permissions.");
            return false;
        }
    } elseif (!file_exists($config_file_path) && !is_writable(dirname($config_file_path))) {
        add_message('errors', "Config file directory (".dirname($config_file_path).") is not writable. Cannot create config file.");
        return false;
    }


    $config_content = file_get_contents($config_file_path);
    if ($config_content === false) {
        add_message('errors', "Could not read from config file template: $config_file_path");
        return false;
    }

    $replacements = [
        "define('DB_HOST', 'YOUR_DATABASE_HOST');"         => "define('DB_HOST', '" . addslashes($db_host) . "');",
        "define('DB_NAME', 'YOUR_DATABASE_NAME');"       => "define('DB_NAME', '" . addslashes($db_name) . "');",
        "define('DB_USER', 'YOUR_DATABASE_USER');"         => "define('DB_USER', '" . addslashes($db_user) . "');",
        "define('DB_PASS', 'YOUR_DATABASE_PASSWORD');"     => "define('DB_PASS', '" . addslashes($db_pass) . "');",
        // Dummy replacement for OneSignal keys if they are in the template, keep them as placeholders
        "define('ONESIGNAL_APP_ID', 'YOUR_ONESIGNAL_APP_ID');" => "define('ONESIGNAL_APP_ID', 'YOUR_ONESIGNAL_APP_ID');",
        "define('ONESIGNAL_REST_API_KEY', 'YOUR_ONESIGNAL_REST_API_KEY');" => "define('ONESIGNAL_REST_API_KEY', 'YOUR_ONESIGNAL_REST_API_KEY');"
    ];

    $config_content = str_replace(array_keys($replacements), array_values($replacements), $config_content);

    if (file_put_contents($config_file_path, $config_content) === false) {
        add_message('errors', "Could not write to config file: $config_file_path");
        return false;
    }
    add_message('success', "Configuration file ($config_file_path) updated successfully.");
    return true;
}

/**
 * Executes SQL schema.
 */
function setup_database($db_host, $db_name, $db_user, $db_pass, $schema_file_path) {
    // 1. Connect to MySQL server (without selecting DB initially)
    $mysqli = @new mysqli($db_host, $db_user, $db_pass);
    if ($mysqli->connect_error) {
        add_message('errors', "MySQL Connection Failed: " . $mysqli->connect_error);
        return false;
    }
    add_message('success', "Successfully connected to MySQL server.");

    // 2. Create Database if it doesn't exist
    if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($db_name) . "`")) {
        add_message('errors', "Error creating database '$db_name': " . $mysqli->error);
        $mysqli->close();
        return false;
    }
    add_message('success', "Database '$db_name' ensured to exist.");

    // 3. Select the database
    if (!$mysqli->select_db($db_name)) {
        add_message('errors', "Error selecting database '$db_name': " . $mysqli->error);
        $mysqli->close();
        return false;
    }
    add_message('success', "Database '$db_name' selected.");

    // 4. Read and execute schema.sql
    $sql_commands = file_get_contents($schema_file_path);
    if ($sql_commands === false) {
        add_message('errors', "Could not read schema file: $schema_file_path");
        $mysqli->close();
        return false;
    }

    // Split SQL commands - basic split, might not handle complex cases like procedures with semicolons
    $commands = preg_split('/;\s*$/m', $sql_commands);
    $commands = array_filter($commands, 'trim'); // Remove empty commands

    foreach ($commands as $command) {
        if (!empty(trim($command))) {
            if (!$mysqli->query($command)) {
                add_message('errors', "SQL Error executing command: " . htmlspecialchars($command) . "<br>Error: " . $mysqli->error);
                // Continue trying other commands if one fails
            } else {
                add_message('success', "Successfully executed: " . htmlspecialchars(substr(trim($command), 0, 100)) . (strlen($command) > 100 ? '...' : ''));
            }
        }
    }
    
    // Check if users table exists as a proxy for successful schema execution
    $result = $mysqli->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->num_rows > 0) {
        add_message('success', "Database tables seem to be created successfully (users table found).");
    } else {
        add_message('errors', "Database tables might not have been created successfully (users table not found). Check SQL errors above.");
        // $mysqli->close(); // Do not close yet, admin user creation might still be attempted or desired
        // return false; // Commented out to allow admin user creation attempt even if some table creations failed.
    }
    
    return $mysqli; // Return connection for admin user creation
}

/**
 * Creates the admin user.
 */
function create_admin_user($mysqli, $admin_email, $admin_password, $admin_name = 'Admin') {
    if (!$mysqli || $mysqli->connect_error) {
        add_message('errors', "Cannot create admin user: No valid database connection.");
        return false;
    }
    
    if (empty($admin_email) || empty($admin_password)) {
        add_message('errors', "Admin email and password cannot be empty.");
        return false;
    }

    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $google_id = 'admin_' . uniqid(); // Create a unique placeholder for Google ID for admin

    $stmt = $mysqli->prepare("INSERT INTO users (id, google_id, email, name, avatar_url, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = CURRENT_TIMESTAMP");
    
    if ($stmt === false) {
        add_message('errors', "Failed to prepare statement for admin user creation: " . $mysqli->error);
        return false;
    }
    // Use google_id for the primary key 'id' as well, for admin user simplicity matching user table structure.
    $stmt->bind_param("ssss", $google_id, $google_id, $admin_email, $admin_name); 

    if ($stmt->execute()) {
        add_message('success', "Admin user '$admin_email' created/updated successfully.");
        $stmt->close();
        return true;
    } else {
        add_message('errors', "Failed to create/update admin user '$admin_email': " . $stmt->error);
        $stmt->close();
        return false;
    }
}


// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host    = trim($_POST['db_host'] ?? '');
    $db_name    = trim($_POST['db_name'] ?? '');
    $db_user    = trim($_POST['db_user'] ?? '');
    $db_pass    = trim($_POST['db_pass'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass = trim($_POST['admin_password'] ?? ''); // Password itself, not hashed yet

    // Basic Validation
    if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_email) || empty($admin_pass)) {
        add_message('errors', "All fields are required except Database Password (which can be empty for some MySQL setups).");
    } else {
        $step = 2; // Move to processing step display

        // 1. Update config.php
        if (update_config_file($db_host, $db_name, $db_user, $db_pass, $config_file_path)) {
            // 2. Setup Database (Connect, Create DB, Create Tables)
            // This function now returns the mysqli connection object on success or false on failure
            $mysqli_conn = setup_database($db_host, $db_name, $db_user, $db_pass, $schema_file_path);

            if ($mysqli_conn) {
                // 3. Create Admin User
                if (create_admin_user($mysqli_conn, $admin_email, $admin_pass)) {
                    add_message('success', "Installation process completed successfully!");
                    add_message('info', "<strong>IMPORTANT:</strong> For security reasons, please delete or rename <code>install.php</code> immediately.");
                    $step = 3; // Success step
                } else {
                    add_message('errors', "Admin user creation failed. Please check errors above.");
                }
                $mysqli_conn->close(); // Close connection after admin user creation
            } else {
                add_message('errors', "Database setup failed. Please check errors above and your database credentials in <code>config.php</code> if it was created/updated.");
            }
        } else {
             add_message('errors', "Configuration file update failed. Installation cannot proceed.");
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .message-box { margin-bottom: 1rem; padding: 1rem; border-radius: 0.25rem; }
        .message-box.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-box.errors { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message-box.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .message-box ul { list-style-position: inside; padding-left: 1.5rem; }
        .message-box li { margin-bottom: 0.25rem; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto p-6 max-w-xl bg-white shadow-xl rounded-lg">
        <h1 class="text-3xl font-bold mb-8 text-center text-gray-700">Application Installer</h1>

        <?php if (!empty($messages['errors'])): ?>
            <div class="message-box errors">
                <strong class="block mb-2">Errors:</strong>
                <ul><?php foreach ($messages['errors'] as $msg) echo '<li>' . $msg . '</li>'; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($messages['success'])): ?>
            <div class="message-box success">
                <strong class="block mb-2">Success:</strong>
                <ul><?php foreach ($messages['success'] as $msg) echo '<li>' . $msg . '</li>'; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($messages['info'])): ?>
            <div class="message-box info">
                <strong class="block mb-2">Information:</strong>
                <ul><?php foreach ($messages['info'] as $msg) echo '<li>' . $msg . '</li>'; ?></ul>
            </div>
        <?php endif; ?>


        <?php if ($step < 3): // Show form if not fully completed ?>
        <form action="install.php" method="POST" class="space-y-6">
            <div>
                <h2 class="text-xl font-semibold mb-4 text-gray-600 border-b pb-2">Database Configuration</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="db_host" class="block text-sm font-medium text-gray-700">Database Host</label>
                        <input type="text" name="db_host" id="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="db_name" class="block text-sm font-medium text-gray-700">Database Name</label>
                        <input type="text" name="db_name" id="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="db_user" class="block text-sm font-medium text-gray-700">Database User</label>
                        <input type="text" name="db_user" id="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="db_pass" class="block text-sm font-medium text-gray-700">Database Password</label>
                        <input type="password" name="db_pass" id="db_pass" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-semibold mb-4 text-gray-600 border-b pb-2">Admin User Setup</h2>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="admin_email" class="block text-sm font-medium text-gray-700">Admin Email</label>
                        <input type="email" name="admin_email" id="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="admin_password" class="block text-sm font-medium text-gray-700">Admin Password</label>
                        <input type="password" name="admin_password" id="admin_password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>
            </div>

            <div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:shadow-outline transition-colors duration-150">
                    Install Application
                </button>
            </div>
        </form>
        <?php elseif ($step === 3): ?>
             <div class="text-center mt-6">
                <p class="text-lg text-gray-700">You can now try to <a href="index.php" class="text-blue-600 hover:underline">access your application</a>.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($step > 1 && $step < 3 && !empty($messages['errors'])): // If errors occurred during processing ?>
            <div class="mt-6 text-center">
                <p class="text-red-600">Installation encountered errors. Please review the messages above and try again.</p>
                <a href="install.php" class="inline-block mt-2 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Retry Installation</a>
            </div>
        <?php endif; ?>


        <footer class="mt-10 text-center text-sm text-gray-500">
            <p>Please ensure <code>config.php</code> is writable by this script during installation and that <code>schema.sql</code> is present and readable.</p>
            <p>After successful installation, <strong>delete or rename <code>install.php</code></strong> for security.</p>
        </footer>
    </div>
</body>
</html>
