<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/Database.php';
require_once '../bll/PaymentBLL.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : die();

switch ($action) {
    case 'search_customers':
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $paymentBLL = new PaymentBLL($db);
        $customers = $paymentBLL->searchCustomers($keyword);
        echo json_encode(["status" => "success", "data" => $customers]);
        break;
    case 'get_customer_unpaid_bookings':
        $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
        if (!$customerId) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "customer_id is required."]);
            exit();
        }
        $paymentBLL = new PaymentBLL($db);
        $bookings = $paymentBLL->getCustomerUnpaidBookings($customerId);
        echo json_encode(["status" => "success", "data" => $bookings]);
        break;
    case 'get_invoice_details':
        $bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
        if (!$bookingId) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "booking_id is required."]);
            exit();
        }
        $paymentBLL = new PaymentBLL($db);
        $result = $paymentBLL->getInvoiceDetails($bookingId);
        echo json_encode($result);
        break;
    case 'confirm_payment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents("php://input"), true);
            
            // Basic validation
            $required_fields = ['booking_id', 'user_id', 'items', 'total_rental', 'total_items', 'deposit', 'final_amount'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
                    exit();
                }
            }

            $paymentBLL = new PaymentBLL($db);
            $result = $paymentBLL->confirmPayment($data);
            echo json_encode($result);
        } else {
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}
?>