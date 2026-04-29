-- 1. Bảng Nhân viên / Quản lý
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL -- 'staff' hoặc 'manager'
);

-- 2. Bảng Khách hàng
CREATE TABLE customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL
);

-- 3. Bảng Sân bóng (Hỗ trợ ghép sân: type có thể là 1, 2, hoặc 4 sân mini)
CREATE TABLE courts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    court_type INT NOT NULL DEFAULT 1, -- 1: Sân 5, 2: Sân 7, 4: Sân 11
    status VARCHAR(20) DEFAULT 'active'
);

-- 4. Bảng Đặt sân (Booking - Hợp đồng tổng)
CREATE TABLE bookings (
    id SERIAL PRIMARY KEY,
    customer_id INT REFERENCES customers(id),
    user_id INT REFERENCES users(id), -- Nhân viên tạo
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_expected_amount DECIMAL(12,2) DEFAULT 0,
    deposit_amount DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending', -- pending, active, completed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS total_amount DECIMAL(12,2) DEFAULT 0;

-- 5. Chi tiết sân được đặt trong Booking
CREATE TABLE booking_courts (
    id SERIAL PRIMARY KEY,
    booking_id INT REFERENCES bookings(id) ON DELETE CASCADE,
    court_id INT REFERENCES courts(id),
    time_slot VARCHAR(50) NOT NULL, -- vd: "17:00-18:30"
    price_per_session DECIMAL(12,2) NOT NULL
);

-- 6. Từng Ca Đá Thực Tế (Phục vụ Module 2 & 3)
CREATE TABLE rental_sessions (
    id SERIAL PRIMARY KEY,
    booking_court_id INT REFERENCES booking_courts(id),
    play_date DATE NOT NULL,
    reception_time TIME,
    return_time TIME,
    rent_amount DECIMAL(12,2), -- Có thể tăng nếu trả trễ
    payment_status VARCHAR(20) DEFAULT 'unpaid'
);

-- 7. Kho hàng & Dịch vụ (Đồ ăn, thức uống)
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    stock_qty INT DEFAULT 0,
    unit_price DECIMAL(12,2) DEFAULT 0
);

-- 8. Dịch vụ phát sinh trong ca đá
CREATE TABLE session_used_items (
    id SERIAL PRIMARY KEY,
    session_id INT REFERENCES rental_sessions(id),
    product_id INT REFERENCES products(id),
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL
);

-- 8a. VIEW và PROCEDURE hỗ trợ Module Update Session và Payment

-- View danh sách booking chưa thanh toán (dùng cho Module Payment)
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

-- View chi tiết thuê sân cho một booking (dùng cho Invoice / Payment)
CREATE VIEW vw_invoice_sessions AS
SELECT rs.id AS session_id,
       bc.booking_id,
       c.name AS court_name,
       rs.play_date,
       rs.reception_time,
       rs.return_time,
       rs.rent_amount,
       bc.price_per_session,
       rs.payment_status
FROM rental_sessions rs
JOIN booking_courts bc ON rs.booking_court_id = bc.id
JOIN courts c ON bc.court_id = c.id;

-- View dịch vụ phát sinh cho một booking (dùng cho Invoice / Payment)
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

-- View tổng quan hóa đơn theo booking
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

-- Stored procedure tạo ca thuê và thêm dịch vụ trong cùng một lần gọi
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
AS $$
DECLARE
    item RECORD;
BEGIN
    UPDATE rental_sessions
    SET reception_time = p_reception_time,
        return_time = p_return_time,
        rent_amount = p_rent_amount
    WHERE booking_court_id = p_booking_court_id 
      AND play_date = p_play_date
      AND payment_status = 'unpaid'
    RETURNING id INTO o_session_id;
    IF o_session_id IS NULL THEN
        RAISE EXCEPTION 'Không tìm thấy ca đá nào đang chờ nhận sân trong ngày này!';
    END IF;
    DELETE FROM session_used_items WHERE session_id = o_session_id;
    IF p_used_items IS NOT NULL THEN
        FOR item IN SELECT * FROM jsonb_to_recordset(p_used_items) AS x(product_id INT, unit_price DECIMAL, quantity INT) LOOP
            INSERT INTO session_used_items (session_id, product_id, unit_price, quantity, total_amount)
            VALUES (o_session_id, item.product_id, item.unit_price, item.quantity, item.unit_price * item.quantity);
        END LOOP;
    END IF;
END;
$$;

-- Stored procedure xác nhận thanh toán booking
CREATE PROCEDURE sp_confirm_booking_payment(
    IN p_booking_id INT,
    IN p_total_amount DECIMAL(12,2)
)
LANGUAGE plpgsql
AS $$
BEGIN
    UPDATE bookings
    SET status = 'paid', total_amount = p_total_amount
    WHERE id = p_booking_id;
