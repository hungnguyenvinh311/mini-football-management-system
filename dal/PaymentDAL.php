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

        // 1. Get Booking and Customer Info
        $query = "SELECT 
                    b.id AS booking_id,
                    b.deposit_amount,
                    b.total_expected_amount,
                    c.id AS customer_id,
                    c.name AS customer_name,
                    c.phone AS customer_phone
                  FROM bookings b
                  JOIN customers c ON b.customer_id = c.id
                  WHERE b.id = :booking_id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['booking_id' => $bookingId]);
        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookingData) {
            return null; // Or throw an exception
        }

        $response['customer'] = [
            'id' => $bookingData['customer_id'],
            'name' => $bookingData['customer_name'],
            'phone' => $bookingData['customer_phone']
        ];
        $response['booking'] = [
            'id' => $bookingData['booking_id'],
            'deposit_amount' => $bookingData['deposit_amount'],
            'total_expected_amount' => $bookingData['total_expected_amount']
        ];

        // 2. Get Rental Sessions linked to the booking
        $query = "SELECT
                    rs.id AS session_id,
                    rs.play_date,
                    rs.reception_time,
                    rs.return_time,
                    rs.rent_amount,
                    c.name AS court_name,
                    bc.price_per_session
                  FROM rental_sessions rs
                  JOIN booking_courts bc ON rs.booking_court_id = bc.id
                  JOIN courts c ON bc.court_id = c.id
                  WHERE bc.booking_id = :booking_id
                  ORDER BY rs.play_date, rs.reception_time";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['booking_id' => $bookingId]);
        $response['rental_sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get Used Items from all those sessions
        // Create a list of session IDs to use in the IN clause
        $sessionIds = array_column($response['rental_sessions'], 'session_id');

        if (!empty($sessionIds)) {
            $query = "SELECT 
                        sui.id AS item_id,
                        sui.session_id,
                        sui.quantity,
                        sui.unit_price,
                        sui.total_amount,
                        p.code AS product_code,
                        p.name AS product_name
                      FROM session_used_items sui
                      JOIN products p ON sui.product_id = p.id
                      WHERE sui.session_id IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($sessionIds);
            $response['used_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $response;
    }

    public function confirmBookingPayment($bookingId, $finalAmount) {
        $query = "UPDATE bookings 
                  SET status = 'paid', total_amount = :total_amount 
                  WHERE id = :booking_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'total_amount' => $finalAmount,
            'booking_id' => $bookingId
        ]);
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
}
?>