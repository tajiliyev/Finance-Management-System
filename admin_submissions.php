<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ====================
// Обработка утверждения / отклонения
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = (int)$_POST['submission_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare("SELECT * FROM money_submissions WHERE id = ? AND status = 'pending'");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch();

    if ($submission) {
        if ($action === 'approve') {
            $description = $submission['description'];

            // Добавляем в транзакции
            $stmtIns = $pdo->prepare("
                INSERT INTO transactions (user_id, wallet_id, currency_id, amount, type, description, created_at)
                VALUES (?, ?, ?, ?, 'income', ?, NOW())
            ");
            $stmtIns->execute([
                $submission['user_id'],
                $submission['wallet_id'],
                $submission['currency_id'],
                $submission['amount'],
                $description
            ]);

            // Обновляем статус
            $stmtUp = $pdo->prepare("
                UPDATE money_submissions
                SET status = 'approved', approved_at = NOW(), approved_by = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$_SESSION['user']['id'], $submission_id]);
        } elseif ($action === 'reject') {
            $stmtUp = $pdo->prepare("
                UPDATE money_submissions
                SET status = 'rejected', approved_at = NOW(), approved_by = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$_SESSION['user']['id'], $submission_id]);
        }
    }
}

// ====================
// Пагинация
// ====================
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Подсчёт всех строк
$totalRows = $pdo->query("SELECT COUNT(*) FROM money_submissions")->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Получаем нужную страницу
$sql = "
    SELECT ms.*, u.username, w.name AS wallet_name, c.name AS currency_name, c.symbol
    FROM money_submissions ms
    JOIN users u ON ms.user_id = u.id
    JOIN wallets w ON ms.wallet_id = w.id
    JOIN currencies c ON ms.currency_id = c.id
    ORDER BY ms.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$submissions = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ====================
// Функция компактной пагинации
// ====================
function pagination($page, $totalPages) {
    $pages = [];

    // Первые 2
    $pages[] = 1;
    if ($totalPages > 1) $pages[] = 2;

    // Соседи
    for ($i = $page - 1; $i <= $page + 1; $i++) {
        if ($i > 2 && $i < $totalPages - 1) {
            $pages[] = $i;
        }
    }

    // Последние 2
    if ($totalPages - 1 > 2) $pages[] = $totalPages - 1;
    if ($totalPages > 2) $pages[] = $totalPages;

    $pages = array_unique($pages);
    sort($pages);

    $finalPages = [];
    $prev = 0;
    foreach ($pages as $p) {
        if ($prev && $p - $prev > 1) {
            $finalPages[] = '...';
        }
        $finalPages[] = $p;
        $prev = $p;
    }

    return $finalPages;
}

$pagesToShow = pagination($page, $totalPages);

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
    <title>Утверждение сдачи денег</title>
    <link rel="stylesheet" href="style/admin_submissions.css">
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="return-link">⬅ Вернуться в панель</a>    
            <h2>✅ Заявки на сдачу денег</h2>
        </div>

        <!-- Пагинация сверху -->
        <div class="pagination">
            <a href="<?= buildPageLink(max(1, $page - 1)) ?>" class="<?= $page == 1 ? 'disabled' : '' ?>">‹</a>
            <?php foreach ($pagesToShow as $p): ?>
                <?php if ($p === '...'): ?>
                    <span>…</span>
                <?php else: ?>
                    <a href="<?= buildPageLink($p) ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="<?= buildPageLink(min($totalPages, $page + 1)) ?>" class="<?= $page == $totalPages ? 'disabled' : '' ?>">›</a>
        </div>

        <div class="overflow-x-auto mt-6">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Пользователь</th>
                        <th>Кошелек</th>
                        <th>Валюта</th>
                        <th>Сумма</th>
                        <th>Описание</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr><td colspan="8" style="text-align:center;">Нет данных для отображения</td></tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td><?= $sub['id'] ?></td>
                                <td><?= htmlspecialchars($sub['username']) ?></td>
                                <td><?= htmlspecialchars($sub['wallet_name']) ?></td>
                                <td><?= htmlspecialchars($sub['currency_name']) ?></td>
                                <td><?= number_format($sub['amount'], 2) ?> <?= htmlspecialchars($sub['symbol']) ?></td>
                                <td><?= htmlspecialchars($sub['description']) ?></td>
                                <td>
                                    <?php
                                        if ($sub['status'] === 'approved') {
                                            echo '<span class="status-approved">Одобрено</span>';
                                        } elseif ($sub['status'] === 'rejected') {
                                            echo '<span class="status-rejected">Отклонено</span>';
                                        } else {
                                            echo '<span class="status-pending">Ожидает</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($sub['status'] === 'pending'): ?>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="approve-btn">✔ Подтвердить</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="reject-btn">✖ Отклонить</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Пагинация снизу -->
        <div class="pagination">
            <a href="<?= buildPageLink(max(1, $page - 1)) ?>" class="<?= $page == 1 ? 'disabled' : '' ?>">‹</a>
            <?php foreach ($pagesToShow as $p): ?>
                <?php if ($p === '...'): ?>
                    <span>…</span>
                <?php else: ?>
                    <a href="<?= buildPageLink($p) ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="<?= buildPageLink(min($totalPages, $page + 1)) ?>" class="<?= $page == $totalPages ? 'disabled' : '' ?>">›</a>
        </div>
    </div>
</body>
</html>
