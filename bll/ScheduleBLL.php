<?php
require_once '../dal/ScheduleDAL.php';

class ScheduleBLL {
    private $db;
    private $scheduleDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->scheduleDAL = new ScheduleDAL($db);
    }

    // =====================================================
    // 8.1: Sinh lịch chơi tự động cho hợp đồng dài hạn
    // =====================================================
    public function generateMonthlySessions($bookingId, $dayOfWeek) {
        try {
            // Validate input
            if (!$bookingId || !is_numeric($bookingId)) {
                return ["status" => "error", "message" => "Booking ID không hợp lệ."];
            }
            if (!is_numeric($dayOfWeek) || $dayOfWeek < 0 || $dayOfWeek > 6) {
                return ["status" => "error", "message" => "Thứ trong tuần phải từ 0 (CN) đến 6 (T7)."];
            }

            $this->scheduleDAL->generateMonthlySessions($bookingId, $dayOfWeek);

            // Lấy danh sách sessions vừa tạo để hiển thị
            $sessions = $this->scheduleDAL->getSessionsByBooking($bookingId);

            return [
                "status" => "success",
                "message" => "Đã sinh lịch chơi thành công!",
                "data" => [
                    "booking_id" => $bookingId,
                    "day_of_week" => $dayOfWeek,
                    "total_sessions" => count($sessions),
                    "sessions" => $sessions
                ]
            ];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // =====================================================
    // 8.2: Lấy hóa đơn gom cho 1 ca đá
    // =====================================================
    public function getConsolidatedInvoice($sessionId) {
        try {
            if (!$sessionId || !is_numeric($sessionId)) {
                return ["status" => "error", "message" => "Session ID không hợp lệ."];
            }

            $invoice = $this->scheduleDAL->getConsolidatedInvoice($sessionId);

            if (!$invoice) {
                return ["status" => "error", "message" => "Không tìm thấy ca đá với ID: $sessionId"];
            }

            return [
                "status" => "success",
                "data" => $invoice
            ];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // =====================================================
    // 8.3: Tìm sân trống (có xử lý sân ghép)
    // =====================================================
    public function findAvailableCourts($playDate, $timeSlot, $courtType) {
        try {
            if (empty($playDate) || empty($timeSlot) || empty($courtType)) {
                return ["status" => "error", "message" => "Vui lòng nhập đủ ngày, khung giờ và loại sân."];
            }

            $courts = $this->scheduleDAL->findAvailableCourts($playDate, $timeSlot, $courtType);

            return [
                "status" => "success",
                "data" => $courts,
                "message" => count($courts) . " sân trống được tìm thấy."
            ];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // =====================================================
    // Lấy danh sách booking đang hoạt động
    // =====================================================
    public function getActiveBookings() {
        try {
            $bookings = $this->scheduleDAL->getActiveBookings();
            return ["status" => "success", "data" => $bookings];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // =====================================================
    // Lấy danh sách sessions theo booking
    // =====================================================
    public function getSessionsByBooking($bookingId) {
        try {
            if (!$bookingId) {
                return ["status" => "error", "message" => "Booking ID không hợp lệ."];
            }
            $sessions = $this->scheduleDAL->getSessionsByBooking($bookingId);
            return ["status" => "success", "data" => $sessions];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // =====================================================
    // Quản lý Court Mappings
    // =====================================================
    public function getCourtMappings() {
        try {
            $mappings = $this->scheduleDAL->getCourtMappings();
            return ["status" => "success", "data" => $mappings];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function addCourtMapping($parentId, $childId) {
        try {
            if (!$parentId || !$childId) {
                return ["status" => "error", "message" => "Vui lòng chọn cả sân cha và sân con."];
            }
            if ($parentId == $childId) {
                return ["status" => "error", "message" => "Sân cha và sân con không được trùng nhau."];
            }
            $this->scheduleDAL->addCourtMapping($parentId, $childId);
            return ["status" => "success", "message" => "Thêm liên kết sân ghép thành công!"];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function deleteCourtMapping($mappingId) {
        try {
            if (!$mappingId) {
                return ["status" => "error", "message" => "Mapping ID không hợp lệ."];
            }
            $this->scheduleDAL->deleteCourtMapping($mappingId);
            return ["status" => "success", "message" => "Đã xóa liên kết sân ghép."];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function getAllCourts() {
        try {
            $courts = $this->scheduleDAL->getAllCourts();
            return ["status" => "success", "data" => $courts];
        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
?>
