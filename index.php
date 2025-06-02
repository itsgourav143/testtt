<?php
session_start();
require_once 'config.php';
require_once 'database.php';

$task_creation_message = ''; // To store success or error messages
$task_update_message = ''; // To store status update messages

// Handle Task Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update_status'])) {
    if (isset($_SESSION['user_id'])) {
        $task_id_to_update = filter_input(INPUT_POST, 'update_status_task_id', FILTER_VALIDATE_INT);
        $new_status = trim($_POST['new_status'] ?? '');
        $current_user_id = $_SESSION['user_id'];

        // Validate status to be one of the allowed ENUM values from schema.sql
        $allowed_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];

        if ($task_id_to_update && !empty($new_status) && in_array($new_status, $allowed_statuses)) {
            $db_update = get_db_connection();
            if ($db_update) {
                if (update_task_status($db_update, $task_id_to_update, $new_status, $current_user_id)) {
                    // $task_update_message = '<p class="text-green-500 text-center mb-4">Task status updated successfully!</p>';
                    // Redirect to clear POST data and refresh the list
                    header("Location: index.php?status_updated=true");
                    exit;
                } else {
                    $task_update_message = '<p class="text-red-500 text-center mb-4">Failed to update task status. Task may not exist or you do not have permission.</p>';
                    // Log error: error_log("Task status update failed for task $task_id_to_update by user $current_user_id.");
                }
                close_db_connection($db_update);
            } else {
                $task_update_message = '<p class="text-red-500 text-center mb-4">Database connection failed for status update.</p>';
            }
        } else {
            $task_update_message = '<p class="text-red-500 text-center mb-4">Invalid data provided for status update.</p>';
        }
    } else {
        $task_update_message = '<p class="text-yellow-500 text-center mb-4">You must be logged in to update task status.</p>';
    }
}

// Check for status update success message from redirect
if (isset($_GET['status_updated']) && $_GET['status_updated'] == 'true') {
    $task_update_message = '<p class="text-green-500 text-center mb-4">Task status updated successfully!</p>';
}


