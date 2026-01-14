<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user']['id'];

// Настройки пагинации
$perPage = 12; // 30 записей на страницу
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Общее количество записей
$stmt = $pdo->prepare("SELECT COUNT(*) FROM money_submissions WHERE user_id = ?");
$stmt->execute([$userId]);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Получаем данные для текущей страницы
$stmt = $pdo->prepare("
    SELECT ms.*, c.symbol, w.name AS wallet_name
    FROM money_submissions ms
    JOIN currencies c ON ms.currency_id = c.id
    JOIN wallets w ON ms.wallet_id = w.id
    WHERE ms.user_id = ?
    ORDER BY ms.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$approved = $stmt->fetchAll();

// Функция компактной пагинации
function pagination($page, $totalPages) {
    $pages = [];

    // Всегда первые 2 страницы
    $pages[] = 1;
    if ($totalPages > 1) $pages[] = 2;

    // Соседние страницы вокруг текущей
    for ($i = $page-1; $i <= $page+1; $i++) {
        if ($i > 2 && $i < $totalPages-1) {
            $pages[] = $i;
        }
    }

    // Последние 2 страницы
    if ($totalPages - 1 > 2) $pages[] = $totalPages - 1;
    if ($totalPages > 2) $pages[] = $totalPages;

    // Убираем дубли и сортируем
    $pages = array_unique($pages);
    sort($pages);

    // Добавляем …
    $finalPages = [];
    $prev = 0;
    foreach($pages as $p) {
        if ($prev && $p - $prev > 1) {
            $finalPages[] = '...';
        }
        $finalPages[] = $p;
        $prev = $p;
    }

    return $finalPages;
}

$pagesToShow = pagination($page, $totalPages);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Отчёт</title>
<link rel="stylesheet" href="style/my_reports.css">
</head>
<body>

<div class="container">
    <div class="header">
        <a href="dashboard.php" class="back-button">⬅ Назад</a>
        <h2 class="title">
            <span style="font-size: 2rem;">📊</span>
            <span>Отчёт по сдаче денег</span>
        </h2>
    </div>

    <!-- Пагинация сверху -->
    <div class="pagination">
        <a href="?page=<?= max(1, $page-1) ?>" class="<?= $page==1 ? 'disabled' : '' ?>">‹</a>
        <?php foreach($pagesToShow as $p): ?>
            <?php if($p==='...'): ?>
                <span>…</span>
            <?php else: ?>
                <a href="?page=<?= $p ?>" class="<?= $p==$page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a href="?page=<?= min($totalPages, $page+1) ?>" class="<?= $page==$totalPages ? 'disabled' : '' ?>">›</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Кошелек</th>
                <th>Сумма</th>
                <th>Валюта</th>
                <th>Описание</th>
                <th>Дата сдачи</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($approved as $row): ?>
            <?php
            $status = htmlspecialchars($row['status']);
            $statusClass = 'status-' . str_replace('_', '-', strtolower($status));
            switch (strtolower($status)) {
                case 'approved': $statusText = 'Успешно'; break;
                case 'rejected': $statusText = 'Отклонено'; break;
                case 'pending': $statusText = 'Ожидает'; break;
                default: $statusText = ucfirst($status); break;
            }
            ?>
            <tr>
                <td><?= htmlspecialchars($row['wallet_name']) ?></td>
                <td><?= number_format($row['amount'],2) ?> <?= $row['symbol'] ?></td>
                <td><?= htmlspecialchars($row['symbol']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td><span class="status <?= $statusClass ?>"><?= $statusText ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Можно также добавить пагинацию снизу при желании -->
</div>

</body>
</html>
