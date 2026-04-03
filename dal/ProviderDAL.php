<?php
class ProviderDAL {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function searchByName($keyword) {
        $query = "SELECT * FROM providers WHERE UPPER(name) LIKE UPPER(:keyword)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['keyword' => "%$keyword%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProvider($code, $name, $phone, $email, $address) {
        $query = "INSERT INTO providers (code, name, phone, email, address) 
                  VALUES (:code, :name, :phone, :email, :address)";
        $stmt = $this->conn->prepare($query);
        if ($stmt->execute(['code' => $code, 'name' => $name, 'phone' => $phone, 'email' => $email, 'address' => $address])) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
}
?>