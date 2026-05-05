-- =====================================================
-- BẢNG HỖ TRỢ GHÉP SÂN (COURT MAPPINGS)
-- =====================================================
CREATE TABLE IF NOT EXISTS court_mappings (
    id SERIAL PRIMARY KEY,
    parent_court_id INT REFERENCES courts(id),
    child_court_id INT REFERENCES courts(id),
    UNIQUE(parent_court_id, child_court_id)
);

-- =====================================================
-- 8.1: Tự động sinh ra lịch chơi cho tháng/quý
-- =====================================================
CREATE OR REPLACE PROCEDURE sp_generate_monthly_sessions(
    p_booking_id INT,
    p_day_of_week INT -- 0=Sunday, 1=Monday... 6=Saturday
)
LANGUAGE plpgsql AS $$
DECLARE
    v_start_date DATE;
    v_end_date DATE;
    v_curr_date DATE;
    v_bc RECORD;
BEGIN
    -- Lấy thời hạn của hợp đồng tổng
    SELECT start_date, end_date INTO v_start_date, v_end_date
    FROM bookings WHERE id = p_booking_id;

    -- Lặp qua từng sân trong hợp đồng
    FOR v_bc IN SELECT * FROM booking_courts WHERE booking_id = p_booking_id LOOP
        v_curr_date := v_start_date;
        -- Lặp qua từng ngày từ start_date đến end_date
        WHILE v_curr_date <= v_end_date LOOP
            -- Nếu đúng thứ cần tìm (p_day_of_week) thì tạo ca
            IF EXTRACT(DOW FROM v_curr_date) = p_day_of_week THEN
                INSERT INTO rental_sessions (booking_court_id, play_date, rent_amount, payment_status)
                VALUES (v_bc.id, v_curr_date, v_bc.price_per_session, 'unpaid');
            END IF;
            v_curr_date := v_curr_date + 1;
        END LOOP;
    END LOOP;
END;
$$;

