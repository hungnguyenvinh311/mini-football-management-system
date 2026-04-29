<?php
require_once '../dal/PaymentDAL.php';
require_once '../dal/BookingDAL.php';
require_once '../dal/CustomerDAL.php';

class PaymentBLL {
    private $db;
    private $paymentDAL;
    private $bookingDAL;
    private $customerDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->paymentDAL = new PaymentDAL($db);
        $this->bookingDAL = new BookingDAL($db);
        $this->customerDAL = new CustomerDAL($db);
    }

    public function getInvoiceDetails($bookingId) {
        $invoiceData = $this->paymentDAL->getInvoiceDetails($bookingId);

        if (!$invoiceData) {
            return ["status" => "error", "message" => "Không tìm thấy chi tiết hóa đơn cho booking ID này."];
        }
        
        return ["status" => "success", "data" => $invoiceData];
    }

    public function confirmPayment($data) {
        try {
            $this->db->beginTransaction();

            $bookingId = $data['booking_id'];
            $userId = $data['user_id'];
            $finalItems = $data['items'];
            // Các biến tính toán bên giao diện gửi lên (totalRental, totalItems, deposit, finalAmount) 
            // có thể giữ lại để ghi log hoặc đối chiếu sau này nếu cần thiết.

            $originalInvoice = $this->paymentDAL->getInvoiceDetails($bookingId);
            if (!$originalInvoice) {
                throw new Exception("Booking không tồn tại.");
            }
            $originalItems = $originalInvoice['used_items'];

            // 1. CẬP NHẬT LẠI SỐ LƯỢNG ĐỒ UỐNG TRƯỚC (NẾU KHÁCH TRẢ LẠI)
            $originalItemsMap = [];
            foreach ($originalItems as $item) {
                $originalItemsMap[$item['item_id']] = $item;
            }

            $finalItemsMap = [];
            if (is_array($finalItems)) {
                foreach ($finalItems as $item) {
                    $finalItemsMap[$item['id']] = $item;
                    if (isset($originalItemsMap[$item['id']])) {
                        $originalItem = $originalItemsMap[$item['id']];
                        if ($originalItem['quantity'] != $item['quantity']) {
                            $newTotal = $item['unit_price'] * $item['quantity'];
                            $this->paymentDAL->updateSessionUsedItemQuantity($item['id'], $item['quantity'], $newTotal);
                        }
                    }
                }
            }

            foreach ($originalItems as $originalItem) {
                if (!isset($finalItemsMap[$originalItem['item_id']])) {
                    $this->paymentDAL->deleteSessionUsedItem($originalItem['item_id']);
                }
            }

            // 2. LẶP QUA TỪNG CA ĐÁ VÀ GỌI PROCEDURE SQL ĐỂ TRỪ KHO VÀ CHỐT THANH TOÁN
            $sessions = $originalInvoice['rental_sessions'];
            foreach ($sessions as $session) {
                // Đảm bảo chỉ thanh toán các ca chưa trả tiền
                if (isset($session['payment_status']) && $session['payment_status'] === 'unpaid') {
                    $this->paymentDAL->callThanhToanProcedure($session['session_id']);
                }
            }

            // 3. ĐÁNH DẤU HỢP ĐỒNG TỔNG (BOOKING) LÀ ĐÃ HOÀN TẤT
            $this->paymentDAL->completeBooking($bookingId);

            $this->db->commit();
            return ["status" => "success", "message" => "Thanh toán thành công! Kho đã được trừ tự động."];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ["status" => "error", "message" => "Lỗi hệ thống khi xác nhận thanh toán: " . $e->getMessage()];
        }
    }

    public function searchCustomers($keyword) {
        return $this->customerDAL->searchByNameOrPhone($keyword);
    }

    public function getCustomerUnpaidBookings($customerId) {
        return $this->paymentDAL->getCustomerUnpaidBookings($customerId);
    }
}
?>