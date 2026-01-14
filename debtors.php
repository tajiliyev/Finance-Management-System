<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debtor = $_POST['debtor_name'];
    $amount = $_POST['amount'];
    $desc = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO debts (user_id, debtor_name, amount, initial_amount, returned, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
$stmt->execute([
    $_SESSION['user']['id'],
    $debtor,
    $amount,       // текущий долг
    $amount,       // исходно выдано
    0,             // ещё никто не вернул
    $desc
]);

}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Должники</title>
<link rel="stylesheet" href="style/dashboard.css">
</head>
<body>

<div class="container">
    <h2>💸 Учет должников</h2>

    <?php
    $stmt = $pdo->query("SELECT * FROM debts WHERE status='open' ORDER BY created_at DESC");
    $debts = $stmt->fetchAll();
    ?>

    


    <div class="card">


        <div class="container">
        <div class="flex">
            <a href="dashboard.php" class="return-link">⬅ Вернуться на панель</a>
            <a href="debt_report.php" class="btn btn-purple">📘 Отчёт по долгам</a>
            <h2>📋 Список должников</h2>
        </div>  
        <table>
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Сумма</th>
                    <th>Описание</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($debts as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['debtor_name']) ?></td>
                    <td><?= number_format($d['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($d['description']) ?></td>
                    <td><?= htmlspecialchars($d['created_at']) ?></td>
                    <td>
                        <a href="return_debt.php?id=<?= $d['id'] ?>" class="btn btn-small btn-green">Возврат</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3>📜 История возвратов</h3>
<?php
$stmt = $pdo->query("
    SELECT dp.*, d.debtor_name
    FROM debt_payments dp
    JOIN debts d ON dp.debt_id = d.id
    ORDER BY dp.created_at DESC
");
$payments = $stmt->fetchAll();
?>

<?php if ($payments): ?>
<table>
    <thead>
        <tr>
            <th>Дата</th>
            <th>Должник</th>
            <th>Сумма</th>
            <th>Комментарий</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['created_at']) ?></td>
            <td><?= htmlspecialchars($p['debtor_name']) ?></td>
            <td><?= number_format($p['amount'], 2) ?> TMT</td>
            <td><?= htmlspecialchars($p['description']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <p>Возвратов пока нет.</p>
<?php endif; ?>

</div>

</body>
</html>
