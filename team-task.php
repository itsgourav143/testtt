<?php
session_start();
require_once 'config.php';
require_once 'database.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name']; 

$page_message = '';
$team_tasks_list = [];
$user_details_cache = []; // Cache for user details

$db = get_db_connection();
if ($db) {
    $team_tasks_list = get_team_tasks($db, $current_user_id);

    // Pre-fetch user details for creators and assignees to optimize
    $user_ids_to_fetch = [];
    foreach ($team_tasks_list as $task) {
        if (!empty($task['user_id']) && !isset($user_details_cache[$task['user_id']])) {
            $user_ids_to_fetch[] = $task['user_id'];
        }
        if (!empty($task['assigned_to_user_id']) && !isset($user_details_cache[$task['assigned_to_user_id']])) {
            $user_ids_to_fetch[] = $task['assigned_to_user_id'];
        }
    }
    $user_ids_to_fetch = array_unique($user_ids_to_fetch);
    foreach($user_ids_to_fetch as $uid) {
        $user_info = get_user_by_id($db, $uid); // get_user_by_id just fetches, no permission check needed here
        if ($user_info) {
            $user_details_cache[$uid] = $user_info;
        }
    }
    close_db_connection($db);
} else {
    $page_message = '<p class="text-red-500 text-center mb-4">Critical: Database connection failed. Cannot load page data.</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Tasks - Task Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async=""></script>
    <script>
      window.OneSignal = window.OneSignal || [];
      OneSignal.push(function() {
        OneSignal.init({
          appId: "YOUR_ONESIGNAL_APP_ID", // From config.php or direct
          notifyButton: { enable: true },
          allowLocalhostAsSecureOrigin: true,
        });
        <?php if (isset($_SESSION['user_id'])): ?>
        OneSignal.setExternalUserId("<?php echo $_SESSION['user_id']; ?>");
        <?php endif; ?>
      });
    </script>
    <style>
        .task-card { margin-bottom: 1rem; padding: 1rem; border-radius: 0.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-pending { background-color: #fef9c3; border-left: 4px solid #facc15; } /* yellow */
        .status-in_progress { background-color: #dbeafe; border-left: 4px solid #60a5fa; } /* blue */
        .status-completed { background-color: #d1fae5; border-left: 4px solid #34d399; } /* green */
        .status-cancelled { background-color: #fee2e2; border-left: 4px solid #f87171; } /* red */
        .priority-low { border-right: 4px solid #9ca3af; } /* gray */
        .priority-medium { border-right: 4px solid #fb923c; } /* orange */
        .priority-high { border-right: 4px solid #f472b6; } /* pink */
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div>
                <a href="index.php" class="text-xl font-bold text-gray-700 hover:text-blue-600">Task Manager Home</a>
                <a href="my-task.php" class="ml-4 text-md text-blue-600 hover:text-blue-800">My Tasks</a>
            </div>
            <div class="flex items-center">
                <span class="text-gray-600 mr-4">Welcome, <?php echo htmlspecialchars($current_user_name); ?>!</span>
                <a href="index.php?action=logout" class="bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 transition-colors">Logout</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Team Tasks</h1>

        <?php if (!empty($page_message)) echo $page_message; ?>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <?php if (!empty($team_tasks_list)): ?>
                <div class="space-y-4">
                    <?php foreach ($team_tasks_list as $task): ?>
                        <?php
                            $status_class = 'status-' . strtolower(htmlspecialchars($task['status']));
                            $priority_class = 'priority-' . strtolower(htmlspecialchars($task['priority']));
                            $creator_name = isset($user_details_cache[$task['user_id']]) ? htmlspecialchars($user_details_cache[$task['user_id']]['name']) : 'Unknown';
                            $assignee_name = 'Not assigned';
                            if (!empty($task['assigned_to_user_id']) && isset($user_details_cache[$task['assigned_to_user_id']])) {
                                $assignee_name = htmlspecialchars($user_details_cache[$task['assigned_to_user_id']]['name']);
                            } elseif (!empty($task['assigned_to_user_id'])) {
                                $assignee_name = 'Unknown User'; // Should be rare if cache is populated correctly
                            }
                        ?>
                        <div class="task-card bg-white <?php echo $status_class; ?> <?php echo $priority_class; ?>">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($task['title']); ?></h3>
                                <div class="text-right">
                                     <span class="text-sm font-medium inline-block py-1 px-3 uppercase rounded-full text-white 
                                        <?php echo ($task['status'] === 'completed' ? 'bg-green-500' : ($task['status'] === 'in_progress' ? 'bg-blue-500' : ($task['status'] === 'cancelled' ? 'bg-red-500' : 'bg-yellow-500'))); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $task['status']))); ?>
                                    </span>
                                     <span class="text-sm font-medium inline-block py-1 px-3 uppercase rounded-full text-white ml-2
                                        <?php echo ($task['priority'] === 'high' ? 'bg-pink-500' : ($task['priority'] === 'medium' ? 'bg-orange-500' : 'bg-gray-500')); ?>">
                                        <?php echo htmlspecialchars(ucfirst($task['priority'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <p class="text-gray-600 text-sm mb-2"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                            <?php endif; ?>
                            <div class="text-xs text-gray-500 mb-1">
                                <?php if (!empty($task['due_date'])): ?>
                                    <span>Due: <?php echo htmlspecialchars(date("M j, Y", strtotime($task['due_date']))); ?></span>
                                <?php else: ?>
                                    <span>No due date</span>
                                <?php endif; ?>
                                <span class="mx-1">|</span>
                                <span>Created: <?php echo htmlspecialchars(date("M j, Y, g:i a", strtotime($task['created_at']))); ?></span>
                                <span class="mx-1">|</span>
                                <span>Creator: <?php echo $creator_name; ?></span>
                                <span class="mx-1">|</span>
                                <span>Assigned to: <?php echo $assignee_name; ?></span>
                            </div>
                            <div class="mt-2 border-t pt-2 text-xs text-gray-400">
                                Read-only view. No actions available on this page.
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-600 text-center py-5">No other team tasks to display at the moment.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
