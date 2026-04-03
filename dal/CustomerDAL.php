<?php
class CustomerDAL {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    // Tìm kiếm khách hàng gần đúng (LIKE)
    public function searchByNameOrPhone($keyword) {
        $query = "SELECT id, name, phone FROM customers WHERE name ILIKE :keyword OR phone LIKE :keyword";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['keyword' => "%$keyword%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Thêm khách hàng mới và trả về ID
    public function createCustomer($name, $phone) {
        $query = "INSERT INTO customers (name, phone) VALUES (:name, :phone) RETURNING id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['name' => $name, 'phone' => $phone]);
        return $stmt->fetchColumn(); // Trả về ID vừa tạo (PostgreSQL RETURNING)
    }
}
?>