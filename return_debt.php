<?php
include 'config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Получаем информацию о долге
$stmt = $pdo->prepare("SELECT * FROM debts WHERE id=?");
$stmt->execute([$id]);
$debt = $stmt->fetch();

if (!$debt) {
    die("Долг не найден");
}

// Если отправлена форма
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_returned = floatval($_POST['amount']);
    $wallet_id = intval($_POST['wallet_id']);
    $currency_id = intval($_POST['currency_id']);
    $description = $_POST['description'] ?? '';

    if ($amount_returned <= 0) {
        die("Некорректная сумма возврата");
    }

    // 1️⃣ Добавляем запись в транзакции как ПРИХОД
    $stmt = $pdo->prepare("
        INSERT INTO transactions (wallet_id, user_id, currency_id, type, amount, description)
        VALUES (?, ?, ?, 'income', ?, ?)
    ");
    $desc_text = "Возврат долга от " . $debt['debtor_name'] . ($description ? " — " . $description : "");
    $stmt->execute([
        $wallet_id,
        $_SESSION['user']['id'],
        $currency_id,
        $amount_returned,
        $desc_text
    ]);

    // 2️⃣ Добавляем запись в историю погашений
    $stmt = $pdo->prepare("
        INSERT INTO debt_payments (debt_id, amount, wallet_id, currency_id, user_id, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $id,
        $amount_returned,
        $wallet_id,
        $currency_id,
        $_SESSION['user']['id'],
        $description
    ]);

    // 3️⃣ Обновляем остаток долга
    $new_amount = $debt['amount'] - $amount_returned;

    if ($new_amount <= 0) {
        $stmt = $pdo->prepare("UPDATE debts SET amount=0, status='closed' WHERE id=?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("UPDATE debts SET amount=? WHERE id=?");
        $stmt->execute([$new_amount, $id]);
    }

    header("Location: debtors.php");
    exit;
}


// Получаем списки кошельков и валют
$wallets = $pdo->query("SELECT * FROM wallets")->fetchAll();
$currencies = $pdo->query("SELECT * FROM currencies")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Возврат долга</title>
<link rel="stylesheet" href="style/return.css">
</head>
<body>
<div class="container">
    <a href="debtors.php" class="return-link">⬅ Назад</a>
    <h2>💵 Возврат долга от <?= htmlspecialchars($debt['debtor_name']) ?></h2>
    <p>Остаток долга: <strong><?= number_format($debt['amount'], 2) ?> TMT</strong></p>

    <form method="POST">
        <div>
            <label for="amount">Сумма возврата:</label>
            <input type="number" name="amount" step="0.01" required>
        </div>
        <div>
         <label for="description">Комментарий (необязательно):</label>
        <textarea name="description" id="description" rows="2" placeholder="Например: частичный возврат наличными"></textarea>
        </div>

        <div>
            <label for="wallet_id">Кошелек для зачисления:</label>
            <select name="wallet_id" required>
                <?php foreach ($wallets as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="currency_id">Валюта:</label>
            <select name="currency_id" required>
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit">💰 Подтвердить возврат</button>
    </form>
</div>
</body>
</html>
