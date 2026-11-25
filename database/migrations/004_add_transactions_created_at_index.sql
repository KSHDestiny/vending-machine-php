-- Date-range filters (dashboard sales trend, reports) read purchases by created_at.
CREATE INDEX transactions_created_at ON transactions (created_at);
