<?php
class Database {
    private $host = "localhost";
    private $port = "5432";
    private $db_name = "MiniPitch";
    private $username = "postgres";
    private $password = "123456";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Dừng thực thi và trả về lỗi JSON để phía Frontend có thể bắt được
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Lỗi kết nối CSDL: " . $exception->getMessage()
            ]);
            exit();
        }
        return $this->conn;
    }
}
?>