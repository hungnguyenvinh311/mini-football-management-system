<?php
class ScheduleDAL {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // =====================================================
    // 8.1: Gọi Procedure sinh lịch chơi tự động cho hợp đồng dài hạn
    // =====================================================
    public function generateMonthlySessions($bookingId, $dayOfWeek) {
        try {
            $query = "CALL sp_generate_monthly_sessions(:booking_id, :day_of_week)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                'booking_id' => $bookingId,
                'day_of_week' => $dayOfWeek
            ]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tạo lịch: " . $e->getMessage());
        }
    }

    // =====================================================
    // 8.2: Gọi Function lấy hóa đơn gom (tiền sân + phạt + nước)
    // =====================================================
    public function getConsolidatedInvoice($sessionId) {
        try {
            $query = "SELECT * FROM fn_get_consolidated_invoice(:session_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['session_id' => $sessionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Lỗi lấy hóa đơn: " . $e->getMessage());
        }
    }

    // =====================================================
    // 8.3: Gọi Function tìm sân trống (có xử lý sân ghép)
    // =====================================================
    public function findAvailableCourts($playDate, $timeSlot, $courtType) {
        try {
            $query = "SELECT * FROM fn_find_available_courts(:play_date, :time_slot, :court_type)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                'play_date' => $playDate,
                'time_slot' => $timeSlot,
                'court_type' => $courtType
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Lỗi tìm sân: " . $e->getMessage());
        }
    }

    // =====================================================
    // Lấy danh sách booking active/pending (cho dropdown tạo lịch)
    // =====================================================
    public function getActiveBookings() {
        $query = "SELECT b.id AS booking_id, c.name AS customer_name, c.phone AS customer_phone, 
                         b.start_date, b.end_date, b.status,
                         bc.id AS booking_court_id, bc.time_slot, bc.price_per_session,
                         cr.name AS court_name
                  FROM bookings b
                  JOIN customers c ON b.customer_id = c.id
                  JOIN booking_courts bc ON bc.booking_id = b.id
                  JOIN courts cr ON cr.id = bc.court_id
                  WHERE b.status IN ('active', 'pending')
                  ORDER BY b.start_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // Lấy danh sách rental sessions theo booking (hiển thị lịch đã sinh)
    // =====================================================
    public function getSessionsByBooking($bookingId) {
        $query = "SELECT rs.id AS session_id, rs.play_date, rs.reception_time, rs.return_time,
                         rs.rent_amount, rs.payment_status,
                         cr.name AS court_name, bc.time_slot
                  FROM rental_sessions rs
                  JOIN booking_courts bc ON rs.booking_court_id = bc.id
                  JOIN courts cr ON bc.court_id = cr.id
                  WHERE bc.booking_id = :booking_id
                  ORDER BY rs.play_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['booking_id' => $bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // Quản lý Court Mappings (Sân ghép)
    // =====================================================
    public function getCourtMappings() {
        $query = "SELECT cm.id, 
                         p.name AS parent_court_name, p.id AS parent_court_id,
                         ch.name AS child_court_name, ch.id AS child_court_id
                  FROM court_mappings cm
                  JOIN courts p ON cm.parent_court_id = p.id
                  JOIN courts ch ON cm.child_court_id = ch.id
                  ORDER BY p.name, ch.name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addCourtMapping($parentCourtId, $childCourtId) {
        try {
            $query = "INSERT INTO court_mappings (parent_court_id, child_court_id) VALUES (:parent_id, :child_id)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                'parent_id' => $parentCourtId,
                'child_id' => $childCourtId
            ]);
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi thêm liên kết sân: " . $e->getMessage());
        }
    }

    public function deleteCourtMapping($mappingId) {
        $query = "DELETE FROM court_mappings WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $mappingId]);
    }

    // Lấy tất cả sân (cho dropdown)
    public function getAllCourts() {
        $query = "SELECT id, name, court_type, status FROM courts ORDER BY court_type, name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
