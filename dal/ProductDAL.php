<?php
class ProductDAL {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function searchByName($keyword) {
        $query = "SELECT id, code, name, stock_qty FROM products WHERE UPPER(name) LIKE UPPER(:keyword)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['keyword' => "%$keyword%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProduct($code, $name) {
        $query = "INSERT INTO products (code, name, stock_qty) VALUES (:code, :name, 0)";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute(['code' => $code, 'name' => $name])) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Cập nhật tồn kho (+ khi nhập kho, - khi bán nước)
    public function updateStock($productId, $qtyChange) {
        $query = "UPDATE products SET stock_qty = stock_qty + :change WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['change' => $qtyChange, 'id' => $productId]);
    }
}
?>