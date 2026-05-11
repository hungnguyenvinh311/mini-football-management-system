<?php
require_once '../dal/RentalSessionDAL.php';
require_once '../dal/ProductDAL.php';

class RentalSessionBLL {
    private $db;
    private $rentalSessionDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->rentalSessionDAL = new RentalSessionDAL($db);
    }

    public function createRentalSession($bookingCourtId, $playDate, $receptionTime, $returnTime, $rentAmount, $usedItems = []) {
        try {
            $this->db->beginTransaction();

            // 1. Kiểm tra session hiện có
            $existingSession = $this->rentalSessionDAL->getSessionByBookingCourtId($bookingCourtId);
            $sessionId = null;

            if ($existingSession) {
                $sessionId = $existingSession['id'];
                // Cập nhật thông tin ca đá hiện tại
                $this->rentalSessionDAL->updateSession($sessionId, $playDate, $receptionTime, $returnTime, $rentAmount);
            } else {
                // Nếu chưa có, tạo mới ca đá (Truyền NULL cho used_items để PHP xử lý bên dưới)
                $sessionId = $this->rentalSessionDAL->createSessionWithItems(
                    $bookingCourtId, $playDate, $receptionTime, $returnTime, $rentAmount, null 
                );
            }

            if (!$sessionId) {
                throw new Exception('Không xử lý được phiên thuê.');
            }

            // 2. XÓA SẠCH đồ cũ của Session này trước khi chèn mới (Dù cũ hay mới đều xóa cho chắc)
            $this->rentalSessionDAL->clearOldItems($sessionId);

            // 3. CHÈN ĐỒ ĂN/NƯỚC UỐNG VÀO DATABASE
            $totalService = 0;
            if (!empty($usedItems) && is_array($usedItems)) {
                foreach ($usedItems as $item) {
                    if (empty($item['product_id']) || empty($item['quantity'])) continue;

                    $unitPrice = (float)$item['unit_price'];
                    $qty = (int)$item['quantity'];
                    $itemTotal = $unitPrice * $qty;
                    $totalService += $itemTotal;

                    // Lưu vào bảng session_used_items
                    $this->rentalSessionDAL->addUsedItem(
                        $sessionId, 
                        $item['product_id'], 
                        $unitPrice, 
                        $qty, 
                        $itemTotal
                    );
                }
            }

            $this->db->commit();

            return [
                'status' => 'success',
                'message' => 'Đã cập nhật dịch vụ thành công!',
                'data' => [
                    'session_id' => $sessionId,
                    'grand_total' => (float)$rentAmount + $totalService
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
}