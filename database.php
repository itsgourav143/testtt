<?php

require_once 'config.php'; // Include the configuration file

/**
 * Establishes a database connection using MySQLi.
 *
 * This function uses the database credentials defined in config.php.
 * It includes basic error handling to check if the connection was successful.
 *
 * @return mysqli|null A mysqli connection object on success, or null on failure.
 */
function get_db_connection() {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        // In a real application, you would log this error more robustly.
        // For example, using error_log() or a dedicated logging library.
        // error_log("Database Connection Failed: " . $conn->connect_error);

        // For security reasons, it's often better not to expose detailed error messages
        // directly to the user in a production environment.
        // Instead, show a generic error message.
        // die("Sorry, we are experiencing technical difficulties. Please try again later.");
        
        // For development, it's useful to see the error.
        // Remove or comment out the die() statement in production.
        // For this example, we'll print the error if it occurs, but return null.
        // echo "Connection failed: " . $conn->connect_error; // For debugging
    return null;
    }

    return $conn;
}

/**
 * Creates a new task in the database.
 *
 * @param mysqli $db The database connection object.
 * @param string $user_id The ID of the user creating the task.
 * @param string $title The title of the task.
 * @param string|null $description The description of the task (optional).
 * @param string|null $due_date The due date of the task (optional, format YYYY-MM-DD).
 * @param string $status The status of the task (e.g., 'pending', 'in_progress', 'completed'). Defaults to 'pending'.
 * @param string $priority The priority of the task (e.g., 'low', 'medium', 'high'). Defaults to 'medium'.
 * @param string|null $assigned_to_user_id The ID of the user to whom the task is assigned. Can be null.
 * @return bool True on success, false on failure.
 */
