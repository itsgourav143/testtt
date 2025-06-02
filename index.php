<?php
session_start();
require_once 'config.php';
require_once 'database.php';

// Placeholder for handling Google API response
if (isset($_GET['code'])) {
    // Simulate handling Google API response
    // In a real application, you would exchange the code for an access token
    // and fetch user information from Google.
    $_SESSION['user_id'] = 'mock_google_user_id'; // Simulate user login
    $_SESSION['user_name'] = 'Mock Google User'; // Simulate user name

    // Redirect to a protected page or display user info
    header('Location: index.php');
    exit;
}

// Placeholder for user logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async=""></script>
    <script>
      window.OneSignal = window.OneSignal || [];
      OneSignal.push(function() {
        OneSignal.init({
          appId: "YOUR_ONESIGNAL_APP_ID", // Replace with your OneSignal App ID from config.php if available, or hardcode
          notifyButton: { enable: true },
          allowLocalhostAsSecureOrigin: true,
        });
        <?php if (isset($_SESSION['user_id'])): ?>
        // Only set external user ID if the user is logged in
        OneSignal.setExternalUserId("<?php echo $_SESSION['user_id']; ?>");
        <?php endif; ?>
      });
    </script>
    <style>
        .nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            margin: 0.5rem;
            border-radius: 0.5rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
        }
        .nav-button:hover {
            transform: translateY(-2px);
        }
        .nav-button .icon {
            margin-right: 0.75rem;
            font-size: 1.5rem; /* Adjust icon size */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold text-gray-700">Task Manager</h1>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex items-center">
                    <span class="text-gray-600 mr-4">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="index.php?action=logout" class="bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 transition-colors">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container mx-auto mt-10 p-6 flex flex-col items-center justify-center">
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Logged-in user: Navigation Buttons -->
            <div class="text-center">
                <h2 class="text-3xl font-semibold text-gray-700 mb-8">Dashboard</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="my-task.php" class="nav-button bg-blue-500 hover:bg-blue-600">
                        <span class="icon">📝</span> My Tasks
                    </a>
                    <a href="team-task.php" class="nav-button bg-green-500 hover:bg-green-600">
                        <span class="icon">👥</span> Team Tasks
                    </a>
                    <a href="report.php" class="nav-button bg-purple-500 hover:bg-purple-600">
                        <span class="icon">📊</span> Reports
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Not logged-in user: Login Button -->
            <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-md w-full">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Welcome!</h2>
                <p class="text-gray-600 mb-8">Please log in with your Google account to manage your tasks.</p>
                <a href="?action=login_google" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg inline-flex items-center transition-transform transform hover:scale-105 text-lg">
                    <svg class="fill-current w-6 h-6 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path><path fill="none" d="M0 0h48v48H0z"></path></svg>
                    Login with Google
                </a>
                <p class="mt-6 text-xs text-gray-500">
                    (This is a mock login for demonstration purposes.)
                </p>
            </div>
        <?php endif; ?>

        <?php
        // This Google redirect simulation logic should ideally be part of the login handler,
        // but keeping it here as per original structure for now.
        if (isset($_GET['action']) && $_GET['action'] === 'login_google' && !isset($_SESSION['user_id'])) {
            $simulatedRedirectUriWithCode = 'index.php?code=mock_auth_code_from_google';
            header('Location: ' . $simulatedRedirectUriWithCode);
            exit;
        }
        ?>
    </main>
</body>
</html>
