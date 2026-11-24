-- Separate database for the integration tests, so they never touch vending_machine.
CREATE DATABASE IF NOT EXISTS vending_machine_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON vending_machine_test.* TO 'vending_app'@'%';
