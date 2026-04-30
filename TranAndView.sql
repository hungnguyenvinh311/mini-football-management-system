--Transaction Đặt sân
CREATE OR REPLACE PROCEDURE DatSan(
    p_user_id INT,
    p_name TEXT,
    p_phone TEXT,
    p_court_id INT,
    p_timeslot TEXT,
    p_amount NUMERIC,
    p_deposit NUMERIC
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_customer_id INT;
    v_booking_id INT;
    v_booking_court_id INT;
BEGIN
    PERFORM 1 FROM rental_sessions rs
    JOIN booking_courts bc ON rs.booking_court_id = bc.id
    WHERE bc.court_id = p_court_id 
      AND rs.play_date = CURRENT_DATE 
      AND bc.time_slot = p_timeslot;

    IF FOUND THEN
        RAISE EXCEPTION 'Sân % đã có người đặt vào khung giờ %!', p_court_id, p_timeslot;
    END IF;
    SELECT id INTO v_customer_id FROM customers WHERE phone = p_phone LIMIT 1;

    IF v_customer_id IS NULL THEN
        INSERT INTO customers (name, phone) VALUES (p_name, p_phone)
        RETURNING id INTO v_customer_id;
    END IF;
    INSERT INTO bookings (customer_id, user_id, start_date, end_date, total_expected_amount, deposit_amount, status)
    VALUES (v_customer_id, p_user_id, CURRENT_DATE, CURRENT_DATE, p_amount, p_deposit, 'active')
    RETURNING id INTO v_booking_id;

    INSERT INTO booking_courts (booking_id, court_id, time_slot, price_per_session)
    VALUES (v_booking_id, p_court_id, p_timeslot, p_amount)
    RETURNING id INTO v_booking_court_id;

    INSERT INTO rental_sessions (booking_court_id, play_date, payment_status)
    VALUES (v_booking_court_id, CURRENT_DATE, 'unpaid');

    RAISE NOTICE 'Đặt sân thành công cho khách hàng % tại sân %!', p_name, p_court_id;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Giao dịch thất bại! Chi tiết: %', SQLERRM;
END;
$$;
;

--View Chi Tiết Phiếu
select * from vw_booking_slip_details
CREATE booking_slip_details AS
SELECT 
    b.id AS booking_id,
    
    c.name AS customer_name,
    c.phone AS customer_phone,
    
    u.full_name AS created_by_staff,
    
    b.start_date,
    b.end_date,
    
    cr.name AS court_name,
    CASE 
        WHEN cr.court_type = 1 THEN 'Sân Mini'
        WHEN cr.court_type = 2 THEN 'Sân 7 người'
        WHEN cr.court_type = 3 THEN 'Sân 11 người'
        ELSE 'Khác'
    END AS court_type_name,
    bc.time_slot,
    bc.price_per_session,
    CEIL((b.end_date - b.start_date)::numeric / 7) + 1 AS estimated_total_sessions,
    b.total_expected_amount,
    b.deposit_amount,
    (b.total_expected_amount - b.deposit_amount) AS remaining_balance

FROM bookings b
JOIN customers c ON b.customer_id = c.id
JOIN users u ON b.user_id = u.id
JOIN booking_courts bc ON b.id = bc.booking_id
JOIN courts cr ON bc.court_id = cr.id;

--View Trạng Thái Sân
CREATE OR REPLACE VIEW current_pitch_status  AS
SELECT 
    c.id AS court_id,
    c.name AS court_name,
    CASE 
        WHEN c.court_type = 1 THEN 'Sân 5'
        WHEN c.court_type = 2 THEN 'Sân 7'
        WHEN c.court_type = 3 THEN 'Sân 11'
    END AS type,
    rs.id AS session_id,
    rs.payment_status,
    bc.time_slot,
    cust.name AS customer_name,
   
    CASE 
        WHEN rs.id IS NOT NULL AND rs.payment_status = 'unpaid' THEN 'Đã Đặt'
        WHEN c.status = 'maintenance'                           THEN 'Đang sửa'
        WHEN b.id IS NOT NULL AND b.status = 'active'
             AND b.end_date >= CURRENT_DATE                     THEN 'Đã Đặt'
        ELSE 'Trống'
    END AS trang_thai
FROM courts c
LEFT JOIN booking_courts bc ON c.id = bc.court_id
LEFT JOIN rental_sessions rs ON bc.id = rs.booking_court_id 
LEFT JOIN bookings b ON bc.booking_id = b.id
LEFT JOIN customers cust ON b.customer_id = cust.id;

--Transaction Thanh Toán
CREATE OR REPLACE PROCEDURE ThanhToan(
    p_session_id INT 
)
LANGUAGE plpgsql
AS $$
BEGIN

    PERFORM id FROM rental_sessions WHERE id = p_session_id FOR UPDATE;
    PERFORM product_id FROM session_used_items WHERE session_id = p_session_id FOR UPDATE;

  
    UPDATE rental_sessions 
    SET payment_status = 'paid'
    WHERE id = p_session_id;

  
    UPDATE products AS p
	SET stock_qty = p.stock_qty - sub.total_quantity
	FROM (
	    SELECT product_id, SUM(quantity) AS total_quantity
	    FROM session_used_items
	    WHERE session_id = p_session_id
	    GROUP BY product_id
	) AS sub
	WHERE p.id = sub.product_id;
    COMMIT; 
    RAISE NOTICE 'Giao dịch thành công.';
END;
$$;
