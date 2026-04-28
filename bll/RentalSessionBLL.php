<?php
require_once '../dal/RentalSessionDAL.php';
require_once '../dal/ProductDAL.php';

class RentalSessionBLL {
    private $db;
    private $rentalSessionDAL;
    private $productDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->rentalSessionDAL = new RentalSessionDAL($db);
        $this->productDAL = new ProductDAL($db);
    }

    public function createRentalSession($bookingCourtId, $playDate, $receptionTime, $returnTime, $rentAmount, $usedItems = []) {
        try {
            $this->db->beginTransaction();

            $usedItemsJson = !empty($usedItems) ? json_encode($usedItems) : null;
            $sessionId = $this->rentalSessionDAL->createSessionWithItems(
                $bookingCourtId,
                $playDate,
                $receptionTime,
                $returnTime,
                $rentAmount,
                $usedItemsJson
            );

            if (!$sessionId) {
                throw new Exception('Không tạo được phiên thuê.');
            }

            $totalService = 0;
            foreach ($usedItems as $item) {
                if (empty($item['product_id']) || empty($item['unit_price']) || empty($item['quantity'])) {
                    continue;
                }
                $totalService += $item['unit_price'] * $item['quantity'];
            }

            $this->db->commit();

            return [
                'status' => 'success',
                'message' => 'Cập nhật phiên đá và hàng hóa thành công.',
                'data' => [
                    'session_id' => $sessionId,
                    'total_service' => $totalService,
                    'rent_amount' => $rentAmount,
                    'grand_total' => $rentAmount + $totalService
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
        }
    }
}
?>