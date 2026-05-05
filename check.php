<?php
require_once 'c:/xampp/htdocs/dbms1/config/Database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'products'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
