-- Every change to a product's stock, so stock levels can be explained and audited.
-- quantity_before/after are the stock around the change; user_id is who made it (the buyer
-- for purchases), transaction_id links purchases. Existing stock is recorded as INITIAL_STOCK.
CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    transaction_id BIGINT UNSIGNED NULL,
    movement_type ENUM('INITIAL_STOCK', 'RESTOCK', 'PURCHASE', 'ADJUSTMENT') NOT NULL,
    quantity_change INT NOT NULL,
    quantity_before INT UNSIGNED NOT NULL,
    quantity_after INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY inventory_movements_product_id_id (product_id, id),
    KEY inventory_movements_created_at (created_at),
    CONSTRAINT inventory_movements_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT inventory_movements_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT inventory_movements_transaction_fk FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE RESTRICT,
    CONSTRAINT inventory_movements_change_adds_up CHECK (quantity_after = quantity_before + quantity_change)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO inventory_movements (product_id, movement_type, quantity_change, quantity_before, quantity_after, reason)
SELECT id, 'INITIAL_STOCK', quantity_available, 0, quantity_available, 'Stock before movement history began'
FROM products;
