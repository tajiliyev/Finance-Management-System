<?php
$host = 'localhost';
$db   = 'finance_db';
$user = 'root';
$pass = ''; // если в XAMPP пустой
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die('Ошибка подключения к БД: ' . $e->getMessage());
}
session_start();
?>
