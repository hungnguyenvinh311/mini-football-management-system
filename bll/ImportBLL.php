<?php
require_once '../dal/ProviderDAL.php';
require_once '../dal/ProductDAL.php';
require_once '../dal/ImportDAL.php';

class ImportBLL {
    private $db;
    private $providerDAL;
    private $productDAL;
    private $importDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->providerDAL = new ProviderDAL($db);
        $this->productDAL = new ProductDAL($db);
        $this->importDAL = new ImportDAL($db);
    }

    public function searchProviders($keyword) {
        return $this->providerDAL->searchByName($keyword);
    }

    public function addNewProvider($data) {
        // Basic validation
        if (empty($data['name']) || empty($data['phone'])) {
            throw new Exception("Provider name and phone are required.");
        }
        // Generate a unique code
        $code = 'PROV' . time(); 
        $name = $data['name'];
        $phone = $data['phone'];
        $email = isset($data['email']) ? $data['email'] : '';
        $address = isset($data['address']) ? $data['address'] : '';

        $newProviderId = $this->providerDAL->createProvider($code, $name, $phone, $email, $address);
        
        if ($newProviderId) {
            return [
                'id' => $newProviderId,
                'code' => $code,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ];
        }
        return null;
    }

    public function searchProducts($keyword) {
        return $this->productDAL->searchByName($keyword);
    }

    public function addNewProduct($data) {
        if (empty($data['name'])) {
            throw new Exception("Product name is required.");
        }
        $code = 'PROD' . time();
        $name = $data['name'];

        $newProductId = $this->productDAL->createProduct($code, $name);
        
        if ($newProductId) {
            return [
                'id' => $newProductId,
                'code' => $code,
                'name' => $name
            ];
        }
        return null;
    }

    public function processImport($data) {
        // Validation
        if (empty($data['provider_id']) || empty($data['user_id']) || empty($data['items']) || !isset($data['total_amount'])) {
            throw new Exception("Invalid import data provided.");
        }

        try {
            $this->db->beginTransaction();

            $providerId = $data['provider_id'];
            $userId = $data['user_id'];
            $totalAmount = $data['total_amount'];
            $items = $data['items'];

            // 1. Create the main import invoice
            $importId = $this->importDAL->createInvoice($providerId, $userId, $totalAmount);
            if (!$importId) {
                throw new Exception("Could not create import invoice record.");
            }

            // 2. Add details and update stock for each item
            foreach ($items as $item) {
                // Add to import_details table
                $this->importDAL->addImportDetail(
                    $importId,
                    $item['product_id'],
                    $item['unit_price'],
                    $item['quantity'],
                    $item['amount']
                );

                // Update product stock quantity
                $this->productDAL->updateStock($item['product_id'], $item['quantity']);
            }

            $this->db->commit();
            return ["status" => "success", "message" => "Nhập kho thành công!", "import_id" => $importId];

        } catch (Exception $e) {
            $this->db->rollBack();
            // Log the error message for debugging
            error_log("Import processing failed: " . $e->getMessage());
            return ["status" => "error", "message" => "Lỗi hệ thống khi xử lý nhập kho."];
        }
    }
}
?>