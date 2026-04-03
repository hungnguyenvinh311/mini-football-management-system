<?php
class DashboardDAL {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getTotalRevenue() {
        $query = "SELECT COALESCE(SUM(total_expected_amount), 0) AS total_revenue FROM bookings WHERE status = 'paid'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function countTodaySessions() {
        $query = "SELECT COUNT(*) AS today_sessions FROM rental_sessions WHERE play_date = CURRENT_DATE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLowStockProducts($threshold = 20) {
        $query = "SELECT id, code, name, stock_qty FROM products WHERE stock_qty < :threshold ORDER BY stock_qty ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['threshold' => $threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCourtsStatus($limit = 7) {
        $query = "SELECT id, name, court_type, status FROM courts ORDER BY id LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInventoryProducts() {
        $query = "SELECT id, code, name, stock_qty FROM products ORDER BY stock_qty ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>