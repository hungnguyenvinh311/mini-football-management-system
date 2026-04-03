<?php
class BookingDAL {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    public function createBooking($customerId, $userId, $startDate, $endDate, $expectedAmount, $deposit) {
        $query = "INSERT INTO bookings (customer_id, user_id, start_date, end_date, total_expected_amount, deposit_amount) 
                  VALUES (:customer_id, :user_id, :start_date, :end_date, :total, :deposit) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'customer_id' => $customerId, 'user_id' => $userId, 
            'start_date' => $startDate, 'end_date' => $endDate, 
            'total' => $expectedAmount, 'deposit' => $deposit
        ]);
        return $stmt->fetchColumn();
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
        $query = "SELECT b.id AS booking_id, b.start_date, b.end_date, b.total_expected_amount, b.deposit_amount, b.status,
                         bc.id AS booking_court_id, bc.time_slot, bc.price_per_session,
                         c.id AS court_id, c.name AS court_name
                  FROM bookings b
                  JOIN booking_courts bc ON bc.booking_id = b.id
                  JOIN courts c ON c.id = bc.court_id
                  WHERE b.customer_id = :customer_id AND b.status != 'completed'
                  ORDER BY b.start_date DESC";
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