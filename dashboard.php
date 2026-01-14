<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION['user']['role'];
$username = $_SESSION['user']['username'];
$userId = $_SESSION['user']['id'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления</title>
    <link rel="stylesheet" href="style/dashboard.css">
</head>
<body>

<div class="container">
    <h2>Добро пожаловать, <?= htmlspecialchars($username) ?>!</h2>

    <?php if ($role === 'admin'): 
        // 📥 Количество ожидающих подтверждения
        $stmt = $pdo->query("SELECT COUNT(*) FROM money_submissions WHERE status = 'pending'");
        $pendingCount = $stmt->fetchColumn();

        // 💸 Общая сумма долгов
        $stmt = $pdo->query("SELECT SUM(amount) FROM debts WHERE status = 'open'");
        $totalDebts = (float)($stmt->fetchColumn() ?? 0);
    ?>
        <div>
            <a href="add_transaction.php" class="btn btn-admin">➕ Транзакция</a>

            <a href="admin_submissions.php" class="btn btn-green relative">
                ✅ Подтверждение
                <?php if ($pendingCount > 0): ?>
                    <span class="badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>

            <a href="debtors.php" class="btn btn-orange relative">
                💸 Должники
             <span class="badge"><?= number_format($totalDebts, 2) ?> TMT</span>
            </a>

            <a href="admin_report.php" class="btn btn-purple">📊 Отчёты</a>
            <a href="saldo_report.php" class="btn btn-purple">🧾 Отчет Сальдо</a>
            <a href="add_wallet.php" class="btn btn-purple">💼 Кошельки</a>
            <a href="users.php" class="btn btn-purple">👥 Пользователи</a>
            <a href="logout.php" class="btn btn-red">🚪 Выйти</a>
        </div>

        <?php
        // 💰 Общий баланс (без валютного кошелька)
        // 💰 Общий баланс (исключаем валютный и банковский кошельки по флагу exclude_from_total)
$excluded_wallets = $pdo->query("SELECT id FROM wallets WHERE exclude_from_total IN (1, 2)")
->fetchAll(PDO::FETCH_COLUMN);
$placeholders = implode(',', array_fill(0, count($excluded_wallets), '?'));

$stmt = $pdo->query("SELECT amount FROM initial_balance WHERE id = 1");
$initial = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND wallet_id NOT IN ($placeholders)");
$stmt->execute($excluded_wallets);
$income = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND wallet_id NOT IN ($placeholders)");
$stmt->execute($excluded_wallets);
$expense = $stmt->fetchColumn() ?? 0;

$balance = $initial + $income - $expense;
        // 🌍 Баланс по валютным кошелькам
        $stmt = $pdo->query("
            SELECT c.name AS currency_name, c.symbol,
                   SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END) AS balance
            FROM transactions t
            JOIN currencies c ON t.currency_id = c.id
            WHERE t.wallet_id = 4
            GROUP BY c.id
        ");
        $currency_balances = $stmt->fetchAll();
        ?>

        <div class="grid">
            <div class="card">
                <h3>💰 Общий баланс</h3>
                <p clas="card"><?= number_format($balance, 2) ?> TMT</p>
            </div>

            <div class="card">
                <h3>🌍 Валютный баланс</h3>
                <?php if ($currency_balances): ?>
                    <?php foreach ($currency_balances as $cb): ?>
                        <p clas="card"><?= number_format($cb['balance'], 2) ?> <?= htmlspecialchars($cb['symbol']) ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Нет валютных транзакций.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>🟢 Приходы</h3>
                <p clas="card"><?= number_format($income, 2) ?> TMT</p>
            </div>

            <div class="card">
                <h3>🔴 Расходы</h3>
                <p clas="card"><?= number_format($expense, 2) ?> TMT</p>
            </div>
        </div>

        <?php
        // 💼 Балансы по кошелькам
        $stmt = $pdo->query("
            SELECT w.name AS wallet_name, c.symbol,
                   SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END) AS balance
            FROM wallets w
            LEFT JOIN transactions t ON t.wallet_id = w.id
            LEFT JOIN currencies c ON c.id = t.currency_id
            WHERE w.id != 4 AND w.id != 6
            GROUP BY w.id, c.id
        ");
        $balances = $stmt->fetchAll();
        ?>

        <div class="card">
            <h3>💼 Баланс по кошелькам</h3>
            <?php if ($balances): ?>
            <table>
                <thead>
                    <tr>
                        <th>Кошелек</th>
                        <th>Баланс</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($balances as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['wallet_name']) ?></td>
                        <td><?= number_format($b['balance'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Нет данных.</p>
            <?php endif; ?>
        </div>

        <?php
        // 🕒 Последние транзакции
        $stmt = $pdo->query("
            SELECT t.*, w.name AS wallet_name, c.name AS currency_name, c.symbol, u.username
            FROM transactions t
            JOIN wallets w ON t.wallet_id = w.id
            JOIN currencies c ON t.currency_id = c.id
            JOIN users u ON t.user_id = u.id
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $transactions = $stmt->fetchAll();
        ?>

        <div class="card">
            <h3>🕒 Последние транзакции</h3>
            <?php if ($transactions): ?>
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Пользователь</th>
                        <th>Кошелек</th>
                        <th>Валюта</th>
                        <th>Тип</th>
                        <th>Сумма</th>
                        <th>Описание</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['created_at']) ?></td>
                        <td><?= htmlspecialchars($t['username']) ?></td>
                        <td><?= htmlspecialchars($t['wallet_name']) ?></td>
                        <td><?= htmlspecialchars($t['currency_name']) ?> (<?= htmlspecialchars($t['symbol']) ?>)</td>
                        <td class="<?= $t['type'] === 'income' ? 'income-text' : 'expense-text' ?>">
                        <?= $t['type'] === 'income' ? 'Приход' : 'Расход' ?>
                        </td>
                        <td><?= number_format($t['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($t['description']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Нет транзакций.</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="card">
            <h3>Панель кассира</h3>
            <a href="submit_money.php" class="btn btn-admin">💵 Сдать деньги</a>
            <a href="my_submissions.php" class="btn btn-admin">📋 История заявок</a>
            <a href="my_reports.php" class="btn btn-admin">📊 Мои отчёты</a>
            <a href="logout.php" class="btn btn-red">🚪 Выйти</a>
        </div>
    <?php endif; ?>
</div>

<script>
    let lastCount = 0;

    function updateBadge() {
        fetch('get_pending_count.php')
            .then(response => response.json())
            .then(data => {
                const newCount = data.count;
                const badge = document.querySelector('.btn-green .badge');
                const button = document.querySelector('.btn-green');

                if (newCount > 0) {
                    if (!badge) {
                        const span = document.createElement('span');
                        span.className = 'badge';
                        span.textContent = newCount;
                        button.appendChild(span);
                    } else {
                        badge.textContent = newCount;
                    }

                    if (newCount > lastCount) {
                        document.getElementById('notifSound').play();
                    }
                } else if (badge) {
                    badge.remove();
                }

                lastCount = newCount;
            });
    }

    setInterval(updateBadge, 15000);
    updateBadge();
</script>

<audio id="notifSound" src="notify.mp3" preload="auto"></audio>


</body>
</html>
