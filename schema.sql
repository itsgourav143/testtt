-- SQL schema for the Task Management Application

-- Users Table
-- Stores user information, primarily for those logging in via Google.
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(255) NOT NULL,             -- Primary Key, typically the Google User ID. VARCHAR is used for flexibility with Google ID format.
    google_id VARCHAR(255) NOT NULL UNIQUE, -- Google's unique user identifier.
    email VARCHAR(255) NOT NULL UNIQUE,   -- User's email address, should be unique.
    name VARCHAR(255) NOT NULL,           -- User's full name or display name.
    avatar_url VARCHAR(2048),             -- URL to the user's avatar image (optional).
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Timestamp of when the user record was created.
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Timestamp of the last update to the user record.
    PRIMARY KEY (id)
);

-- Index on google_id for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_google_id ON users(google_id);

-- Tasks Table
-- Stores task information, linked to users.
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT,                -- Primary Key, auto-incrementing integer.
    user_id VARCHAR(255) NOT NULL,        -- Foreign Key referencing the users table's id column.
    title VARCHAR(255) NOT NULL,          -- The title or description of the task.
    description TEXT,                     -- A more detailed description of the task (optional).
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending', -- Task status.
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium', -- Task priority (optional).
    due_date DATE,                        -- Due date for the task (optional).
    assigned_to_user_id VARCHAR(255) NULL, -- Foreign Key referencing users.id for assignee (can be NULL if unassigned)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Timestamp of when the task was created.
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Timestamp of the last update.
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- If a user (creator) is deleted, their tasks are also deleted.
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL -- If an assigned user is deleted, the task becomes unassigned (SET NULL).
);

-- Indexes for common query patterns
CREATE INDEX IF NOT EXISTS idx_tasks_user_id ON tasks(user_id); -- For tasks created by user
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_to_user_id ON tasks(assigned_to_user_id); -- For tasks assigned to user
CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_priority ON tasks(priority);
CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date);

-- Notes:
-- 1. `IF NOT EXISTS`: Ensures that the statements don't cause errors if the tables/indexes already exist.
-- 2. `VARCHAR(255)` for user IDs: Google IDs can be long strings.
-- 3. `ON DELETE CASCADE` for `tasks.user_id`: If a user is deleted, all their associated tasks will also be deleted.
--    This might be desired behavior, but consider if soft deletes or re-assigning tasks would be better in some scenarios.
-- 4. `TEXT` for `tasks.description`: Allows for longer, multi-line descriptions.
-- 5. `ENUM` for `status` and `priority`: Restricts values to a predefined set, ensuring data consistency.
-- 6. `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`: Automatically sets the creation time.
-- 7. `ON UPDATE CURRENT_TIMESTAMP`: Automatically updates the `updated_at` field when a row is modified.
-- 8. `avatar_url` can be quite long, hence `VARCHAR(2048)`.
-- 9. The `users.id` is the primary key and is intended to store the Google User ID directly.
--    If you prefer an auto-incrementing integer ID for the `users` table as well, you could add an `internal_id INT AUTO_INCREMENT PRIMARY KEY`
--    and keep `google_id` as a separate unique column. However, using Google ID as PK simplifies things if it's guaranteed to be unique and stable.

-- Example of how to add a user (conceptual, not for direct execution here):
-- INSERT INTO users (id, google_id, email, name, avatar_url)
-- VALUES ('google_user_id_123', 'google_user_id_123', 'user@example.com', 'John Doe', 'http://example.com/avatar.jpg');

-- Example of how to add a task (conceptual):
-- INSERT INTO tasks (user_id, title, description, status, priority, due_date)
-- VALUES ('google_user_id_123', 'Finish project report', 'Complete all sections and proofread.', 'in_progress', 'high', '2023-12-31');

-- Consider adding a `settings` or `preferences` table if users need to store application-specific settings.
-- CREATE TABLE IF NOT EXISTS user_settings (
--     user_id VARCHAR(255) NOT NULL,
--     setting_key VARCHAR(100) NOT NULL,
--     setting_value VARCHAR(255),
--     PRIMARY KEY (user_id, setting_key),
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
-- );
-- CREATE INDEX IF NOT EXISTS idx_user_settings_user_id ON user_settings(user_id);
