<?php
require_once '../dal/PaymentDAL.php';
require_once '../dal/ProductDAL.php';
require_once '../dal/BookingDAL.php';
require_once '../dal/CustomerDAL.php';

class PaymentBLL {
    private $db;
    private $paymentDAL;
    private $productDAL;
    private $bookingDAL;
    private $customerDAL;

    public function __construct($db) {
        $this->db = $db;
        $this->paymentDAL = new PaymentDAL($db);
        $this->productDAL = new ProductDAL($db);
        $this->bookingDAL = new BookingDAL($db);
        $this->customerDAL = new CustomerDAL($db);
    }

    public function getInvoiceDetails($bookingId) {
        // Here you could add business logic, e.g., calculating late fees, applying discounts, etc.
        // For now, it directly calls the DAL and returns the data.
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
            $totalRental = $data['total_rental'];
            $totalItems = $data['total_items'];
            $deposit = $data['deposit'];
            $finalAmount = $data['final_amount'];

            // 1. Get the original state of the invoice from the DB
            $originalInvoice = $this->paymentDAL->getInvoiceDetails($bookingId);
            if (!$originalInvoice) {
                throw new Exception("Booking không tồn tại.");
            }
            $originalItems = $originalInvoice['used_items'];
            
            // 2. Process changes in used items and update stock
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
                            $quantityChange = $originalItem['quantity'] - $item['quantity'];
                            $this->productDAL->updateStock($item['product_id'], $quantityChange);
                        }
                    }
                }
            }
            
            foreach ($originalItems as $originalItem) {
                if (!isset($finalItemsMap[$originalItem['item_id']])) {
                    $this->productDAL->updateStock($originalItem['product_id'], $originalItem['quantity']);
                    $this->paymentDAL->deleteSessionUsedItem($originalItem['item_id']);
                }
            }

            // 3. Update booking status to 'paid' and set the final amount
            $this->paymentDAL->confirmBookingPayment($bookingId, $finalAmount);

            $this->db->commit();
            return ["status" => "success", "message" => "Thanh toán thành công!"];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ["status" => "error", "message" => "Lỗi hệ thống khi xác nhận thanh toán: " . $e->getMessage()];
        }
    }

    public function searchCustomers($keyword) {
        // Logic nghiệp vụ: Validate keyword, etc.
        return $this->customerDAL->searchByNameOrPhone($keyword);
    }

    public function getCustomerUnpaidBookings($customerId) {
        // Logic nghiệp vụ: Có thể thêm filter theo user permissions, etc.
        return $this->bookingDAL->getUnpaidBookingsByCustomer($customerId);
    }
}
?>