END;
$$;

-- Trigger function điều chỉnh tồn kho tự động khi session_used_items thay đổi
CREATE FUNCTION fn_sync_stock_on_session_used_items() RETURNS TRIGGER LANGUAGE plpgsql AS $$
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
$$;

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

-- 9. Nhà cung cấp & Nhập kho (Phục vụ Module 4)
CREATE TABLE providers (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT
);

CREATE TABLE import_invoices (
    id SERIAL PRIMARY KEY,
    provider_id INT REFERENCES providers(id),
    user_id INT REFERENCES users(id),
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(12,2) NOT NULL
);

CREATE TABLE import_details (
    id SERIAL PRIMARY KEY,
    import_invoice_id INT REFERENCES import_invoices(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id),
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL
);


INSERT INTO courts (name, court_type, status) VALUES 
-- Danh sách Sân 7 người (court_type = 2)
('Sân 7 - 01', 2, 'active'),
('Sân 7 - 02', 2, 'unactive'),
('Sân 7 - 03', 2, 'active'),
('Sân 7 - 04', 2, 'maintenance'),
('Sân 7 - 05', 2, 'unactive'),
('Sân 7 - 06', 2, 'active'),
('Sân 7 - 07', 2, 'unactive'),
('Sân 7 - 08', 2, 'maintenance'),
('Sân 7 - 09', 2, 'active'),
('Sân 7 - 10', 2, 'unactive'),

-- Danh sách Sân 11 người (court_type = 3)
('Sân 11 - 01', 3, 'active'),
('Sân 11 - 02', 3, 'unactive'),
('Sân 11 - 03', 3, 'maintenance'),
('Sân 11 - 04', 3, 'active'),
('Sân 11 - 05', 3, 'unactive'),
('Sân 11 - 06', 3, 'active'),
('Sân 11 - 07', 3, 'unactive'),
('Sân 11 - 08', 3, 'maintenance'),
('Sân 11 - 09', 3, 'active'),
('Sân 11 - 10', 3, 'unactive');

INSERT INTO import_invoices (provider_id, user_id, import_date, total_amount) 
VALUES (1, 4, '2026-03-25 09:30:00', 1500000);

INSERT INTO import_details (import_invoice_id, product_id, unit_price, quantity, amount) 
VALUES 
(1, 1, 12000, 50, 600000),  
(1, 2, 10000, 90, 900000);


INSERT INTO users (username, password_hash, full_name, role) VALUES 
('admin', '$2y$10$YCo96ueS.7XW5u7G.6fQreX6H6G6G6G6G6G6G6G6G6G6G6G6G6G6', 'Trần Văn Quản Lý', 'manager'),
('staff', '$2y$10$YCo96ueS.7XW5u7G.6fQreX6H6G6G6G6G6G6G6G6G6G6G6G6G6G6', 'Nguyễn Văn Nhân Viên', 'staff');

---------------------------------------------------------
-- DỮ LIỆU ĐỂ TEST MODULE 2 (UPDATE SESSION) & 3 (PAYMENT)
---------------------------------------------------------

-- 6. TẠO 1 BOOKING ĐANG HOẠT ĐỘNG (Khách Hải Long đặt từ 20/03 đến 20/04/2026)
INSERT INTO bookings (customer_id, user_id, start_date, end_date, total_expected_amount, deposit_amount, status) 
VALUES (1, 3, '2026-03-20', '2026-04-20', 4000000, 500000, 'active');

-- 7. CHI TIẾT SÂN ĐẶT (Sân 1, khung giờ 18:30-20:00)
INSERT INTO booking_courts (booking_id, court_id, time_slot, price_per_session) 
VALUES (5, 1, '18:30-20:00', 400000);

-- 8. GIẢ LẬP 1 CA ĐÁ ĐÃ XONG NHƯNG CHƯA THANH TOÁN (Để test Module 2)
-- Khách đã đá xong ngày 24/03/2026
INSERT INTO rental_sessions (booking_court_id, play_date, reception_time, return_time, rent_amount, payment_status) 
VALUES (3, '2026-03-24', '18:30:00', '20:05:00', 450000, 'unpaid'); -- Trả trễ 5p nên tính 450k

-- 9. GIẢ LẬP ĐỒ UỐNG KHÁCH ĐÃ DÙNG TRONG CA ĐÓ
INSERT INTO session_used_items (session_id, product_id, unit_price, quantity, total_amount) 
VALUES 
(2, 1, 15000, 4, 60000), -- 4 Sting
(2, 2, 10000, 2, 20000); -- 2 Nước suối