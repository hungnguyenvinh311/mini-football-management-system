<?php
require_once '../dal/BookingDAL.php';
require_once '../dal/CustomerDAL.php';
require_once '../dal/CourtDAL.php';
require_once '../dal/ProductDAL.php';

class BookingBLL {
    private $db;
    private $bookingDAL;
    private $customerDAL;
    private $courtDAL;
    private $productDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->bookingDAL = new BookingDAL($db);
        $this->customerDAL = new CustomerDAL($db);
        $this->courtDAL = new CourtDAL($db);
        $this->productDAL = new ProductDAL($db);
    }

    public function createNewBooking($data) {
    try {
        // Procedure DatSan trong SQL đã tự động bao bọc Transaction 
        // và xử lý logic tạo khách hàng, tạo booking, tạo ca thuê.
        // BLL giờ chỉ cần gọi đúng 1 hàm từ DAL:
        
        $this->bookingDAL->callDatSanProcedure(
            $data['user_id'],
            $data['customer_name'],
            $data['customer_phone'],
            $data['court_id'],
            $data['time_slot'],
            $data['price_per_session'], // Đây chính là p_amount trong SQL
            $data['deposit']
        );

        return ["status" => "success", "message" => "Đặt sân thành công!"];

    } catch (Exception $e) {
        // Bắt lỗi từ Procedure trả ra (ví dụ: "Sân ... đã có người đặt")
        return ["status" => "error", "message" => "Lỗi đặt sân: " . $e->getMessage()];
    }
}
    public function getCustomerBookings($customerId) {
        return $this->bookingDAL->getBookingsByCustomer($customerId);
    }

    public function searchAvailableCourts($date, $timeSlot, $courtType) {
        // Logic nghiệp vụ: Có thể thêm validation cho date, timeSlot, etc.
        return $this->courtDAL->getAvailableCourts($date, $timeSlot, $courtType);
    }

    public function searchCustomers($keyword) {
        // Logic nghiệp vụ: Validate keyword, log search, etc.
        return $this->customerDAL->searchByNameOrPhone($keyword);
    }

    public function searchItems($keyword) {
        // Logic nghiệp vụ: Có thể thêm filter theo stock, etc.
        return $this->productDAL->searchByName($keyword);
    }

    public function createCustomer($name, $phone) {
        // Logic nghiệp vụ: Validate dữ liệu, kiểm tra trùng lặp
        if (empty($name) || empty($phone)) {
            return ["status" => "error", "message" => "Vui lòng nhập đủ tên và SĐT"];
        }
        $exists = $this->customerDAL->searchByNameOrPhone($phone);
        if (!empty($exists)) {
            return ["status" => "error", "message" => "Số điện thoại này đã tồn tại trong hệ thống!"];
        }
        $newId = $this->customerDAL->createCustomer($name, $phone);
        return ["status" => "success", "data" => ["id" => $newId, "name" => $name, "phone" => $phone]];
    }
}
?>