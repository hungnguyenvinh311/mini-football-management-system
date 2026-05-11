<?php
class RentalSessionDAL {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    public function getSessionByCourtAndDate($bookingCourtId, $playDate) {
    // Tìm đúng ca đá của sân đó VÀ ngày đó
    $query = "SELECT id FROM rental_sessions WHERE booking_court_id = :id AND play_date = :date LIMIT 1";
    $stmt = $this->conn->prepare($query);
    $stmt->execute(['id' => $bookingCourtId, 'date' => $playDate]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
    // 1. HÀM MỚI: Tìm xem ca đá này đã tồn tại trong DB chưa
    public function getSessionByBookingCourtId($bookingCourtId) {
        $query = "SELECT id FROM rental_sessions WHERE booking_court_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $bookingCourtId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. HÀM MỚI: Cập nhật thông tin ca đá nếu đã có sẵn
    public function updateSession($id, $playDate, $receptionTime, $returnTime, $rentAmount) {
        $query = "UPDATE rental_sessions 
                  SET play_date = :d, 
                      reception_time = :rec, 
                      return_time = :ret, 
                      rent_amount = :amt 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'd'   => $playDate,
            'rec' => $receptionTime,
            'ret' => $returnTime,
            'amt' => $rentAmount,
            'id'  => $id
        ]);
    }

    // 3. HÀM MỚI: Xóa trắng danh sách nước uống cũ để chèn lại cái mới (tránh trùng lặp)
    public function clearOldItems($sessionId) {
        $query = "DELETE FROM session_used_items WHERE session_id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $sessionId]);
    }

    // --- GIỮ NGUYÊN CÁC HÀM CŨ CỦA BẠN ---

    public function createSessionWithItems($bookingCourtId, $playDate, $receptionTime, $returnTime, $rentAmount, $usedItemsJson) {
        $query = "CALL sp_create_rental_session(
            CAST(:bc_id AS INT),
            CAST(:play_date AS DATE),
            CAST(:reception_time AS TIME),
            CAST(:return_time AS TIME),
            CAST(:rent_amount AS NUMERIC),
            CAST(:used_items AS JSONB),
            NULL
        )";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'bc_id' => $bookingCourtId,
            'play_date' => $playDate,
            'reception_time' => $receptionTime,
            'return_time' => $returnTime,
            'rent_amount' => $rentAmount,
            'used_items' => $usedItemsJson
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['o_session_id'] : false;
    }

    public function updateCheckout($sessionId, $returnTime, $rentAmount) {
        $query = "UPDATE rental_sessions SET return_time = :return_time, rent_amount = :rent_amount 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['return_time' => $returnTime, 'rent_amount' => $rentAmount, 'id' => $sessionId]);
    }

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