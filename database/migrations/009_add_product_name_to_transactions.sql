-- Each purchase keeps the product name it was bought under, so renaming a product later
-- does not rewrite history (the price is already stored as unit_price). New purchases must
-- supply it; rows that existed before this migration get '' and are backfilled once in
-- the database, not by this migration: see "Data backfills" in README.md for the SQL.
ALTER TABLE transactions ADD COLUMN product_name VARCHAR(100) NOT NULL AFTER product_id;