-- =====================================================
-- 8.2: Gom hóa đơn thanh toán (Tiền sân + Tiền phạt + Tiền nước)
-- =====================================================
CREATE OR REPLACE FUNCTION fn_get_consolidated_invoice(p_session_id INT)
RETURNS TABLE (
    session_id INT,
    base_rent_amount DECIMAL,
    total_service_amount DECIMAL,
    grand_total DECIMAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        r.id AS session_id,
        r.rent_amount AS base_rent_amount,
        COALESCE(SUM(s.total_amount), 0) AS total_service_amount,
        (r.rent_amount + COALESCE(SUM(s.total_amount), 0)) AS grand_total
    FROM rental_sessions r
    LEFT JOIN session_used_items s ON r.id = s.session_id
    WHERE r.id = p_session_id
    GROUP BY r.id, r.rent_amount;
END;
$$ LANGUAGE plpgsql;

-- =====================================================
-- 8.3: Tìm sân trống theo khung giờ & ngày (Xử lý ghép sân)
-- =====================================================
CREATE OR REPLACE FUNCTION fn_find_available_courts(
    p_play_date DATE,
    p_time_slot VARCHAR(50),
    p_court_type INT -- 1: Sân 5, 2: Sân 7, 3: Sân 11
)
RETURNS TABLE (court_id INT, court_name VARCHAR(50)) AS $$
BEGIN
    RETURN QUERY
    SELECT c.id, c.name
    FROM courts c
    WHERE c.court_type = p_court_type
    AND c.status = 'active'
    AND c.id NOT IN (
        -- Lấy các sân ĐANG BỊ CHIẾM trong khung giờ đó
        SELECT bc.court_id
        FROM rental_sessions rs
        JOIN booking_courts bc ON rs.booking_court_id = bc.id
        WHERE rs.play_date = p_play_date AND bc.time_slot = p_time_slot
        UNION
        -- Nếu Sân Lớn (Cha) bị đặt -> Sân Nhỏ (Con) mất lượt
        SELECT cm.child_court_id
        FROM court_mappings cm
        JOIN booking_courts bc ON cm.parent_court_id = bc.court_id
        JOIN rental_sessions rs ON bc.id = rs.booking_court_id
        WHERE rs.play_date = p_play_date AND bc.time_slot = p_time_slot
        UNION
        -- Nếu Sân Nhỏ (Con) bị đặt -> Sân Lớn (Cha) mất lượt
        SELECT cm.parent_court_id
        FROM court_mappings cm
        JOIN booking_courts bc ON cm.child_court_id = bc.court_id
        JOIN rental_sessions rs ON bc.id = rs.booking_court_id
        WHERE rs.play_date = p_play_date AND bc.time_slot = p_time_slot
    );
END;
$$ LANGUAGE plpgsql;

-- =====================================================
-- 9.1: Trigger - Khách dùng đồ -> Kho tự trừ
-- =====================================================
CREATE OR REPLACE FUNCTION trg_deduct_stock()
RETURNS TRIGGER AS $$
BEGIN
    -- Trừ số lượng tồn kho
    UPDATE products
    SET stock_qty = stock_qty - NEW.quantity
    WHERE id = NEW.product_id;

    -- Kiểm tra nếu âm kho thì hủy giao dịch
    IF (SELECT stock_qty FROM products WHERE id = NEW.product_id) < 0 THEN
        RAISE EXCEPTION 'Không đủ tồn kho cho sản phẩm ID: %', NEW.product_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_deduct_stock ON session_used_items;
CREATE TRIGGER trigger_deduct_stock
AFTER INSERT ON session_used_items
FOR EACH ROW EXECUTE FUNCTION trg_deduct_stock();

-- =====================================================
-- 9.2: Trigger - Trả sân muộn -> Tự cộng tiền phạt
-- =====================================================
CREATE OR REPLACE FUNCTION trg_calculate_late_fee()
RETURNS TRIGGER AS $$
DECLARE
    v_time_slot VARCHAR(50);
    v_expected_end_time TIME;
    v_late_minutes INT;
    v_penalty_per_minute DECIMAL := 1000; -- Mức phạt: 1000đ/phút
BEGIN
    -- Chỉ tính toán khi nhân viên cập nhật giờ trả sân (return_time)
    IF NEW.return_time IS NOT NULL AND OLD.return_time IS DISTINCT FROM NEW.return_time THEN
        -- Lấy khung giờ gốc từ booking_courts
        SELECT time_slot INTO v_time_slot
        FROM booking_courts WHERE id = NEW.booking_court_id;

        -- Cắt chuỗi để lấy giờ kết thúc (VD: "18:30-20:00" -> "20:00")
        v_expected_end_time := split_part(v_time_slot, '-', 2)::TIME;

        -- Tính số phút muộn
        IF NEW.return_time > v_expected_end_time THEN
            v_late_minutes := EXTRACT(EPOCH FROM (NEW.return_time - v_expected_end_time))/60;
            -- Cộng dồn tiền phạt vào giá thuê gốc
            NEW.rent_amount := NEW.rent_amount + (v_late_minutes * v_penalty_per_minute);
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_calculate_late_fee ON rental_sessions;
CREATE TRIGGER trigger_calculate_late_fee
BEFORE UPDATE ON rental_sessions
FOR EACH ROW EXECUTE FUNCTION trg_calculate_late_fee();

-- =====================================================
-- 9.3: Trigger - Ngăn chặn đặt trùng sân (Bao gồm cả sân ghép)
-- =====================================================
CREATE OR REPLACE FUNCTION trg_prevent_court_overlap()
RETURNS TRIGGER AS $$
DECLARE
    overlap_count INT;
BEGIN
    SELECT COUNT(*) INTO overlap_count
    FROM booking_courts bc
    JOIN bookings b ON bc.booking_id = b.id
    WHERE b.status IN ('pending', 'active')
    AND bc.time_slot = NEW.time_slot
    AND (
        -- 1. Trùng chính sân đó
        bc.court_id = NEW.court_id
        -- 2. Trùng sân con
        OR bc.court_id IN (SELECT child_court_id FROM court_mappings WHERE parent_court_id = NEW.court_id)
        -- 3. Trùng sân cha
        OR bc.court_id IN (SELECT parent_court_id FROM court_mappings WHERE child_court_id = NEW.court_id)
    );

    IF overlap_count > 0 THEN
        RAISE EXCEPTION 'Khung giờ % cho sân này (hoặc sân ghép liên quan) đã bị đặt!', NEW.time_slot;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_prevent_overlap ON booking_courts;
CREATE TRIGGER trigger_prevent_overlap
BEFORE INSERT ON booking_courts
FOR EACH ROW EXECUTE FUNCTION trg_prevent_court_overlap();
