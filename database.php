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
        $stmt->close();
        return true;
    } else {
        // Error in execution
        error_log("create_task: Failed to execute statement: " . $stmt->error);
        $stmt->close();
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

    // Prepare SQL statement to update the task status, ensuring the task belongs to the user
    $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        error_log("update_task_status: Failed to prepare statement: " . $db->error);
        return false;
    }

    $stmt->bind_param("sis", $new_status, $task_id, $user_id); // s: status, i: id (integer), s: user_id

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        if ($affected_rows > 0) {
            return true; // Successfully updated
        } else {
            error_log("update_task_status: No rows affected. Task ID $task_id might not exist or user $user_id does not own it.");
            return false; // No rows affected (task not found for this user, or status is already the new_status)
        }
    } else {
        error_log("update_task_status: Failed to execute statement: " . $stmt->error);
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
