<?php
include 'config.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT COUNT(*) FROM money_submissions WHERE status = 'pending'");
$count = $stmt->fetchColumn();
echo json_encode(['count' => (int)$count]);
