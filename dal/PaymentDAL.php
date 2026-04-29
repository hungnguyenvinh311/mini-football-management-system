<?php
class PaymentDAL {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getInvoiceDetails($bookingId) {
        $response = [
            'customer' => null,
            'booking' => null,
            'rental_sessions' => [],
            'used_items' => []
        ];

        $query = "SELECT * FROM vw_booking_invoice_summary WHERE booking_id = :booking_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['booking_id' => $bookingId]);
        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookingData) {
            return null;
        }

        $response['customer'] = [
            'id' => $bookingData['customer_id'],
            'name' => $bookingData['customer_name'],
            'phone' => $bookingData['customer_phone']
        ];
        $response['booking'] = [
            'id' => $bookingData['booking_id'],
            'deposit_amount' => $bookingData['deposit_amount'],
            'total_expected_amount' => $bookingData['total_expected_amount'],
            'total_amount' => $bookingData['total_amount']
        ];

        $query = "SELECT * FROM vw_invoice_sessions WHERE booking_id = :booking_id ORDER BY play_date, reception_time";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['booking_id' => $bookingId]);
        $response['rental_sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT * FROM vw_invoice_items WHERE booking_id = :booking_id ORDER BY session_id, item_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['booking_id' => $bookingId]);
        $response['used_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $response;
    }

    public function getCustomerUnpaidBookings($customerId) {
        $query = "SELECT * FROM vw_unpaid_bookings WHERE customer_id = :customer_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['customer_id' => $customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSessionUsedItemQuantity($itemId, $newQuantity, $newTotalAmount) {
        $query = "UPDATE session_used_items SET quantity = :qty, total_amount = :total WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['qty' => $newQuantity, 'total' => $newTotalAmount, 'id' => $itemId]);
    }
    
    public function deleteSessionUsedItem($itemId) {
        $query = "DELETE FROM session_used_items WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $itemId]);
    }
    
    // Gọi Procedure ThanhToan của SQL
    public function callThanhToanProcedure($sessionId) {
        try {
            $query = "CALL ThanhToan(:session_id)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute(['session_id' => $sessionId]);
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi thanh toán ca đá $sessionId: " . $e->getMessage());
        }
    }

    // Cập nhật trạng thái của Booking tổng thành 'completed'
    public function completeBooking($bookingId) {
        $query = "UPDATE bookings SET status = 'completed' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $bookingId]);
    }
} // <--- Dấu ngoặc đóng class quan trọng mà bạn bị thiếu
?>