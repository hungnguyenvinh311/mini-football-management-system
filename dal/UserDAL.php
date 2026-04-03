<?php
class UserDAL {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    // Tìm user theo username (Dùng cho đăng nhập auth.html)
    public function findByUsername($username) {
        $query = "SELECT id, username, password_hash, full_name, role FROM users WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>