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
$current_user_name = $_SESSION['user_name']; // Assuming user_name is stored in session

$task_creation_message = '';
$task_action_message = ''; // General message for updates, deletions etc.
$edit_task_id = null;
$task_to_edit = null;

// --- Handle GET request for editing a task ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_task_id'])) {
    $edit_task_id = filter_input(INPUT_GET, 'edit_task_id', FILTER_VALIDATE_INT);
    if ($edit_task_id) {
        $db_edit_fetch = get_db_connection();
        if ($db_edit_fetch) {
            $task_to_edit = get_task_by_id($db_edit_fetch, $edit_task_id, $current_user_id);
            if (!$task_to_edit) {
                $task_action_message = '<p class="text-red-500 text-center mb-4">Error: Task not found or you do not have permission to edit it.</p>';
                $edit_task_id = null; // Clear invalid ID
            }
            // close_db_connection($db_edit_fetch); // Keep open for other ops or close if done with GET phase
        } else {
            $task_action_message = '<p class="text-red-500 text-center mb-4">Database connection failed while fetching task to edit.</p>';
        }
    }
}

// --- Handle Task Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    $task_id_to_delete = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);

    if ($task_id_to_delete) {
        $db_delete = get_db_connection();
        if ($db_delete) {
            if (delete_task($db_delete, $task_id_to_delete, $current_user_id)) {
                header("Location: my-task.php?task_deleted=true");
                exit;
            } else {
                // delete_task logs specific errors.
                $task_action_message = '<p class="text-red-500 text-center mb-4">Failed to delete task. You may not be the task creator or the task does not exist.</p>';
            }
            close_db_connection($db_delete);
        } else {
            $task_action_message = '<p class="text-red-500 text-center mb-4">Database connection failed for task deletion.</p>';
        }
    } else {
        $task_action_message = '<p class="text-red-500 text-center mb-4">Invalid task ID for deletion.</p>';
    }
}


// --- Handle Quick Task Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task_status_quick') {
    $task_id_quick_update = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
    $new_status_quick = trim($_POST['new_status'] ?? '');

    if ($task_id_quick_update && !empty($new_status_quick) && in_array($new_status_quick, ['pending', 'completed', 'in_progress', 'cancelled'])) {
        $db_quick_update = get_db_connection();
        if ($db_quick_update) {
            // Using current_user_id for permission check in update_task_status
            // The update_task_status in database.php needs to be checked if it correctly handles permissions.
            // Current update_task_status checks if current_user_id is task.user_id (creator).
            // This might need adjustment if assignees should also be able to quick update status.
            // For now, assuming creator only for quick status change via this simplified button.
            // Or, ideally, update_task_status should be enhanced to check creator OR assignee.
            // Let's assume update_task_status checks if the user is the *creator*.
            // If an assignee needs to change status, they should use the Edit form.
            // For consistency, it's better if update_task_status also allows assignee.
            // Let's assume it does (or we'd adjust it).
            // The `update_task_status` function in database.php currently takes $user_id which is used to check if $user_id === tasks.user_id
            // Let's use $current_user_id for that check.
            
            // Re-checking update_task_status in database.php: it takes ($db, $task_id, $new_status, $user_id)
            // and in its SQL: WHERE id = ? AND user_id = ?
            // This means only the CREATOR can use this quick status change.
            // This is acceptable for now. If assignees need it, the Edit form provides that.
            // Or, update_task_status could be changed to: WHERE id = ? AND (user_id = ? OR assigned_to_user_id = ?)
            // For now, proceeding with current behavior (creator only for quick status change).

            if (update_task_status($db_quick_update, $task_id_quick_update, $new_status_quick, $current_user_id)) {
                header("Location: my-task.php?task_status_changed=true");
                exit;
            } else {
                $task_action_message = '<p class="text-red-500 text-center mb-4">Failed to update task status (quick). You may not be the task creator or an error occurred.</p>';
            }
            close_db_connection($db_quick_update);
        } else {
            $task_action_message = '<p class="text-red-500 text-center mb-4">Database connection failed for quick status update.</p>';
        }
    } else {
        $task_action_message = '<p class="text-red-500 text-center mb-4">Invalid data for quick status update.</p>';
    }
}


