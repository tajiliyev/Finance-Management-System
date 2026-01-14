<?php
include 'config.php';
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$wallets = $pdo->query("SELECT * FROM wallets")->fetchAll();
$currencies = $pdo->query("SELECT * FROM currencies")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $wallet_id = $_POST['wallet_id'];
    $currency_id = $_POST['currency_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $is_credit = isset($_POST['is_credit']) ? 1 : 0;
    $debtor_name = $_POST['debtor_name'] ?? null;

    // Сохраняем транзакцию
    $stmt = $pdo->prepare("INSERT INTO transactions (wallet_id, user_id, currency_id, type, amount, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $wallet_id,
        $_SESSION['user']['id'],
        $currency_id,
        $type,
        $amount,
        $description
    ]);

    // Если это кредит — добавляем в таблицу долгов
    if ($is_credit && $type === 'expense' && !empty($debtor_name)) {
       $stmt = $pdo->prepare("INSERT INTO debts (user_id, debtor_name, amount, initial_amount, description) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([
    $_SESSION['user']['id'],
    $debtor_name,
    $amount,          // текущий долг
    $amount,          // изначально выдано
    "Кредит: " . $description
]);

    }

    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить транзакцию</title>
    <link rel="stylesheet" href="style/add_transaction.css">

    <script>
        function toggleDebtorField() {
            const checkbox = document.getElementById('is_credit');
            const debtorBlock = document.getElementById('debtor_block');
            if (checkbox.checked) {
                debtorBlock.classList.add('show');
                debtorBlock.classList.add('highlight');
                setTimeout(() => debtorBlock.classList.remove('highlight'), 600);
            } else {
                debtorBlock.classList.remove('show');
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="flex">
            <a href="dashboard.php" class="return-link">⬅ в панель</a>
            <a href="edit_transaction.php" class="return-link">✏️Изменить транзакцию</a>
            <h2>➕ Добавить транзакцию</h2>
        </div>

        <form method="POST">
            <div>
                <label for="type">Тип:</label>
                <select name="type" id="type" required>
                    <option value="income">Приход</option>
                    <option value="expense">Расход</option>
                </select>
            </div>

            <div>
                <label for="wallet_id">Кошелек:</label>
                <select name="wallet_id" id="wallet_id" required>
                    <?php foreach ($wallets as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="currency_id">Валюта:</label>
                <select name="currency_id" id="currency_id" required>
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="amount">Сумма:</label>
                <input type="number" name="amount" step="0.01" required>
            </div>

            <!-- Красивая зона для кредита -->
            <div class="credit-checkbox">
                <input type="checkbox" id="is_credit" name="is_credit" onchange="toggleDebtorField()">
                <label for="is_credit" class="checkbox-label">
                    <span class="custom-checkbox"></span>
                    💸 <span class="checkbox-text">Выдать в долг (кредит)</span>
                </label>
                <p class="hint">Отметьте, если эта сумма выдаётся в долг другому человеку</p>
            </div>

            <div id="debtor_block" class="debtor-block">
                <label for="debtor_name">Имя должника:</label>
                <input type="text" name="debtor_name" id="debtor_name" placeholder="Введите имя должника">
            </div>

            <div>
                <label for="description">Описание:</label>
                <textarea name="description" id="description" rows="3" placeholder="Опишите транзакцию" required></textarea>
            </div>

            <div class="flex">
                <button type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</body>
</html>
