<?php
// API endpoint cho Store Procedures & Triggers Management
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/Database.php';
require_once '../bll/ScheduleBLL.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
}

if (empty($action)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing action parameter."]);
    exit();
}

$scheduleBLL = new ScheduleBLL($db);

switch ($action) {

    // =====================================================
    // 8.1: Sinh lịch chơi tự động
    // =====================================================
    case 'generate_schedule':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            $bookingId = isset($data['booking_id']) ? (int)$data['booking_id'] : 0;
            $dayOfWeek = isset($data['day_of_week']) ? (int)$data['day_of_week'] : -1;

            if (!$bookingId || $dayOfWeek < 0 || $dayOfWeek > 6) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Dữ liệu không hợp lệ. Cần booking_id và day_of_week (0-6)."]);
                exit();
            }

            $result = $scheduleBLL->generateMonthlySessions($bookingId, $dayOfWeek);
            echo json_encode($result);
        } else {
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        }
        break;

    // =====================================================
    // 8.2: Lấy hóa đơn gom
    // =====================================================
    case 'consolidated_invoice':
        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "session_id is required."]);
            exit();
        }
        $result = $scheduleBLL->getConsolidatedInvoice($sessionId);
        echo json_encode($result);
        break;

    // =====================================================
    // 8.3: Tìm sân trống (có xử lý sân ghép)
    // =====================================================
    case 'find_available_courts':
        $playDate = isset($_GET['play_date']) ? $_GET['play_date'] : '';
        $timeSlot = isset($_GET['time_slot']) ? $_GET['time_slot'] : '';
        $courtType = isset($_GET['court_type']) ? (int)$_GET['court_type'] : 0;

        $result = $scheduleBLL->findAvailableCourts($playDate, $timeSlot, $courtType);
        echo json_encode($result);
        break;

    // =====================================================
    // Lấy danh sách booking active/pending
    // =====================================================
    case 'active_bookings':
        $result = $scheduleBLL->getActiveBookings();
        echo json_encode($result);
        break;

    // =====================================================
    // Lấy sessions theo booking
    // =====================================================
    case 'booking_sessions':
        $bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
        if (!$bookingId) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "booking_id is required."]);
            exit();
        }
        $result = $scheduleBLL->getSessionsByBooking($bookingId);
        echo json_encode($result);
        break;

    // =====================================================
    // Quản lý Court Mappings
    // =====================================================
    case 'get_court_mappings':
        $result = $scheduleBLL->getCourtMappings();
        echo json_encode($result);
        break;

    case 'add_court_mapping':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            $parentId = isset($data['parent_court_id']) ? (int)$data['parent_court_id'] : 0;
            $childId = isset($data['child_court_id']) ? (int)$data['child_court_id'] : 0;
            $result = $scheduleBLL->addCourtMapping($parentId, $childId);
            echo json_encode($result);
        } else {
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        }
        break;

    case 'delete_court_mapping':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            $mappingId = isset($data['mapping_id']) ? (int)$data['mapping_id'] : 0;
            $result = $scheduleBLL->deleteCourtMapping($mappingId);
            echo json_encode($result);
        } else {
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        }
        break;

    case 'get_all_courts':
        $result = $scheduleBLL->getAllCourts();
        echo json_encode($result);
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Invalid action: $action"]);
        break;
}
?>
