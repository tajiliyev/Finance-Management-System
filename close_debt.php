<?php
include 'config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Получаем данные по долгу
$stmt = $pdo->prepare("SELECT * FROM debts WHERE id=?");
$stmt->execute([$id]);
$debt = $stmt->fetch();

if ($debt) {
    // Закрываем долг
    $stmt = $pdo->prepare("UPDATE debts SET status='closed' WHERE id=?");
    $stmt->execute([$id]);

    // Добавляем запись о возврате долга как приход
    $stmt = $pdo->prepare("
        INSERT INTO transactions (wallet_id, user_id, currency_id, type, amount, description)
        VALUES (?, ?, ?, 'income', ?, ?)
    ");
    
    // Здесь можешь указать нужный кошелёк и валюту (например, по умолчанию 1)
    $wallet_id = 1;
    $currency_id = 1;
    $description = "Возврат долга от " . $debt['debtor_name'];

    $stmt->execute([
        $wallet_id,
        $_SESSION['user']['id'],
        $currency_id,
        $debt['amount'],
        $description
    ]);
}

header("Location: debtors.php");
exit;
?>