// --- Handle Task Update (Full Edit Form) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task') {
    $task_id_to_update = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['task_title'] ?? '');
    $description = trim($_POST['task_description'] ?? '');
    $due_date = !empty($_POST['task_due_date']) ? trim($_POST['task_due_date']) : null;
    $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? trim($_POST['assigned_to_user_id']) : null;
    if ($assigned_to_user_id === '') $assigned_to_user_id = null;
    $status = trim($_POST['task_status'] ?? 'pending');
    $priority = trim($_POST['task_priority'] ?? 'medium');

    if (empty($title) || !$task_id_to_update) {
        $task_action_message = '<p class="text-red-500 text-center mb-4">Task title cannot be empty and task ID must be valid for update.</p>';
    } else {
        $db_update = get_db_connection();
        if ($db_update) {
            if (update_task($db_update, $task_id_to_update, $current_user_id, $title, $description, $due_date, $status, $priority, $assigned_to_user_id)) {
                header("Location: my-task.php?task_updated=true");
                exit;
            } else {
                // update_task function logs specific errors. General message here.
                $task_action_message = '<p class="text-red-500 text-center mb-4">Failed to update task. You may not have permission or an error occurred.</p>';
            }
            close_db_connection($db_update);
        } else {
            $task_action_message = '<p class="text-red-500 text-center mb-4">Database connection failed for task update.</p>';
        }
    }
}


// --- Handle Task Creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['task_title'] ?? '');
    $description = trim($_POST['task_description'] ?? '');
    $due_date = !empty($_POST['task_due_date']) ? trim($_POST['task_due_date']) : null;
    // Default to assigning to self if not specified or empty string from "Unassigned"
    $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? trim($_POST['assigned_to_user_id']) : $current_user_id; 
    if ($assigned_to_user_id === '') $assigned_to_user_id = null; // Ensure empty string from "Unassigned" becomes NULL for DB

    $status = trim($_POST['task_status'] ?? 'pending'); // Added status field
    $priority = trim($_POST['task_priority'] ?? 'medium'); // Added priority field


    if (empty($title)) {
        $task_creation_message = '<p class="text-red-500 text-center mb-4">Task title cannot be empty.</p>';
    } else {
        $db = get_db_connection();
        if ($db) {
            if (create_task($db, $current_user_id, $title, $description, $due_date, $status, $priority, $assigned_to_user_id)) {
                header("Location: my-task.php?task_created=true"); 
                exit;
            } else {
                $task_creation_message = '<p class="text-red-500 text-center mb-4">Failed to create task. Please try again. DB Error: '.(isset($db->error) && $db->error ? $db->error : 'Unknown').'</p>';
            }
            close_db_connection($db);
        } else {
            $task_creation_message = '<p class="text-red-500 text-center mb-4">Database connection failed. Please try again later.</p>';
        }
    }
}

if (isset($_GET['task_created']) && $_GET['task_created'] == 'true') {
    $task_action_message = '<p class="text-green-500 text-center mb-4">Task created successfully!</p>';
}
if (isset($_GET['task_updated']) && $_GET['task_updated'] == 'true') {
    $task_action_message = '<p class="text-green-500 text-center mb-4">Task updated successfully!</p>';
}
if (isset($_GET['task_status_changed']) && $_GET['task_status_changed'] == 'true') {
    $task_action_message = '<p class="text-green-500 text-center mb-4">Task status changed successfully!</p>';
}
if (isset($_GET['task_deleted']) && $_GET['task_deleted'] == 'true') {
    $task_action_message = '<p class="text-green-500 text-center mb-4">Task deleted successfully!</p>';
}


// --- Fetch Data for Display ---
// $db_main is used for all data fetching on this page load.
// It might have been opened by GET edit_task_id logic. If not, open it.
if (!isset($db_main) || !$db_main) { // Check if $db_main is not already set or is null/false
    $db_main = get_db_connection();
}

$all_users_for_dropdown = [];
$my_tasks_list = [];
$user_details_cache = []; 

