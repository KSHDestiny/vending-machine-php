-- Account status for admin user management. Inactive users cannot sign in, and their
-- existing sessions and tokens stop working. deactivated_at records when it happened.
ALTER TABLE users
    ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN deactivated_at TIMESTAMP NULL DEFAULT NULL AFTER status,
    ADD KEY users_role_status (role, status);
