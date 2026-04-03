<?php
// Cho phép các request từ Frontend (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");

// Khai báo các class BLL cần thiết
require_once '../config/Database.php';
require_once '../bll/BookingBLL.php';
require_once '../bll/RentalSessionBLL.php';
require_once '../bll/DashboardBLL.php';

// Khởi tạo kết nối Database
$database = new Database();
$db = $database->getConnection();

// Lấy tham số action từ URL
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ==========================================
// 1. ENDPOINT: TÌM SÂN TRỐNG
// ==========================================
if ($action === 'search_courts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $timeSlot = isset($_GET['time_slot']) ? $_GET['time_slot'] : '';
    $courtType = isset($_GET['court_type']) ? $_GET['court_type'] : '';

    $bookingBLL = new BookingBLL($db);
    $availableCourts = $bookingBLL->searchAvailableCourts($date, $timeSlot, $courtType);
    
    echo json_encode(["status" => "success", "data" => $availableCourts]);
    exit();
}

// ==========================================
// 2. ENDPOINT: TÌM KIẾM KHÁCH HÀNG (MỚI)
// ==========================================
if ($action === 'search_customers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    
    $bookingBLL = new BookingBLL($db);
    $customers = $bookingBLL->searchCustomers($keyword);
    
    echo json_encode(["status" => "success", "data" => $customers]);
    exit();
}

// ==========================================
// 2b. ENDPOINT: LẤY BOOKING TRÊN KHÁCH HÀNG
// ==========================================
if ($action === 'customer_bookings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    if (!$customerId) {
        echo json_encode(["status" => "error", "message" => "customer_id không hợp lệ"]);
        exit();
    }

    $bookingBLL = new BookingBLL($db);
    $bookings = $bookingBLL->getCustomerBookings($customerId);
    echo json_encode(["status" => "success", "data" => $bookings]);
    exit();
}

// ==========================================
// 2b2. ENDPOINT: DỮ LIỆU DASHBOARD THỐNG KÊ
// ==========================================
if ($action === 'dashboard_summary' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $dashboardBLL = new DashboardBLL($db);
    $summary = $dashboardBLL->getDashboardSummary();
    echo json_encode(["status" => "success", "data" => $summary]);
    exit();
}

// ==========================================
// 2c. ENDPOINT: TÌM KIẾM SẢN PHẨM
// ==========================================
if ($action === 'search_items' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $bookingBLL = new BookingBLL($db);
    $items = $bookingBLL->searchItems($keyword);
    echo json_encode(["status" => "success", "data" => $items]);
    exit();
}

// ==========================================
// 2d. ENDPOINT: TẠO HOẶC CẬP NHẬT PHIÊN THUÊ
// ==========================================
if ($action === 'create_rental_session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $required = ['booking_court_id', 'play_date', 'reception_time', 'return_time', 'rent_amount'];
    foreach ($required as $f) {
        if (empty($data[$f])) {
            echo json_encode(["status" => "error", "message" => "Thiếu dữ liệu: $f"]);
            exit();
        }
    }

    $sessionBLL = new RentalSessionBLL($db);
    $result = $sessionBLL->createRentalSession(
        $data['booking_court_id'],
        $data['play_date'],
        $data['reception_time'],
        $data['return_time'],
        $data['rent_amount'],
        isset($data['used_items']) ? $data['used_items'] : []
    );

    echo json_encode($result);
    exit();
}

// ==========================================
// 3. ENDPOINT: TẠO KHÁCH HÀNG MỚI (MỚI)
// ==========================================
if ($action === 'create_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Đọc dữ liệu JSON gửi từ Fetch API
    $data = json_decode(file_get_contents("php://input"), true);
    $bookingBLL = new BookingBLL($db);
    
    $result = $bookingBLL->createCustomer($data['name'], $data['phone']);
    echo json_encode($result);
    exit();
}

// ==========================================
// 4. ENDPOINT: LƯU PHIẾU ĐẶT SÂN (BOOKING)
// ==========================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Đẩy dữ liệu vào tầng Logic xử lý (BLL)
    $bookingBLL = new BookingBLL($db);
    $result = $bookingBLL->createNewBooking($data);
    
    echo json_encode($result);
    exit();
}

// ==========================================
// NẾU ACTION KHÔNG KHỚP VỚI BẤT KỲ TRƯỜNG HỢP NÀO Ở TRÊN
// ==========================================
echo json_encode(["status" => "error", "message" => "Invalid endpoint. Hãy kiểm tra lại tên action."]);
exit();
?>