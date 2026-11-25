-- Products can be taken off sale without deleting them: inactive products are hidden
-- from customers and cannot be bought, but admins still see and edit them.
ALTER TABLE products
    ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER quantity_available;
