<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/Database.php';
require_once '../bll/ImportBLL.php';

$database = new Database();
$db = $database->getConnection();
$importBLL = new ImportBLL($db);

$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- ROUTER ---
try {
    switch ($action) {
        case 'search_providers':
            $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
            $results = $importBLL->searchProviders($keyword);
            echo json_encode(["status" => "success", "data" => $results]);
            break;

        case 'add_provider':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents("php://input"), true);
                $newProvider = $importBLL->addNewProvider($data);
                echo json_encode(["status" => "success", "data" => $newProvider]);
            } else {
                 throw new Exception("Invalid request method for adding provider.");
            }
            break;

        case 'search_products':
            $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
            $results = $importBLL->searchProducts($keyword);
            echo json_encode(["status" => "success", "data" => $results]);
            break;

        case 'add_product':
             if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents("php://input"), true);
                $newProduct = $importBLL->addNewProduct($data);
                echo json_encode(["status" => "success", "data" => $newProduct]);
            } else {
                 throw new Exception("Invalid request method for adding product.");
            }
            break;

        case 'submit_import':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents("php://input"), true);
                $result = $importBLL->processImport($data);
                echo json_encode($result);
            } else {
                throw new Exception("Invalid request method for submitting import.");
            }
            break;

        default:
            throw new Exception("Invalid action specified.");
            break;
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>