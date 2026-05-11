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

    // File: bll/PaymentBLL.php

public function confirmPayment($data) {
    try {
        $this->db->beginTransaction();

        $bookingId = $data['booking_id'];
        $userId = $data['user_id'];
        $finalItems = $data['items'];

        // --- BƯỚC MỚI: LẤY TỔNG TIỀN THỰC TẾ TỪ DỮ LIỆU GỬI LÊN ---
        // Tổng tiền thực tế = Tiền sân + Tiền dịch vụ
        $totalRental = (float)$data['total_rental'];
        $totalItems = (float)$data['total_items'];
        $grandTotal = $totalRental + $totalItems; 
        // ------------------------------------------------------

        $originalInvoice = $this->paymentDAL->getInvoiceDetails($bookingId);
        if (!$originalInvoice) {
            throw new Exception("Booking không tồn tại.");
        }
        $originalItems = $originalInvoice['used_items'];

        // 1. CẬP NHẬT LẠI SỐ LƯỢNG ĐỒ UỐNG (Giữ nguyên logic của bạn)
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

        // 2. LẶP QUA TỪNG CA ĐÁ ĐỂ TRỪ KHO (Giữ nguyên logic của bạn)
        $sessions = $originalInvoice['rental_sessions'];
        foreach ($sessions as $session) {
            if (isset($session['payment_status']) && $session['payment_status'] === 'unpaid') {
                $this->paymentDAL->callThanhToanProcedure($session['session_id']);
            }
        }

        // 3. ĐÁNH DẤU BOOKING LÀ ĐÃ THANH TOÁN VÀ LƯU TỔNG TIỀN
        // Truyền thêm biến $grandTotal vào đây
        $this->paymentDAL->completeBooking($bookingId, $grandTotal);

        $this->db->commit();
        return ["status" => "success", "message" => "Thanh toán thành công!"];

    } catch (Exception $e) {
        $this->db->rollBack();
        return ["status" => "error", "message" => "Lỗi: " . $e->getMessage()];
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