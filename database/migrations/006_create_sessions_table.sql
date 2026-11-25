-- Web sessions, so sign-ins survive restarts and redeploys of the web container.
-- id is the SHA-256 of the session ID: a copy of this table cannot be used to take over a
-- session. user_id lets a user's sessions be ended at once (deactivation, password change).
CREATE TABLE IF NOT EXISTS sessions (
    id CHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    payload MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY sessions_user_id (user_id),
    KEY sessions_last_activity (last_activity),
    CONSTRAINT sessions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