function create_task($db, $creator_user_id, $title, $description, $due_date, $status = 'pending', $priority = 'medium', $assigned_to_user_id = null) {
    // Basic validation for required fields
    if (empty($creator_user_id) || empty($title)) {
        error_log("create_task: Missing creator_user_id or title.");
        return false;
    }

    // Sanitize description, due_date, and assigned_to_user_id
    $description = !empty($description) ? $description : null;
    $due_date = !empty($due_date) ? $due_date : null;
    $assigned_to_user_id = !empty($assigned_to_user_id) ? $assigned_to_user_id : null; // Ensure empty string becomes NULL

    // Prepare SQL statement
    // Note: The column name in `tasks` table for the user who created the task is `user_id`.
    // We are adding `assigned_to_user_id` as a new concept.
    // The schema would need an `assigned_to_user_id` column. Assuming it exists or will be added.
    // For now, let's assume the schema is: tasks (id, user_id (creator), title, ..., assigned_to_user_id)
    // If your schema change in `schema.sql` was meant to rename `user_id` to `creator_id` and add `user_id` for assignee,
    // then this query needs adjustment. Based on current `schema.sql`, `user_id` is creator.
    // Let's proceed with `user_id` as creator and add a new field `assigned_to_user_id` to the query.
    // This requires `assigned_to_user_id` column to be added to `tasks` table in `schema.sql`.
    // For the purpose of this step, I will assume the `tasks` table has an `assigned_to_user_id` column.

    $stmt = $db->prepare("INSERT INTO tasks (user_id, title, description, due_date, status, priority, assigned_to_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt === false) {
        error_log("create_task: Failed to prepare statement: " . $db->error);
        return false;
    }

    // Bind parameters
    // s = string (creator_user_id)
    // s = string (title)
    // s = string (description)
    // s = string (due_date)
    // s = string (status)
    // s = string (priority)
    // s = string (assigned_to_user_id) - can be null
    $stmt->bind_param("sssssss", $creator_user_id, $title, $description, $due_date, $status, $priority, $assigned_to_user_id);

    // Execute statement
    if ($stmt->execute()) {
        $task_id = $stmt->insert_id; // Get the ID of the newly created task
        $stmt->close();

        // Send notification if assigned to someone else
        if ($assigned_to_user_id && $assigned_to_user_id !== $creator_user_id) {
            $creator_info = get_user_by_id($db, $creator_user_id); // Fetch creator's name
            $creator_name = $creator_info ? $creator_info['name'] : 'Someone';
            
            $message = "New task '" . htmlspecialchars($title) . "' has been assigned to you by " . htmlspecialchars($creator_name) . ".";
            $headings = ["en" => "New Task Assigned"];
            $data = ["task_id" => $task_id, "url" => "my-task.php?edit_task_id=" . $task_id]; // Example data
            send_onesignal_notification($message, $assigned_to_user_id, $headings, $data);
        }
        return true;
    } else {
        // Error in execution
        error_log("create_task: Failed to execute statement: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Deletes a task from the database.
 *
 * @param mysqli $db The database connection object.
 * @param int $task_id The ID of the task to delete.
 * @param string $current_user_id The ID of the user attempting the deletion (must be the creator).
 * @return bool True on success, false on failure or permission denied.
 */
function delete_task($db, $task_id, $current_user_id) {
    if (empty($task_id) || empty($current_user_id)) {
        error_log("delete_task: Missing task_id or current_user_id.");
        return false;
    }

    // Verify the current_user_id is the creator of the task.
    // We need to fetch the task's user_id first.
    $stmt_check = $db->prepare("SELECT user_id FROM tasks WHERE id = ?");
    if ($stmt_check === false) {
        error_log("delete_task: Failed to prepare check statement: " . $db->error);
        return false;
    }
    $stmt_check->bind_param("i", $task_id);
    $task_creator_id = null;
    if ($stmt_check->execute()) {
        $result = $stmt_check->get_result();
        if ($row = $result->fetch_assoc()) {
            $task_creator_id = $row['user_id'];
        }
        $stmt_check->close();
    } else {
        error_log("delete_task: Failed to execute check statement: " . $stmt_check->error);
        $stmt_check->close();
        return false;
    }

    if ($task_creator_id !== $current_user_id) {
        error_log("delete_task: Permission denied. User $current_user_id is not the creator of task $task_id.");
        return false; // Permission denied
    }

    // If permission granted, proceed with deletion
    $stmt_delete = $db->prepare("DELETE FROM tasks WHERE id = ?");
    if ($stmt_delete === false) {
        error_log("delete_task: Failed to prepare delete statement: " . $db->error);
        return false;
    }
    $stmt_delete->bind_param("i", $task_id);

    if ($stmt_delete->execute()) {
        $affected_rows = $stmt_delete->affected_rows;
        $stmt_delete->close();
        return $affected_rows > 0; // True if one row was deleted
    } else {
        error_log("delete_task: Failed to execute delete statement: " . $stmt_delete->error);
        $stmt_delete->close();
        return false;
    }
}

/**
 * Retrieves all tasks for a given user ID, ordered by creation date.
 *
 * @param mysqli $db The database connection object.
 * @param string $user_id The ID of the user whose tasks are to be retrieved.
 * @return array An array of tasks (as associative arrays) on success, or an empty array on failure/no tasks.
 */
function get_tasks_by_user($db, $user_id) {
    $tasks = [];
    if (empty($user_id)) {
        error_log("get_tasks_by_user: Missing user_id.");
        return $tasks;
    }

    $stmt = $db->prepare("SELECT id, title, description, status, priority, due_date, created_at, user_id, assigned_to_user_id FROM tasks WHERE user_id = ? ORDER BY created_at DESC");
    if ($stmt === false) {
        error_log("get_tasks_by_user: Failed to prepare statement: " . $db->error);
        return $tasks;
    }

    $stmt->bind_param("s", $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
    } else {
        error_log("get_tasks_by_user: Failed to execute statement: " . $stmt->error);
        $stmt->close();
    }
    return $tasks;
}

/**
 * Retrieves all tasks from the database, ordered by creation date.
 * Useful for admin purposes or testing.
 *
 * @param mysqli $db The database connection object.
 * @return array An array of all tasks (as associative arrays) on success, or an empty array on failure.
 */
function get_all_tasks($db) {
    $tasks = [];
    $stmt = $db->prepare("SELECT id, user_id, title, description, status, priority, due_date, created_at, assigned_to_user_id FROM tasks ORDER BY created_at DESC");
    if ($stmt === false) {
        error_log("get_all_tasks: Failed to prepare statement: " . $db->error);
        return $tasks;
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
    } else {
        error_log("get_all_tasks: Failed to execute statement: " . $stmt->error);
        $stmt->close();
    }
    return $tasks;
}

/**
 * Updates the status of a specific task.
 *
 * @param mysqli $db The database connection object.
 * @param int $task_id The ID of the task to update.
 * @param string $new_status The new status for the task (e.g., 'pending', 'in_progress', 'completed', 'cancelled').
 * @param string $user_id The ID of the user who owns the task (for verification).
 * @return bool True on success, false on failure or if task not found/permission denied.
 */
function update_task_status($db, $task_id, $new_status, $user_id) {
    if (empty($task_id) || empty($new_status) || empty($user_id)) {
        error_log("update_task_status: Missing task_id, new_status, or user_id.");
        return false;
    }

    // Prepare SQL statement to update the task status.
    // Allows update if the user is the creator OR the assignee.
    
    // Fetch task details first to get title, creator, and current assignee for notifications
    // We use a direct query here as get_task_by_id has its own permission checks that might interfere
    // or be redundant if we are only checking if the $user_id has rights to update status.
    $task_details_stmt = $db->prepare("SELECT title, user_id, assigned_to_user_id FROM tasks WHERE id = ?");
    if (!$task_details_stmt) {
        error_log("update_task_status: Failed to prepare fetch task details statement: " . $db->error);
        return false;
    }
    $task_details_stmt->bind_param("i", $task_id);
    $task_title = "Untitled Task";
    $task_creator_id = null;
    $task_assignee_id = null;
    if ($task_details_stmt->execute()) {
        $result = $task_details_stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $task_title = $row['title'];
            $task_creator_id = $row['user_id'];
            $task_assignee_id = $row['assigned_to_user_id'];
        }
    } else {
        error_log("update_task_status: Failed to execute fetch task details: " . $task_details_stmt->error);
    }
    $task_details_stmt->close();

    // Now, perform the update
    $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND (user_id = ? OR assigned_to_user_id = ?)");
    if ($stmt === false) {
        error_log("update_task_status: Failed to prepare update statement: " . $db->error);
        return false;
    }

    $stmt->bind_param("siss", $new_status, $task_id, $user_id, $user_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        if ($affected_rows > 0) {
            // Send notifications
            $message = "Task '" . htmlspecialchars($task_title) . "' status updated to: " . htmlspecialchars($new_status) . ".";
            $headings = ["en" => "Task Status Updated"];
            $notification_data = ["task_id" => $task_id, "url" => "my-task.php?edit_task_id=" . $task_id];

            // Notify creator if not the updater
            if ($task_creator_id && $task_creator_id !== $user_id) {
                send_onesignal_notification($message, $task_creator_id, $headings, $notification_data);
            }
            // Notify assignee if set, not the updater, and not the same as creator (to avoid double notify if creator was already notified)
            if ($task_assignee_id && $task_assignee_id !== $user_id && $task_assignee_id !== $task_creator_id) {
                send_onesignal_notification($message, $task_assignee_id, $headings, $notification_data);
            }
            return true; 
        } else {
            error_log("update_task_status: No rows affected. Task ID $task_id might not exist, user $user_id may not be creator/assignee, or status is already '$new_status'.");
            return false; 
        }
    } else {
        error_log("update_task_status: Failed to execute update statement: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Retrieves all users from the database.
 *
 * @param mysqli $db The database connection object.
 * @return array An array of users (id, name, email) on success, or an empty array on failure.
 */
function get_all_users($db) {
    $users = [];
    $stmt = $db->prepare("SELECT id, name, email FROM users ORDER BY name ASC");
    if ($stmt === false) {
        error_log("get_all_users: Failed to prepare statement: " . $db->error);
        return $users;
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
    } else {
        error_log("get_all_users: Failed to execute statement: " . $stmt->error);
        $stmt->close();
    }
    return $users;
}

/**
 * Retrieves a specific user by their ID.
 *
 * @param mysqli $db The database connection object.
 * @param string $user_id The ID of the user to retrieve.
 * @return array|null An associative array of the user's data (id, name, email) or null if not found.
 */
function get_user_by_id($db, $user_id) {
    if (empty($user_id)) {
        return null;
    }

    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        error_log("get_user_by_id: Failed to prepare statement: " . $db->error);
        return null;
    }

    $stmt->bind_param("s", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); // Fetch a single row
        $stmt->close();
        return $user ? $user : null;
    } else {
        error_log("get_user_by_id: Failed to execute statement: " . $stmt->error);
        $stmt->close();
        return null;
    }
}

/**
 * Retrieves tasks created by or assigned to a specific user.
 *
 * @param mysqli $db The database connection object.
 * @param string $user_id The ID of the user.
 * @return array An array of tasks, ordered by due date (NULLS LAST), then created_at.
 */
function get_my_tasks($db, $user_id) {
    $tasks = [];
    if (empty($user_id)) {
        error_log("get_my_tasks: Missing user_id.");
        return $tasks;
    }

    // Fetches tasks where the user is the creator (user_id) OR the assignee (assigned_to_user_id)
    // Orders by due_date (tasks without due date or past due date come later), then by creation date.
    $stmt = $db->prepare("
        SELECT id, user_id, assigned_to_user_id, title, description, status, priority, due_date, created_at 
        FROM tasks 
        WHERE user_id = ? OR assigned_to_user_id = ? 
        ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, created_at DESC
    ");

    if ($stmt === false) {
        error_log("get_my_tasks: Failed to prepare statement: " . $db->error);
        return $tasks;
    }

    $stmt->bind_param("ss", $user_id, $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
    } else {
        error_log("get_my_tasks: Failed to execute statement: " . $stmt->error);
        $stmt->close();
    }
    return $tasks;
}

/**
 * Retrieves a specific task by its ID, ensuring the user has permission to view/edit it.
 *
 * @param mysqli $db The database connection object.
 * @param int $task_id The ID of the task to retrieve.
 * @param string $user_id The ID of the current user (for permission check).
 * @return array|null An associative array of the task's data or null if not found or no permission.
 */
function get_task_by_id($db, $task_id, $user_id) {
    if (empty($task_id) || empty($user_id)) {
        error_log("get_task_by_id: Missing task_id or user_id.");
        return null;
    }

    $stmt = $db->prepare("
        SELECT id, user_id, assigned_to_user_id, title, description, status, priority, due_date, created_at 
        FROM tasks 
        WHERE id = ? AND (user_id = ? OR assigned_to_user_id = ?)
        LIMIT 1
    ");

    if ($stmt === false) {
        error_log("get_task_by_id: Failed to prepare statement: " . $db->error);
        return null;
    }

    $stmt->bind_param("iss", $task_id, $user_id, $user_id); // task_id is INT, user_ids are STRING

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $task = $result->fetch_assoc();
        $stmt->close();
        return $task ? $task : null; // Returns task if found and user has permission, else null
    } else {
        error_log("get_task_by_id: Failed to execute statement: " . $stmt->error);
        $stmt->close();
        return null;
    }
}

/**
 * Updates a task's details in the database.
 *
 * @param mysqli $db The database connection object.
 * @param int $task_id The ID of the task to update.
 * @param string $current_user_id The ID of the user attempting the update (for permission check).
 * @param string $title The new title.
 * @param string|null $description The new description.
 * @param string|null $due_date The new due date.
 * @param string $status The new status.
 * @param string $priority The new priority.
 * @param string|null $assigned_to_user_id The new assignee's user ID.
 * @return bool True on success, false on failure or permission denied.
 */
function update_task($db, $task_id, $current_user_id, $title, $description, $due_date, $status, $priority, $assigned_to_user_id) {
    if (empty($task_id) || empty($current_user_id) || empty($title) || empty($status) || empty($priority)) {
        error_log("update_task: Missing required fields (task_id, current_user_id, title, status, priority).");
        return false;
    }

    // First, verify permission by fetching the task and checking ownership/assignment
    $task_to_check = get_task_by_id($db, $task_id, $current_user_id);
    if (!$task_to_check) {
        error_log("update_task: Permission denied or task not found for task ID $task_id and user ID $current_user_id.");
        return false; // User doesn't have rights or task doesn't exist
    }
    // Note: get_task_by_id already checks if current_user_id is creator OR assignee.

    $description = !empty($description) ? $description : null;
    $due_date = !empty($due_date) ? $due_date : null;
    $assigned_to_user_id = !empty($assigned_to_user_id) ? $assigned_to_user_id : null;

    $stmt = $db->prepare("
        UPDATE tasks 
        SET title = ?, description = ?, due_date = ?, status = ?, priority = ?, assigned_to_user_id = ?
        WHERE id = ? 
    ");
    // Note: We don't need to check user_id / assigned_to_user_id in WHERE clause here again,
    // because we've already verified permission using get_task_by_id.
    // $task_to_check contains the state of the task *before* the update.

    if ($stmt === false) {
        error_log("update_task: Failed to prepare statement: " . $db->error);
        return false;
    }

    $stmt->bind_param("ssssssi", $title, $description, $due_date, $status, $priority, $assigned_to_user_id, $task_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows; // Can be 0 if data is identical, but execute still true
        $stmt->close();

        // Notification logic
        $original_creator_id = $task_to_check['user_id'];
        $original_assignee_id = $task_to_check['assigned_to_user_id'];
        $original_status = $task_to_check['status'];
        $task_title_for_notif = htmlspecialchars($title); // Use the new title for notifications
        $notification_data = ["task_id" => $task_id, "url" => "my-task.php?edit_task_id=" . $task_id];

        // 1. Assignee Changed
        if ($assigned_to_user_id !== $original_assignee_id) {
            // Notify new assignee (if any, and not the updater)
            if ($assigned_to_user_id && $assigned_to_user_id !== $current_user_id) {
                $msg = "Task '$task_title_for_notif' has been assigned to you.";
                send_onesignal_notification($msg, $assigned_to_user_id, ["en" => "Task Assigned to You"], $notification_data);
            }
            // Notify old assignee (if any, and not the updater, and not the new assignee)
            if ($original_assignee_id && $original_assignee_id !== $current_user_id && $original_assignee_id !== $assigned_to_user_id) {
                $msg = "Task '$task_title_for_notif' has been unassigned from you.";
                send_onesignal_notification($msg, $original_assignee_id, ["en" => "Task Unassigned"], $notification_data);
            }
        }

        // 2. Status Changed (and assignee hasn't just been cleared by this update if they were the only other party)
        if ($status !== $original_status) {
            $msg = "Task '$task_title_for_notif' status updated to: " . htmlspecialchars($status) . ".";
            $headings = ["en" => "Task Status Updated"];
            
            // Notify creator (if not the updater and not the current assignee if they are being notified about assignment change)
            if ($original_creator_id !== $current_user_id) {
                 // Avoid double notification if creator is new assignee and already notified, or old assignee and already notified.
                $already_notified_creator_as_assignee = ($assigned_to_user_id === $original_creator_id && $assigned_to_user_id !== $original_assignee_id) ||
                                                       ($original_assignee_id === $original_creator_id && $assigned_to_user_id !== $original_assignee_id && $original_assignee_id !== $current_user_id);
                if (!$already_notified_creator_as_assignee) {
                   send_onesignal_notification($msg, $original_creator_id, $headings, $notification_data);
                }
            }

            // Notify current assignee (if any, not the updater, and not the creator who was just notified)
            // This handles notifying an existing assignee about a status change not covered by reassignment.
            if ($assigned_to_user_id && $assigned_to_user_id !== $current_user_id && $assigned_to_user_id !== $original_creator_id) {
                 // Avoid double notification if assignee is new and already notified about assignment
                if ($assigned_to_user_id !== $original_assignee_id && $assigned_to_user_id === $current_user_id) { 
                    // This condition is tricky: if current user is the new assignee, they don't need this status update.
                    // The logic for notifying new assignee above covers it.
                } else if ($assigned_to_user_id === $original_assignee_id) { // Existing assignee, status changed by someone else
                     send_onesignal_notification($msg, $assigned_to_user_id, $headings, $notification_data);
                }
            }
        }
        return true; 
    } else {
        error_log("update_task: Failed to execute statement: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Retrieves tasks not created by or assigned to the current user (i.e., "team tasks").
 * This version will fetch tasks that are:
 * 1. Not created by the current user.
 * 2. And, if assigned, not assigned to the current user.
 * OR tasks created by others and unassigned.
 *
 * @param mysqli $db The database connection object.
 * @param string $current_user_id The ID of the current user.
 * @return array An array of tasks, ordered by due date then created_at.
 */
function get_team_tasks($db, $current_user_id) {
    $tasks = [];
    if (empty($current_user_id)) {
        error_log("get_team_tasks: Missing current_user_id.");
        return $tasks;
    }

    // SQL: Select tasks where the creator is not the current user.
    // And, if the task is assigned to someone, it's not assigned to the current user.
    // Tasks created by others and unassigned (assigned_to_user_id IS NULL) will be included.
    $stmt = $db->prepare("
        SELECT id, user_id, assigned_to_user_id, title, description, status, priority, due_date, created_at 
        FROM tasks 
        WHERE user_id != ? AND (assigned_to_user_id IS NULL OR assigned_to_user_id != ?)
        ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, created_at DESC
    ");

    if ($stmt === false) {
        error_log("get_team_tasks: Failed to prepare statement: " . $db->error);
        return $tasks;
    }

    $stmt->bind_param("ss", $current_user_id, $current_user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
    } else {
        error_log("get_team_tasks: Failed to execute statement: " . $stmt->error);
        $stmt->close();
    }
    return $tasks;
}

/**
 * Retrieves task counts grouped by status for a specific user.
 * Counts tasks where the user is either the creator or the assignee.
 *
 * @param mysqli $db The database connection object.
 * @param string $user_id The ID of the user.
 * @return array An associative array where keys are statuses and values are counts.
 */
function get_task_counts_by_status($db, $user_id) {
    $counts = [
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'total' => 0
    ];

    if (empty($user_id)) {
        error_log("get_task_counts_by_status: Missing user_id.");
        return $counts; // Return default zero counts
    }

    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        WHERE user_id = ? OR assigned_to_user_id = ? 
        GROUP BY status
    ");

    if ($stmt === false) {
        error_log("get_task_counts_by_status: Failed to prepare statement: " . $db->error);
        return $counts; // Return default zero counts on error
    }

    $stmt->bind_param("ss", $user_id, $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $total_tasks = 0;
        while ($row = $result->fetch_assoc()) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int)$row['count'];
            }
            $total_tasks += (int)$row['count'];
        }
        $counts['total'] = $total_tasks;
        $stmt->close();
    } else {
        error_log("get_task_counts_by_status: Failed to execute statement: " . $stmt->error);
        // Still return default zero counts on execution error
    }
    return $counts;
}

/**
 * Retrieves tasks for report generation based on filters.
 * Fetches tasks created by or assigned to the specified user.
 *
 * @param mysqli $db The database connection object.
 * @param string $user_id The ID of the user.
 * @param array $statuses Array of status strings to filter by. If empty, all statuses included.
 * @param string|null $start_date Start date for due_date range (YYYY-MM-DD).
 * @param string|null $end_date End date for due_date range (YYYY-MM-DD).
 * @return array An array of task data.
 */
function get_tasks_for_report($db, $user_id, $statuses = [], $start_date = null, $end_date = null) {
    $tasks = [];
    if (empty($user_id)) {
        error_log("get_tasks_for_report: Missing user_id.");
        return $tasks;
    }

    $sql = "SELECT id, user_id, assigned_to_user_id, title, description, status, priority, due_date, created_at 
            FROM tasks 
            WHERE (user_id = ? OR assigned_to_user_id = ?)";

    $params = [$user_id, $user_id];
    $types = "ss";

    if (!empty($statuses)) {
        // Create placeholders for IN clause: ?, ?, ?
        $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql .= " AND status IN (" . $status_placeholders . ")";
        foreach ($statuses as $status) {
            $params[] = $status;
            $types .= "s";
        }
    }

    if (!empty($start_date)) {
        $sql .= " AND due_date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }

    if (!empty($end_date)) {
        $sql .= " AND due_date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    $sql .= " ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, created_at DESC";

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        error_log("get_tasks_for_report: Failed to prepare statement: " . $db->error . " | SQL: " . $sql);
        return $tasks;
    }

    // Dynamically bind parameters
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
    } else {
        error_log("get_tasks_for_report: Failed to execute statement: " . $stmt->error);
    }
    return $tasks;
}

/**
 * Sends a push notification using OneSignal API.
 *
 * @param string $message The main content of the notification.
 * @param string $target_user_id The internal user ID of the recipient.
 * @param array|null $headings Optional. Associative array for notification headings, e.g., ["en" => "Title"].
 * @param array|null $data Optional. Associative array for additional data to send with the notification.
 * @return bool True on success (API call accepted), false on failure.
 */
function send_onesignal_notification($message, $target_user_id, $headings = null, $data = null) {
    if (empty(ONESIGNAL_APP_ID) || empty(ONESIGNAL_REST_API_KEY) || ONESIGNAL_APP_ID === 'YOUR_ONESIGNAL_APP_ID' || ONESIGNAL_REST_API_KEY === 'YOUR_ONESIGNAL_REST_API_KEY') {
        error_log("OneSignal API credentials are not configured in config.php. Notification not sent.");
        return false;
    }

    if (empty($message) || empty($target_user_id)) {
        error_log("send_onesignal_notification: Message or target_user_id is empty.");
        return false;
    }

    $default_headings = ["en" => "Task Manager Notification"];
    $notification_headings = $headings ?: $default_headings;
    
    $fields = [
        'app_id' => ONESIGNAL_APP_ID,
        'include_external_user_ids' => [$target_user_id],
        'headings' => $notification_headings,
        'contents' => ["en" => $message],
        // 'channel_for_external_user_ids' => 'push', // Ensure it's a push
    ];

    if (!empty($data) && is_array($data)) {
        $fields['data'] = $data;
    }
    // Example: Add a URL to open when notification is clicked (works on some platforms)
    // $fields['web_url'] = 'http://localhost/my-task.php'; // Or a specific task URL

    $fields_json = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . ONESIGNAL_REST_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Should be TRUE in production with proper CA certs

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("OneSignal cURL Error: " . $curl_error);
        return false;
    }

    if ($http_code >= 200 && $http_code < 300) {
        // Success
        $response_data = json_decode($response, true);
        error_log("OneSignal Notification Sent: ID - " . ($response_data['id'] ?? 'N/A') . ", Recipients - " . ($response_data['recipients'] ?? 'N/A'));
        return true;
    } else {
        // Failure
        error_log("OneSignal API Error: HTTP $http_code - Response: $response");
        return false;
    }
}


/**
 * Closes an active database connection.
 *
 * @param mysqli $conn The mysqli connection object to close.
 */
function close_db_connection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/*
// --- Example Usage (Uncomment to test) ---

// Attempt to get a database connection
$connection = get_db_connection();

if ($connection) {
    echo "Successfully connected to the database.<br>";

    // --- Example: Perform a simple query (Make sure you have a table to query) ---
    // $sql = "SELECT DATABASE() AS dbname;"; // Query to get current database name
    // $result = $connection->query($sql);
    //
    // if ($result && $result->num_rows > 0) {
    //     $row = $result->fetch_assoc();
    //     echo "Current database: " . $row['dbname'] . "<br>";
    // } else {
    //     echo "Query failed or returned no results: " . $connection->error . "<br>";
    // }
    // --- End Example Query ---

    // Close the connection
    close_db_connection($connection);
    echo "Database connection closed.";
} else {
    // This message is for when get_db_connection() returns null
    echo "Failed to connect to the database. Please check your configuration and ensure the database server is running.";
}
*/

?>