// Handle Task Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (isset($_SESSION['user_id'])) {
        $creator_user_id = $_SESSION['user_id']; // User creating the task

        $title = trim($_POST['task_title'] ?? '');
        $description = trim($_POST['task_description'] ?? '');
        $due_date = !empty($_POST['task_due_date']) ? trim($_POST['task_due_date']) : null;
        $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? trim($_POST['assigned_to_user_id']) : $creator_user_id; // Default to self if not set or empty

        if (empty($title)) {
            $task_creation_message = '<p class="text-red-500 text-center mb-4">Task title cannot be empty.</p>';
        } else {
            $db = get_db_connection();
            if ($db) {
                if (create_task($db, $creator_user_id, $title, $description, $due_date, 'pending', 'medium', $assigned_to_user_id)) {
                    $task_creation_message = '<p class="text-green-500 text-center mb-4">Task created successfully!</p>';
                    // header("Location: index.php?task_created=true");
                    // exit;
                } else {
                    $task_creation_message = '<p class="text-red-500 text-center mb-4">Failed to create task. Please try again.</p>';
                }
                close_db_connection($db);
            } else {
                $task_creation_message = '<p class="text-red-500 text-center mb-4">Database connection failed. Please try again later.</p>';
                // Log error: error_log("Database connection failed for task creation.");
            }
        }
    } else {
        // This case should ideally not happen if the form is only shown to logged-in users,
        // but it's good practice to handle it.
        $task_creation_message = '<p class="text-yellow-500 text-center mb-4">You must be logged in to create a task.</p>';
    }
}


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
    <title>Login with Google</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async=""></script>
    <script>
      window.OneSignal = window.OneSignal || [];
      OneSignal.push(function() {
        OneSignal.init({
          appId: "YOUR_ONESIGNAL_APP_ID", // Replace with your OneSignal App ID
          safari_web_id: "YOUR_SAFARI_WEB_ID", // Optional: If you have a Safari Web ID
          notifyButton: {
            enable: true, // Show a bell icon to subscribe/unsubscribe
          },
          allowLocalhostAsSecureOrigin: true, // Useful for testing on localhost
        });
      });
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center">
    <div class="container mx-auto p-6 max-w-md bg-white shadow-md rounded-lg">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-700">My Application</h1>

        <?php echo $task_creation_message; // Display task creation messages here ?>
        <?php echo $task_update_message; // Display task update messages here ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            // Fetch users for the assign to dropdown
            $all_users = [];
            $db_users = get_db_connection();
            if ($db_users) {
                $all_users = get_all_users($db_users);
                close_db_connection($db_users);
            }
            ?>
            <div class="text-center">
                <p class="text-lg text-gray-800">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
                <img src="https://via.placeholder.com/100" alt="User Avatar" class="mx-auto rounded-full my-4">
                <a href="index.php?action=logout" class="bg-red-500 text-white py-2 px-4 rounded hover:bg-red-600 transition-colors duration-150 mb-8">Logout</a>
            </div>

            <!-- Task Creation Form -->
            <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Create New Task</h2>
                <form action="index.php" method="POST">
                    <div class="mb-4">
                        <label for="task_title" class="block text-sm font-medium text-gray-600 mb-1">Title</label>
                        <input type="text" name="task_title" id="task_title" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" placeholder="Enter task title">
                    </div>
                    <div class="mb-4">
                        <label for="task_description" class="block text-sm font-medium text-gray-600 mb-1">Description (Optional)</label>
                        <textarea name="task_description" id="task_description" rows="3" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" placeholder="Enter task description"></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="task_due_date" class="block text-sm font-medium text-gray-600 mb-1">Due Date (Optional)</label>
                        <input type="date" name="task_due_date" id="task_due_date" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    </div>
                    <div class="mb-6">
                        <label for="assigned_to_user_id" class="block text-sm font-medium text-gray-600 mb-1">Assign To</label>
                        <select name="assigned_to_user_id" id="assigned_to_user_id" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                            <option value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">Assign to me</option>
                            <?php if (!empty($all_users)): ?>
                                <?php foreach ($all_users as $user): ?>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): // Don't list current user again ?>
                                        <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                             <option value="">Unassigned (No one / Later)</option>
                        </select>
                    </div>
                    <button type="submit" name="add_task" class="w-full bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 transition-colors duration-150">Add Task</button>
                </form>
            </div>
            <!-- End Task Creation Form -->

            <!-- Task List -->
            <div class="mt-10">
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Your Tasks</h2>
                <?php
                $user_tasks = [];
                if (isset($_SESSION['user_id'])) {
                    $db_list = get_db_connection();
                    if ($db_list) {
                        $user_tasks = get_tasks_by_user($db_list, $_SESSION['user_id']);
                        close_db_connection($db_list);
                    } else {
                        echo '<p class="text-red-500 text-center">Error connecting to the database to fetch tasks.</p>';
                    }
                }

                if (!empty($user_tasks)) :
                ?>
                    <ul class="space-y-4">
                        <?php foreach ($user_tasks as $task) : ?>
                            <li class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition-shadow duration-150 ease-in-out">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($task['title']); ?></h3>
                                        <?php if (!empty($task['description'])) : ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right ml-4 flex-shrink-0">
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full
                                            <?php
                                            switch ($task['status']) {
                                                case 'pending': echo 'bg-yellow-200 text-yellow-800'; break;
                                                case 'in_progress': echo 'bg-blue-200 text-blue-800'; break;
                                                case 'completed': echo 'bg-green-200 text-green-800'; break;
                                                case 'cancelled': echo 'bg-red-200 text-red-800'; break;
                                                default: echo 'bg-gray-200 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $task['status']))); ?>
                                        </span>
                                        <?php if (!empty($task['priority'])) : ?>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full ml-2
                                            <?php
                                            switch ($task['priority']) {
                                                case 'low': echo 'bg-gray-300 text-gray-900'; break;
                                                case 'medium': echo 'bg-orange-300 text-orange-900'; break;
                                                case 'high': echo 'bg-pink-300 text-pink-900'; break;
                                                default: echo 'bg-gray-200 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars(ucfirst($task['priority'])); ?>
                                        </span>
                                        <?php endif; ?>

                                    </div>
                                </div>
                                <div class="mt-3 text-xs text-gray-500">
                                    <?php if (!empty($task['due_date'])) : ?>
                                        <span>Due: <?php echo htmlspecialchars(date("M j, Y", strtotime($task['due_date']))); ?></span>
                                    <?php else: ?>
                                        <span>No due date</span>
                                    <?php endif; ?>
                                    <span class="mx-2">|</span>
                                    <span>Created: <?php echo htmlspecialchars(date("M j, Y, g:i a", strtotime($task['created_at']))); ?></span>
                                    <?php
                                    $assignee_name = 'Not assigned';
                                    if (!empty($task['assigned_to_user_id'])) {
                                        // $db_assignee needed for get_user_by_id.
                                        // This opens a new connection per task if assigned.
                                        // For larger lists, consider fetching all users once outside the loop and mapping.
                                        $db_assignee_info = get_db_connection();
                                        if ($db_assignee_info) {
                                            $assignee_user = get_user_by_id($db_assignee_info, $task['assigned_to_user_id']);
                                            if ($assignee_user) {
                                                $assignee_name = htmlspecialchars($assignee_user['name']);
                                            } else {
                                                $assignee_name = 'Unknown User';
                                            }
                                            close_db_connection($db_assignee_info);
                                        } else {
                                            $assignee_name = 'Error fetching assignee';
                                        }
                                    }
                                    ?>
                                    <span class="mx-2">|</span>
                                    <span>Assigned to: <?php echo $assignee_name; ?></span>
                                </div>
                                <!-- Action Buttons / Forms -->
                                <div class="mt-3 border-t pt-3 flex justify-end space-x-2 items-center">
                                    <button class="text-xs bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md transition-colors">Edit</button>
                                    <button class="text-xs bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors">Delete</button>

                                    <?php if ($task['status'] !== 'completed'): ?>
                                    <form action="index.php" method="POST" class="inline">
                                        <input type="hidden" name="update_status_task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                        <input type="hidden" name="new_status" value="completed">
                                        <button type="submit" name="submit_update_status" class="text-xs bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md transition-colors">
                                            Mark Completed
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($task['status'] === 'completed' || $task['status'] === 'cancelled'): // Option to revert to pending ?>
                                    <form action="index.php" method="POST" class="inline">
                                        <input type="hidden" name="update_status_task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                        <input type="hidden" name="new_status" value="pending">
                                        <button type="submit" name="submit_update_status" class="text-xs bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md transition-colors">
                                            Mark Pending
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                     <?php if ($task['status'] !== 'cancelled'): ?>
                                    <form action="index.php" method="POST" class="inline">
                                        <input type="hidden" name="update_status_task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                        <input type="hidden" name="new_status" value="cancelled">
                                        <button type="submit" name="submit_update_status" class="text-xs bg-gray-500 hover:bg-gray-600 text-white py-1 px-3 rounded-md transition-colors">
                                            Mark Cancelled
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="text-gray-600 text-center py-4">No tasks yet. Create one above!</p>
                <?php endif; ?>
            </div>
            <!-- End Task List -->

        <?php else: ?>
            <div class="text-center">
                <p class="text-lg text-gray-600 mb-6">Please log in to continue.</p>
                <a href="?action=login_google" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg inline-flex items-center transition-transform transform hover:scale-105">
                    <svg class="fill-current w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48px" height="48px"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path><path fill="none" d="M0 0h48v48H0z"></path></svg>
                    Login with Google
                </a>
                <p class="mt-4 text-xs text-gray-500">
                    (This is a mock login. No actual Google authentication will occur.)
                </p>
            </div>
        <?php endif; ?>

        <?php
        // Placeholder for where Google would redirect after authentication
        // For now, we'll simulate this with a GET parameter 'action=login_google'
        // which will then "redirect" to the 'code' parameter simulation.
        if (isset($_GET['action']) && $_GET['action'] === 'login_google') {
            // In a real app, this would be the URL you provide to Google to redirect back to.
            // Google would append a 'code' parameter to it.
            $simulatedRedirectUriWithCode = 'index.php?code=mock_auth_code_from_google';
            header('Location: ' . $simulatedRedirectUriWithCode);
            exit;
        }
        ?>
    </div>
</body>
</html>
