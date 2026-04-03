<?php
class ImportDAL {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function createInvoice($providerId, $userId, $totalAmount) {
        $query = "INSERT INTO import_invoices (provider_id, user_id, total_amount) 
                  VALUES (:provider_id, :user_id, :total)";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute(['provider_id' => $providerId, 'user_id' => $userId, 'total' => $totalAmount])) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function addImportDetail($invoiceId, $productId, $unitPrice, $quantity, $amount) {
        $query = "INSERT INTO import_details (import_invoice_id, product_id, unit_price, quantity, amount) 
                  VALUES (:invoice_id, :product_id, :price, :qty, :amount)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'invoice_id' => $invoiceId, 'product_id' => $productId, 
            'price' => $unitPrice, 'qty' => $quantity, 'amount' => $amount
        ]);
    }
}
?>