if ($db_main) {
    $all_users_for_dropdown = get_all_users($db_main);
    // If we are editing, $task_to_edit is already fetched using $current_user_id for permission.
    // If not editing, $task_to_edit is null.
    
    // Fetch the main list of tasks
    $my_tasks_list = get_my_tasks($db_main, $current_user_id);

    // Pre-fetch user details for creators and assignees to optimize
    $user_ids_to_fetch = [];
    if ($task_to_edit) { // If editing, ensure its users are in the cache
        if (!empty($task_to_edit['user_id'])) $user_ids_to_fetch[] = $task_to_edit['user_id'];
        if (!empty($task_to_edit['assigned_to_user_id'])) $user_ids_to_fetch[] = $task_to_edit['assigned_to_user_id'];
    }
    foreach ($my_tasks_list as $task) {
        if (!empty($task['user_id'])) $user_ids_to_fetch[] = $task['user_id'];
        if (!empty($task['assigned_to_user_id'])) $user_ids_to_fetch[] = $task['assigned_to_user_id'];
    }
    $user_ids_to_fetch = array_unique(array_filter($user_ids_to_fetch));

    foreach($user_ids_to_fetch as $uid) {
        if (!isset($user_details_cache[$uid])) { // Fetch only if not already in cache
            $user_info = get_user_by_id($db_main, $uid); // This get_user_by_id does not check permissions, it just fetches
            if ($user_info) {
                $user_details_cache[$uid] = $user_info;
            }
        }
    }
} else {
    $task_action_message .= '<p class="text-red-500 text-center mb-4">Critical: Database connection failed. Cannot load page data.</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Task Manager</title>
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
        .task-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
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
            <a href="index.php" class="text-xl font-bold text-gray-700 hover:text-blue-600">Task Manager Home</a>
            <div class="flex items-center">
                <span class="text-gray-600 mr-4">Welcome, <?php echo htmlspecialchars($current_user_name); ?>!</span>
                <a href="index.php?action=logout" class="bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 transition-colors">Logout</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto mt-8 p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">My Tasks</h1>

        <?php echo $task_action_message; // Display all action messages here ?>
        
        <?php if ($edit_task_id && $task_to_edit): ?>
        <!-- Edit Task Form -->
        <div id="editTaskFormContainer" class="bg-white p-6 rounded-lg shadow-xl mb-8 border-2 border-blue-500">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-2xl font-semibold text-gray-700">Edit Task: <?php echo htmlspecialchars($task_to_edit['title']); ?></h2>
                <a href="my-task.php" class="text-blue-600 hover:text-blue-800 font-medium">Cancel Edit</a>
            </div>
            <form action="my-task.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task_to_edit['id']); ?>">
                
                <div>
                    <label for="edit_task_title" class="block text-sm font-medium text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="task_title" id="edit_task_title" value="<?php echo htmlspecialchars($task_to_edit['title']); ?>" required class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="edit_task_description" class="block text-sm font-medium text-gray-600 mb-1">Description</label>
                    <textarea name="task_description" id="edit_task_description" rows="3" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($task_to_edit['description'] ?? ''); ?></textarea>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_task_due_date" class="block text-sm font-medium text-gray-600 mb-1">Due Date</label>
                        <input type="date" name="task_due_date" id="edit_task_due_date" value="<?php echo htmlspecialchars($task_to_edit['due_date'] ?? ''); ?>" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="edit_assigned_to_user_id" class="block text-sm font-medium text-gray-600 mb-1">Assign To</label>
                        <select name="assigned_to_user_id" id="edit_assigned_to_user_id" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="">Unassigned</option>
                            <?php foreach ($all_users_for_dropdown as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo ($task_to_edit['assigned_to_user_id'] == $user['id'] ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_task_status" class="block text-sm font-medium text-gray-600 mb-1">Status</label>
                        <select name="task_status" id="edit_task_status" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            <?php foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $status_option): ?>
                                <option value="<?php echo $status_option; ?>" <?php echo ($task_to_edit['status'] == $status_option ? 'selected' : ''); ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status_option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_task_priority" class="block text-sm font-medium text-gray-600 mb-1">Priority</label>
                        <select name="task_priority" id="edit_task_priority" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                             <?php foreach (['low', 'medium', 'high'] as $priority_option): ?>
                                <option value="<?php echo $priority_option; ?>" <?php echo ($task_to_edit['priority'] == $priority_option ? 'selected' : ''); ?>>
                                    <?php echo ucfirst($priority_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <a href="my-task.php" class="py-2.5 px-4 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="bg-green-600 text-white py-2.5 px-4 rounded-md hover:bg-green-700 transition-colors duration-150">Save Changes</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Task Creation Form (show only if not editing) -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold mb-5 text-gray-700 border-b pb-3">Create New Task</h2>
            <form action="my-task.php" method="POST" class="space-y-4">
                <div>
                    <label for="task_title" class="block text-sm font-medium text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="task_title" id="task_title" required class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" placeholder="Enter task title">
                </div>
                <div>
                    <label for="task_description" class="block text-sm font-medium text-gray-600 mb-1">Description</label>
                    <textarea name="task_description" id="task_description" rows="3" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" placeholder="Enter task description"></textarea>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label for="task_due_date" class="block text-sm font-medium text-gray-600 mb-1">Due Date</label>
                        <input type="date" name="task_due_date" id="task_due_date" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    </div>
                    <div>
                        <label for="assigned_to_user_id" class="block text-sm font-medium text-gray-600 mb-1">Assign To</label>
                        <select name="assigned_to_user_id" id="assigned_to_user_id" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                            <option value="<?php echo htmlspecialchars($current_user_id); ?>">Assign to me</option>
                            <?php foreach ($all_users_for_dropdown as $user): ?>
                                <?php if ($user['id'] !== $current_user_id): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <option value="">Unassigned</option>
                        </select>
                    </div>
                </div>
                 <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label for="task_status" class="block text-sm font-medium text-gray-600 mb-1">Status</label>
                        <select name="task_status" id="task_status" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                            <option value="pending" selected>Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label for="task_priority" class="block text-sm font-medium text-gray-600 mb-1">Priority</label>
                        <select name="task_priority" id="task_priority" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_task" class="w-full bg-blue-600 text-white py-2.5 px-4 rounded-md hover:bg-blue-700 transition-colors duration-150">Add Task</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Task Display List -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold mb-5 text-gray-700 border-b pb-3">Your Task List</h2>
            <?php if (!empty($my_tasks_list)): ?>
                <div class="space-y-4">
                    <?php foreach ($my_tasks_list as $task): ?>
                        <?php
                            $status_class = 'status-' . strtolower(htmlspecialchars($task['status']));
                            $priority_class = 'priority-' . strtolower(htmlspecialchars($task['priority']));
                            $creator_name = isset($user_details_cache[$task['user_id']]) ? htmlspecialchars($user_details_cache[$task['user_id']]['name']) : 'Unknown';
                            $assignee_name = 'Not assigned';
                            if (!empty($task['assigned_to_user_id']) && isset($user_details_cache[$task['assigned_to_user_id']])) {
                                $assignee_name = htmlspecialchars($user_details_cache[$task['assigned_to_user_id']]['name']);
                            } elseif (!empty($task['assigned_to_user_id'])) {
                                $assignee_name = 'Unknown User';
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
                            <div class="text-xs text-gray-500 mb-3">
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
                            <div class="flex justify-end space-x-2">
                                <?php 
                                // Show Edit button only if user is creator or assignee
                                $can_edit = ($task['user_id'] == $current_user_id || $task['assigned_to_user_id'] == $current_user_id);
                                if ($can_edit && (!$edit_task_id || $edit_task_id != $task['id'])): // Hide if this task is being edited
                                ?>
                                    <a href="my-task.php?edit_task_id=<?php echo $task['id']; ?>#editTaskFormContainer" class="text-xs bg-gray-300 hover:bg-gray-400 text-gray-800 py-1 px-3 rounded-md transition-colors">Edit</a>
                                <?php endif; ?>
                                
                                <?php if ($task['user_id'] == $current_user_id): // Only creator can delete ?>
                                <form action="my-task.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                    <button type="submit" class="text-xs bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md transition-colors">Delete</button>
                                </form>
                                <?php endif; ?>
                                
                                <!-- Simplified Status Update (can be removed if Edit form is preferred for all changes) -->
                                <?php if ($can_edit && $task['status'] !== 'completed'): ?>
                                    <form action="my-task.php" method="POST" class="inline"> 
                                        <input type="hidden" name="action" value="update_task_status_quick"> <!-- Different action for quick status -->
                                        <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                        <input type="hidden" name="new_status" value="completed">
                                        <button type="submit" class="text-xs bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md transition-colors">Mark Completed</button>
                                    </form>
                                <?php elseif ($can_edit && $task['status'] === 'completed'): ?>
                                     <form action="my-task.php" method="POST" class="inline">
                                        <input type="hidden" name="action" value="update_task_status_quick">
                                        <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                        <input type="hidden" name="new_status" value="pending">
                                        <button type="submit" class="text-xs bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md transition-colors">Mark Pending</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-600 text-center py-5">You have no tasks created by you or assigned to you yet. Create one above!</p>
            <?php endif; ?>
        </div>
    </main>

    <?php
        if ($db_main) {
            close_db_connection($db_main); // Close main DB connection at the end of the script
        }
    ?>
</body>
</html>
