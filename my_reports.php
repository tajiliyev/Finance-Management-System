<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user']['id'];

// ===================
// Фильтры
// ===================
$params = [$userId];
$where = "ms.user_id = ? AND ms.status = 'approved'";

if (!empty($_GET['from_date'])) {
    $where .= " AND DATE(ms.created_at) >= ?";
    $params[] = $_GET['from_date'];
}

if (!empty($_GET['to_date'])) {
    $where .= " AND DATE(ms.created_at) <= ?";
    $params[] = $_GET['to_date'];
}

if (!empty($_GET['description'])) {
    $where .= " AND ms.description LIKE ?";
    $params[] = '%' . $_GET['description'] . '%';
}

// ===================
// Пагинация
// ===================
$perPage = 13;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Общее количество записей
$countSql = "SELECT COUNT(*) FROM money_submissions ms WHERE $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Получаем записи текущей страницы
$dataSql = "
    SELECT ms.*, c.symbol, w.name AS wallet_name
    FROM money_submissions ms
    JOIN currencies c ON ms.currency_id = c.id
    JOIN wallets w ON ms.wallet_id = w.id
    WHERE $where
    ORDER BY ms.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$approved = $stmt->fetchAll();

// Считаем сумму текущей выборки
$total = 0;
foreach ($approved as $row) {
    $total += $row['amount'];
}

// ===================
// Функция компактной пагинации
// ===================
function pagination($page, $totalPages) {
    $pages = [];

    // Первые 2 страницы
    $pages[] = 1;
    if ($totalPages > 1) $pages[] = 2;

    // Соседние страницы вокруг текущей
    for ($i = $page-1; $i <= $page+1; $i++) {
        if ($i > 2 && $i < $totalPages-1) {
            $pages[] = $i;
        }
    }

    // Последние 2 страницы
    if ($totalPages-1 > 2) $pages[] = $totalPages-1;
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

// Функция для сохранения GET-параметров при переходе страниц
function buildPageLink($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Отчёт</title>
<link rel="stylesheet" href="style/myreports.css">

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

    <!-- Форма фильтра -->
    <form method="get" class="filter-form">
        <div class="form-group">
            <label for="from_date">От</label>
            <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="to_date">До</label>
            <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="description">Описание</label>
            <input type="text" id="description" name="description" placeholder="Поиск по описанию..." value="<?= htmlspecialchars($_GET['description'] ?? '') ?>">
        </div>
        <div class="form-group button-group">
            <label>&nbsp;</label>
            <button type="submit" class="back-button">🧮Фильтр</button>
            
        </div>
    </form>

    <!-- Пагинация сверху -->
    <div class="pagination">
        <a href="<?= buildPageLink(max(1,$page-1)) ?>" class="<?= $page==1?'disabled':'' ?>">‹</a>
        <?php foreach($pagesToShow as $p): ?>
            <?php if($p==='...'): ?>
                <span>…</span>
            <?php else: ?>
                <a href="<?= buildPageLink($p) ?>" class="<?= $p==$page?'active':'' ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a href="<?= buildPageLink(min($totalPages,$page+1)) ?>" class="<?= $page==$totalPages?'disabled':'' ?>">›</a>
    </div>

    <!-- Таблица отчёта -->
    <table>
    <thead>
        <tr>
            <th>Кошелек</th>
            <th>Сумма</th>
            <th>Валюта</th>
            <th>Описание</th>
            <th>Дата сдачи</th>
        </tr>
    </thead>
    <tbody>
        <?php if(count($approved)===0): ?>
            <tr><td colspan="5" style="text-align:center;">Нет данных для отображения</td></tr>
        <?php else: ?>
            <?php foreach($approved as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['wallet_name']) ?></td>
                    <td><?= number_format($row['amount'],2) ?> <?= $row['symbol'] ?></td>
                    <td><?= htmlspecialchars($row['symbol']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Пустая строка -->
            <tr><td colspan="5" style="height: 1rem;"></td></tr>

            <!-- Итоговая строка -->
            <tr style="background-color: #f3f4f6; font-weight:bold;">
                <td>Итого:</td>
                <td><?= number_format($total,2) ?> <?= $approved[0]['symbol'] ?? '' ?></td>
                <td colspan="3"></td>
            </tr>
        <?php endif; ?>
    </tbody>
    </table>
</div>

</body>
</html>
