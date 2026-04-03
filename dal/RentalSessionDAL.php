<?php
class RentalSessionDAL {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    // Tạo ca đá mới khi khách nhận sân
    public function createSession($bookingCourtId, $playDate, $receptionTime) {
        $query = "INSERT INTO rental_sessions (booking_court_id, play_date, reception_time) 
                  VALUES (:bc_id, :play_date, :reception_time) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['bc_id' => $bookingCourtId, 'play_date' => $playDate, 'reception_time' => $receptionTime]);
        return $stmt->fetchColumn();
    }

    // Cập nhật giờ trả sân và tiền sân (có thể tăng do trễ giờ)
    public function updateCheckout($sessionId, $returnTime, $rentAmount) {
        $query = "UPDATE rental_sessions SET return_time = :return_time, rent_amount = :rent_amount 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['return_time' => $returnTime, 'rent_amount' => $rentAmount, 'id' => $sessionId]);
    }

    // Thêm dịch vụ (Nước/Đồ ăn) khách dùng trong ca
    public function addUsedItem($sessionId, $productId, $unitPrice, $quantity, $totalAmount) {
        $query = "INSERT INTO session_used_items (session_id, product_id, unit_price, quantity, total_amount) 
                  VALUES (:session_id, :product_id, :price, :qty, :total)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'session_id' => $sessionId, 'product_id' => $productId, 
            'price' => $unitPrice, 'qty' => $quantity, 'total' => $totalAmount
        ]);
    }
}
?>