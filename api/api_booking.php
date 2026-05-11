<?php
// Cho phép các request từ Frontend (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Khai báo các class BLL cần thiết
require_once '../config/Database.php';
require_once '../bll/BookingBLL.php';
require_once '../bll/RentalSessionBLL.php';
require_once '../bll/DashboardBLL.php';

// Khởi tạo kết nối Database
$database = new Database();
$db = $database->getConnection();

// Xử lý OPTIONS cho CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Lấy tham số action từ GET trước, nếu không có thì thử POST
$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
} elseif (!empty($_SERVER['REQUEST_URI'])) {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    parse_str($query, $queryParams);
    if (isset($queryParams['action'])) {
        $action = $queryParams['action'];
    }
}

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
// Sửa lại dòng này để nhận cả 2 tên action cho chắc
if ($action === 'create_rental_session' || $action === 'update_session') {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    // Đảm bảo lấy được play_date từ JSON hoặc Request
    $playDate = $data['play_date'] ?? $_REQUEST['play_date'] ?? null;
    $bookingCourtId = $data['booking_court_id'] ?? $_REQUEST['booking_court_id'] ?? null;

    if (!$playDate || !$bookingCourtId) {
        echo json_encode(["status" => "error", "message" => "Thiếu booking_court_id hoặc play_date"]);
        exit();
    }

    $sessionBLL = new RentalSessionBLL($db);
    $result = $sessionBLL->createRentalSession(
        $bookingCourtId,
        $playDate, // Truyền ngày cụ thể vào đây
        $data['reception_time'] ?? $_REQUEST['reception_time'],
        $data['return_time'] ?? $_REQUEST['return_time'],
        $data['rent_amount'] ?? $_REQUEST['rent_amount'],
        $data['used_items'] ?? []
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
// ==========================================
// 4. ENDPOINT: LƯU PHIẾU ĐẶT SÂN (BOOKING)
// ==========================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    if (!is_array($data)) {
        $data = [];
    }

    // Danh sách các trường dữ liệu BẮT BUỘC phải có để gọi Procedure DatSan
    $requiredFields = [
        'user_id', 
        'customer_name', 
        'customer_phone', 
        'court_id', 
        'time_slot', 
        'price_per_session', 
        'deposit'
    ];

    // Kiểm tra xem Frontend có gửi thiếu trường nào không
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            echo json_encode([
                "status" => "error",
                "message" => "Thiếu dữ liệu bắt buộc: $field",
                "debug" => [
                    "received_data" => $data
                ]
            ]);
            exit();
        }
    }

    // Đẩy dữ liệu vào tầng Logic xử lý (BLL)
    $bookingBLL = new BookingBLL($db);
    $result = $bookingBLL->createNewBooking($data);
    
    echo json_encode($result);
    exit();
}

// ==========================================
// NẾU ACTION KHÔNG KHỚP VỚI BẤT KỲ TRƯỜNG HỢP NÀO Ở TRÊN
// ==========================================
http_response_code(400);
echo json_encode([
    "status" => "error",
    "message" => "Invalid endpoint. Hãy kiểm tra lại tên action.",
    "debug" => [
        "action" => $action,
        "method" => $_SERVER['REQUEST_METHOD'],
        "query_string" => $_SERVER['QUERY_STRING'] ?? '',
        "request_uri" => $_SERVER['REQUEST_URI'] ?? '',
        "content_type" => $_SERVER['CONTENT_TYPE'] ?? ''
    ]
]);
exit();
?>