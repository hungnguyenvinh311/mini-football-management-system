<?php
class CourtDAL {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Lấy danh sách sân trống theo khung giờ và loại sân
    public function getAvailableCourts($date, $time_slot, $court_type) {
        // Query kiểm tra xem sân có nằm trong booking_courts bị trùng ngày không
        $query = "
            SELECT c.id, c.name, c.court_type 
            FROM courts c
            WHERE c.court_type = :court_type 
            AND c.status = 'active'
            AND c.id NOT IN (
                SELECT bc.court_id 
                FROM booking_courts bc
                JOIN bookings b ON bc.booking_id = b.id
                WHERE bc.time_slot = :time_slot
                AND :date BETWEEN b.start_date AND b.end_date
            )
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':court_type', $court_type);
        $stmt->bindParam(':time_slot', $time_slot);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>