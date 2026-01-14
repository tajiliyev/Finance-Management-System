<?php
include 'config.php';

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$sql = "
    SELECT
        DATE(created_at) AS `date`,
        SUM(CASE WHEN `type` = 'income' THEN `amount` ELSE 0 END) AS total_income,
        SUM(CASE WHEN `type` = 'expense' THEN `amount` ELSE 0 END) AS total_expense
    FROM transactions
    WHERE DATE(created_at) BETWEEN :date_from AND :date_to
    AND wallet_id NOT IN (4, 8)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'date_from' => $date_from,
    'date_to' => $date_to
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$saldo_stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN `type` = 'income' THEN `amount` ELSE 0 END) -
        SUM(CASE WHEN `type` = 'expense' THEN `amount` ELSE 0 END) AS saldo
    FROM transactions
    WHERE DATE(created_at) < :date_from
    AND wallet_id NOT IN (4, 8)
");
$saldo_stmt->execute(['date_from' => $date_from]);
$balance = (float) $saldo_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сальдо </title>
    <link rel="stylesheet" href="style/rsaldo.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Сальдо с <?= htmlspecialchars($date_from) ?> по <?= htmlspecialchars($date_to) ?></h2>
        <a href="dashboard.php" class="back-button">⬅ Назад</a>
    </div>

    <form method="GET">
        <div class="form-group">
            <label for="date_from">От</label>
            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="form-group">
            <label for="date_to">До</label>
            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <button type="submit">Показать</button>
    </form>

    <table>
        <tr>
            <th>Дата</th>
            <th>Сальдо на начало дня</th>
            <th>Приход</th>
            <th>Расход</th>
            <th>Сальдо на конец дня</th>
        </tr>
        <?php foreach ($rows as $row): 
            $start_balance = $balance;
            $income = (float) $row['total_income'];
            $expense = (float) $row['total_expense'];
            $balance = $start_balance + $income - $expense;
        ?>
        <tr>
            <td><?= htmlspecialchars($row['date']) ?></td>
            <td><?= number_format($start_balance, 2, '.', ' ') ?></td>
            <td><?= number_format($income, 2, '.', ' ') ?></td>
            <td><?= number_format($expense, 2, '.', ' ') ?></td>
            <td><?= number_format($balance, 2, '.', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
