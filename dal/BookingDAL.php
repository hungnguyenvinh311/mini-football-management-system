<?php
class BookingDAL {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    public function callDatSanProcedure($userId, $name, $phone, $courtId, $timeSlot, $totalAmount, $unitPrice, $deposit, $startDate, $endDate) {
    try {
        // Có 11 dấu chấm hỏi (10 IN + 1 OUT)
        $query = "CALL DatSan(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->execute([
            $userId, $name, $phone, $courtId, $timeSlot, 
            $totalAmount, $unitPrice, $deposit, $startDate, $endDate,
            null // Vị trí của biến OUT
        ]);

        // Lấy giá trị ID trả về từ Procedure
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['p_booking_id'] : false;
    } catch (PDOException $e) {
        throw new Exception($e->getMessage());
    }
}

    public function addBookingCourt($bookingId, $courtId, $timeSlot, $pricePerSession) {
        $query = "INSERT INTO booking_courts (booking_id, court_id, time_slot, price_per_session) 
                  VALUES (:booking_id, :court_id, :time_slot, :price)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'booking_id' => $bookingId, 'court_id' => $courtId, 
            'time_slot' => $timeSlot, 'price' => $pricePerSession
        ]);
    }

    // Lấy danh sách booking và ca đá chưa hoàn thành theo khách hàng
    public function getBookingsByCustomer($customerId) {
    // Sửa lại Query để lấy ra từng Session cụ thể thay vì chỉ lấy Booking tổng
    $query = "SELECT b.id AS booking_id, rs.play_date, rs.payment_status,
                     bc.id AS booking_court_id, bc.time_slot,
                     c.name AS court_name, rs.id AS session_id
              FROM bookings b
              JOIN booking_courts bc ON bc.booking_id = b.id
              JOIN rental_sessions rs ON rs.booking_court_id = bc.id -- QUAN TRỌNG: Join thêm sessions
              JOIN courts c ON c.id = bc.court_id
              WHERE b.customer_id = :customer_id AND b.status != 'paid'
              ORDER BY rs.play_date ASC";
    $stmt = $this->conn->prepare($query);
    $stmt->execute(['customer_id' => $customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // Tìm các Booking chưa thanh toán của 1 khách hàng (Cho Checkout)
    public function getUnpaidBookingsByCustomer($customerId) {
        $query = "SELECT * FROM bookings WHERE customer_id = :customer_id AND status != 'completed'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['customer_id' => $customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Đổi trạng thái thanh toán
    public function markAsCompleted($bookingId) {
        $query = "UPDATE bookings SET status = 'completed' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $bookingId]);
    }
}
?>