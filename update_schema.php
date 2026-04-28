<?php
require_once 'config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Only run the new additions: views, procedures, triggers
    $sql = "
-- Drop existing views if they exist
DROP VIEW IF EXISTS vw_unpaid_bookings;
DROP VIEW IF EXISTS vw_invoice_sessions;
DROP VIEW IF EXISTS vw_invoice_items;
DROP VIEW IF EXISTS vw_booking_invoice_summary;

-- Views
CREATE VIEW vw_unpaid_bookings AS
SELECT b.id AS booking_id,
       b.customer_id,
       c.name AS customer_name,
       c.phone AS customer_phone,
       b.start_date,
       b.end_date,
       b.total_expected_amount,
       b.deposit_amount,
       b.status
FROM bookings b
JOIN customers c ON c.id = b.customer_id
WHERE b.status != 'paid';

CREATE VIEW vw_invoice_sessions AS
SELECT rs.id AS session_id,
       bc.booking_id,
       c.name AS court_name,
       rs.play_date,
       rs.reception_time,
       rs.return_time,
       rs.rent_amount,
       bc.price_per_session
FROM rental_sessions rs
JOIN booking_courts bc ON rs.booking_court_id = bc.id
JOIN courts c ON bc.court_id = c.id;

CREATE VIEW vw_invoice_items AS
SELECT sui.id AS item_id,
       rs.id AS session_id,
       bc.booking_id,
       p.id AS product_id,
       p.code AS product_code,
       p.name AS product_name,
       sui.quantity,
       sui.unit_price,
       sui.total_amount
FROM session_used_items sui
JOIN rental_sessions rs ON sui.session_id = rs.id
JOIN booking_courts bc ON rs.booking_court_id = bc.id
JOIN products p ON sui.product_id = p.id;

CREATE VIEW vw_booking_invoice_summary AS
SELECT b.id AS booking_id,
       c.id AS customer_id,
       c.name AS customer_name,
       c.phone AS customer_phone,
       b.deposit_amount,
       b.total_expected_amount,
       b.total_amount
FROM bookings b
JOIN customers c ON b.customer_id = c.id;

-- Procedures
CREATE OR REPLACE PROCEDURE sp_create_rental_session(
    IN p_booking_court_id INT,
    IN p_play_date DATE,
    IN p_reception_time TIME,
    IN p_return_time TIME,
    IN p_rent_amount DECIMAL(12,2),
    IN p_used_items JSONB,
    OUT o_session_id INT
)
LANGUAGE plpgsql
AS \$\$
DECLARE
    item RECORD;
BEGIN
    INSERT INTO rental_sessions (booking_court_id, play_date, reception_time, return_time, rent_amount)
    VALUES (p_booking_court_id, p_play_date, p_reception_time, p_return_time, p_rent_amount)
    RETURNING id INTO o_session_id;

    IF p_used_items IS NOT NULL THEN
        FOR item IN SELECT * FROM jsonb_to_recordset(p_used_items) AS x(product_id INT, unit_price DECIMAL, quantity INT) LOOP
            INSERT INTO session_used_items (session_id, product_id, unit_price, quantity, total_amount)
            VALUES (o_session_id, item.product_id, item.unit_price, item.quantity, item.unit_price * item.quantity);
        END LOOP;
    END IF;
END;
\$\$;

CREATE OR REPLACE PROCEDURE sp_confirm_booking_payment(
    IN p_booking_id INT,
    IN p_total_amount DECIMAL(12,2)
)
LANGUAGE plpgsql
AS \$\$
BEGIN
    UPDATE bookings
    SET status = 'paid', total_amount = p_total_amount
    WHERE id = p_booking_id;
END;
\$\$;

-- Function and Triggers
CREATE OR REPLACE FUNCTION fn_sync_stock_on_session_used_items() RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE products SET stock_qty = stock_qty - NEW.quantity WHERE id = NEW.product_id;
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        IF NEW.product_id = OLD.product_id THEN
            UPDATE products SET stock_qty = stock_qty - (NEW.quantity - OLD.quantity) WHERE id = NEW.product_id;
        ELSE
            UPDATE products SET stock_qty = stock_qty + OLD.quantity WHERE id = OLD.product_id;
            UPDATE products SET stock_qty = stock_qty - NEW.quantity WHERE id = NEW.product_id;
        END IF;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE products SET stock_qty = stock_qty + OLD.quantity WHERE id = OLD.product_id;
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
\$\$;

DROP TRIGGER IF EXISTS trg_session_used_items_stock_insert ON session_used_items;
DROP TRIGGER IF EXISTS trg_session_used_items_stock_update ON session_used_items;
DROP TRIGGER IF EXISTS trg_session_used_items_stock_delete ON session_used_items;

CREATE TRIGGER trg_session_used_items_stock_insert
AFTER INSERT ON session_used_items
FOR EACH ROW
EXECUTE FUNCTION fn_sync_stock_on_session_used_items();

CREATE TRIGGER trg_session_used_items_stock_update
AFTER UPDATE ON session_used_items
FOR EACH ROW
EXECUTE FUNCTION fn_sync_stock_on_session_used_items();

CREATE TRIGGER trg_session_used_items_stock_delete
AFTER DELETE ON session_used_items
FOR EACH ROW
EXECUTE FUNCTION fn_sync_stock_on_session_used_items();

-- Add unit_price column if not exists
ALTER TABLE products ADD COLUMN IF NOT EXISTS unit_price DECIMAL(12,2) DEFAULT 0;
    ";
    $db->exec($sql);
    echo "Schema updated successfully!\n";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>