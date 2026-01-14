<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>📊 Отчёт по долгам</title>
<link rel="stylesheet" href="style/dashboard.css">
</head>
<body>
<div class="container">
    <div class="flex">
        <a href="dashboard.php" class="return-link">⬅ Назад на панель</a>
        <h2>📊 Отчёт по долгам и возвратам</h2>
    </div>

<?php
// Запрос, который точно считает по debt_id
$stmt = $pdo->query("
    SELECT 
        d.id,
        d.debtor_name,
        d.description,
        d.amount AS current_amount,
        d.status,
        d.created_at,
        (
            SELECT SUM(amount)
            FROM debt_payments dp
            WHERE dp.debt_id = d.id
        ) AS total_returned,
        d.initial_amount
    FROM debts d
    ORDER BY d.created_at DESC
");
$debts = $stmt->fetchAll();
?>

<?php if ($debts): ?>
<table>
    <thead>
        <tr>
            <th>Имя должника</th>
            <th>Выдано (всего)</th>
            <th>Возвращено</th>
            <th>Остаток</th>
            <th>Статус</th>
            <th>Дата</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($debts as $d): 
            $issued = $d['initial_amount'] ?? $d['current_amount']; // исходная сумма долга
            $returned = $d['total_returned'] ?? 0;
            $remaining = $d['status'] === 'closed' ? 0 : max(0, $issued - $returned);
        ?>
        <tr>
            <td><?= htmlspecialchars($d['debtor_name']) ?></td>
            <td><?= number_format($issued, 2) ?> TMT</td>
            <td><?= number_format($returned, 2) ?> TMT</td>
            <td><?= number_format($remaining, 2) ?> TMT</td>
            <td><?= $d['status'] === 'closed' ? '✅ Закрыт' : '🕓 Открыт' ?></td>
            <td><?= htmlspecialchars($d['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <p>Нет долговых данных.</p>
<?php endif; ?>
</div>
</body>
</html>
