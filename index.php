<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Daily Tasks</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto mt-10 p-4 max-w-2xl">
        <h1 class="text-3xl font-bold mb-6 text-center text-gray-700">My Daily Tasks</h1>
        <form action="index.php" method="POST" class="flex mb-6">
            <input type="text" name="task_text" placeholder="Enter new task..." class="flex-grow p-2 border border-gray-300 rounded-l-md focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            <button type="submit" class="bg-blue-500 text-white p-2 px-4 rounded-r-md hover:bg-blue-600">Add Task</button>
        </form>
        <div id="task-list-container" class="mt-8">
            <?php
            /**
             * A simple single-file PHP task management application using a JSON file for storage.
             * Features: Add, Delete, Toggle Task Status, Responsive UI with Tailwind CSS.
             */

            // --- Configuration ---
            /**
             * Defines the name of the JSON file used to store tasks.
             */
            define('TASKS_FILE', 'tasks.json');

            // --- Helper Functions ---

            /**
             * Reads tasks from the specified JSON file.
             * Provides robust error handling for file reading and JSON decoding.
             *
             * @param string $filePath Path to the JSON file.
             * @return array An array of tasks, or an empty array on failure or if no tasks exist.
             */
            function getTasks($filePath) {
                $tasks = []; // Default to empty array

                if (!file_exists($filePath)) {
                    // File doesn't exist. This is handled by initialization logic,
                    // but returning empty here is a safe fallback.
                    return $tasks;
                }

                $jsonContent = file_get_contents($filePath);
                if ($jsonContent === false) {
                    // Failed to read file (e.g., permissions issue).
                    // In a larger application, this would be logged.
                    return $tasks;
                }

                if (empty($jsonContent)) {
                    // File is empty, which is valid (no tasks).
                    return $tasks;
                }

                $decodedTasks = json_decode($jsonContent, true); // true for associative array
                if (is_array($decodedTasks)) {
                    $tasks = $decodedTasks;
                } else {
                    // Invalid JSON content or json_decode failed.
                    // In a larger application, this would be logged.
                }
                return $tasks;
            }

            /**
             * Saves the provided array of tasks to the specified JSON file.
             * Uses JSON_PRETTY_PRINT for human-readable file content.
             *
             * @param string $filePath Path to the JSON file.
             * @param array  $tasksArray The array of tasks to save.
             * @return bool True on success, false on failure (e.g., encoding error, write permission issue).
             */
            function saveTasks($filePath, $tasksArray) {
                $updatedTasksJson = json_encode($tasksArray, JSON_PRETTY_PRINT);
                if ($updatedTasksJson === false) {
                    // Failed to encode tasks to JSON (should be rare with this data structure).
                    // In a larger application, this would be logged.
                    return false;
                }
                if (file_put_contents($filePath, $updatedTasksJson) === false) {
                    // Failed to write to file (e.g., permissions issue).
                    // In a larger application, this would be logged.
                    return false;
                }
                return true;
            }

            // --- Initialization ---
            // Initialize tasks file if it doesn't exist.
            if (!file_exists(TASKS_FILE)) {
                if (!saveTasks(TASKS_FILE, [])) {
                    // Critical error: Failed to create the tasks file.
                    // The application might not function correctly.
                    // In a real application, display an error message or log prominently.
                }
            }
            
            // --- Action Handling ---
            // Order of GET action checks: Delete, then Toggle. This avoids potential conflicts if multiple action parameters were somehow present.

            // Handle Task Deletion (via GET request)
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                $taskIdToDelete = $_GET['id'];
                $currentTasks = getTasks(TASKS_FILE);

                $initialCount = count($currentTasks);
                // Filter out the task with the matching ID.
                $updatedTasks = array_filter($currentTasks, function ($task) use ($taskIdToDelete) {
                    // Ensure task structure is as expected before accessing 'id'.
                    return isset($task['id']) && $task['id'] !== $taskIdToDelete;
                });

                // Only save if a task was actually removed.
                if (count($updatedTasks) < $initialCount) {
                    $updatedTasks = array_values($updatedTasks); // Re-index array keys.
                    saveTasks(TASKS_FILE, $updatedTasks);
                    // Not checking saveTasks result here for simplicity, but could add error display.
                }
                // Redirect to the same page to clear GET parameters and prevent resubmission.
                header("Location: index.php");
                exit;
            }

            // Handle Task Status Toggle (via GET request)
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
                $taskIdToToggle = $_GET['id'];
                $currentTasks = getTasks(TASKS_FILE);
                $taskFoundAndToggled = false;

                // Find the task and toggle its status.
                foreach ($currentTasks as $key => $task) {
                    // Ensure task structure is as expected.
                    if (isset($task['id']) && $task['id'] === $taskIdToToggle) {
                        $currentTasks[$key]['status'] = (isset($task['status']) && $task['status'] === 'pending' ? 'completed' : 'pending');
                        $taskFoundAndToggled = true;
                        break; // Stop searching once the task is found and updated.
                    }
                }

                if ($taskFoundAndToggled) {
                    saveTasks(TASKS_FILE, $currentTasks);
                    // Not checking saveTasks result here for simplicity.
                }
                // Redirect to clear GET parameters.
                header("Location: index.php");
                exit;
            }

            // Handle Task Addition (via POST request)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_text'])) {
                $taskText = trim($_POST['task_text']); // Remove leading/trailing whitespace.

                // Only add task if text is not empty.
                if (!empty($taskText)) {
                    $currentTasks = getTasks(TASKS_FILE);
                    
                    // Create the new task structure.
                    $newTask = [
                        'id' => uniqid(), // Generate a simple unique ID.
                        'text' => $taskText,
                        'status' => 'pending' // New tasks are pending by default.
                    ];
                    $currentTasks[] = $newTask; // Append to the tasks array.

                    saveTasks(TASKS_FILE, $currentTasks);
                    // Not checking saveTasks result here for simplicity.
                }
                // Redirect to prevent form resubmission on page refresh (Post-Redirect-Get pattern).
                header("Location: index.php");
                exit;
            }

            // --- Data Retrieval for Display ---
            // Load tasks for displaying on the page. This happens after all potential modifications.
            $tasks = getTasks(TASKS_FILE);

            // --- HTML Display Logic ---
            // Display tasks or a message if no tasks are present.
            if (!empty($tasks)) {
                echo '<ul>';
                foreach ($tasks as $task) {
                    $taskTextClass = ($task['status'] === 'completed' ? 'line-through text-gray-500' : '');
                    echo '<li class="bg-white p-3 mb-2 rounded shadow flex justify-between items-center">';
                    echo '<span class="' . $taskTextClass . '">' . htmlspecialchars($task['text']) . '</span>';
                    echo '<div>'; // Container for status and buttons
                    
                    // Status display (optional, could be inferred from button text or styling)
                    // echo '<span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full align-middle mr-2">' . htmlspecialchars($task['status']) . '</span>';

                    // Toggle button
                    $toggleButtonText = ($task['status'] === 'pending' ? 'Mark Complete' : 'Mark Pending');
                    $toggleButtonBaseClass = 'text-white px-2 py-1 rounded text-xs ml-2 align-middle transition-colors duration-150';
                    $toggleButtonClass = ($task['status'] === 'pending' ? 'bg-green-500 hover:bg-green-600' : 'bg-yellow-500 hover:bg-yellow-600');
                    echo '<a href="index.php?action=toggle&id=' . htmlspecialchars($task['id']) . '" 
                           class="' . $toggleButtonClass . ' ' . $toggleButtonBaseClass . '">' . $toggleButtonText . '</a>';
                    
                    // Delete button
                    echo '<a href="index.php?action=delete&id=' . htmlspecialchars($task['id']) . '" 
                           class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 ml-2 align-middle transition-colors duration-150"
                           onclick="return confirm(\'Are you sure you want to delete this task?\');">Delete</a>';
                    echo '</div>';
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="text-gray-500 italic text-center py-4">No tasks yet! Add one above.</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>
