<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
$wallets = $pdo->query("SELECT id, name FROM wallets ORDER BY name")->fetchAll();
$currencies = $pdo->query("SELECT id, name, symbol FROM currencies")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>📊 Админские отчёты</title>
    <link rel="stylesheet" href="style/admin_report.css">
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
        <a href="dashboard.php" class="return-link">⬅ Назад в панель</a>    
        <h2>📊 Генерация отчётов</h2>
            
        </div>

        <form method="get">
            <div class="form-container">
                <div class="form-group">
                    <label for="report_type">Тип отчёта:</label>
                    <select name="report_type" id="report_type" required>
                        <option value="">-- Выберите --</option>
                        <option value="income" <?= @$_GET['report_type'] === 'income' ? 'selected' : '' ?>>Приход</option>
                        <option value="expense" <?= @$_GET['report_type'] === 'expense' ? 'selected' : '' ?>>Расход</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="user_id">Сотрудник:</label>
                    <select name="user_id" id="user_id">
                        <option value="">-- Все --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= @$_GET['user_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="wallet_id">Кошелёк:</label>
                    <select name="wallet_id" id="wallet_id">
                        <option value="">-- Все --</option>
                        <?php foreach ($wallets as $w): ?>
                            <option value="<?= $w['id'] ?>" <?= @$_GET['wallet_id'] == $w['id'] ? 'selected' : '' ?>><?= htmlspecialchars($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="currency_id">Валюта:</label>
                    <select name="currency_id" id="currency_id">
                        <option value="">-- Все --</option>
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= @$_GET['currency_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?> (<?= $c['symbol'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Описание содержит:</label>
                    <input type="text" name="description" id="description" value="<?= htmlspecialchars(@$_GET['description'] ?? '') ?>" />
                </div>

                <div class="form-group">
                    <label for="from_date">Дата от:</label>
                    <input type="date" name="from_date" id="from_date" value="<?= @$_GET['from_date'] ?>" />
                </div>

                <div class="form-group">
                    <label for="to_date">Дата до:</label>
                    <input type="date" name="to_date" id="to_date" value="<?= @$_GET['to_date'] ?>" />
                </div>

                <div class="form-group form-group-full" style="display: flex; gap: 20px;">
    <button type="submit" style="flex: 1;">🔍 Сформировать</button>
    <button 
        type="submit" 
        formaction="export_excel.php"
        class="excel-button"
        style="flex: 1;"
    >
        📥 Экспорт в Excel
    </button>
</div>
            </div>
        </form>

        <hr />

        <?php
        if (!empty($_GET['report_type'])) {
            $type = $_GET['report_type'];
            $where = [];
            $params = [];

            if (!empty($_GET['user_id'])) {
                $where[] = ($type === 'submission' ? 'ms.user_id = ?' : 't.user_id = ?');
                $params[] = $_GET['user_id'];
            }
            if (!empty($_GET['wallet_id'])) {
                $where[] = ($type === 'submission' ? 'ms.wallet_id = ?' : 't.wallet_id = ?');
                $params[] = $_GET['wallet_id'];
            }
            if (!empty($_GET['currency_id'])) {
                $where[] = ($type === 'submission' ? 'ms.currency_id = ?' : 't.currency_id = ?');
                $params[] = $_GET['currency_id'];
            }
            if (!empty($_GET['from_date'])) {
                $where[] = ($type === 'submission' ? 'DATE(ms.created_at) >= ?' : 'DATE(t.created_at) >= ?');
                $params[] = $_GET['from_date'];
            }
            if (!empty($_GET['description'])) {
                $where[] = ($type === 'submission' ? 'ms.description LIKE ?' : 't.description LIKE ?');
                $params[] = '%' . $_GET['description'] . '%';
            }
            if (!empty($_GET['to_date'])) {
                $where[] = ($type === 'submission' ? 'DATE(ms.created_at) <= ?' : 'DATE(t.created_at) <= ?');
                $params[] = $_GET['to_date'];
            }

            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            if ($type === 'submission') {
                $stmt = $pdo->prepare("
                    SELECT ms.*, u.username, w.name AS wallet_name, c.name AS currency_name, c.symbol
                    FROM money_submissions ms
                    JOIN users u ON ms.user_id = u.id
                    JOIN wallets w ON ms.wallet_id = w.id
                    JOIN currencies c ON ms.currency_id = c.id
                    $whereSQL
                    ORDER BY ms.created_at DESC
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT t.*, u.username, w.name AS wallet_name, c.name AS currency_name, c.symbol
                    FROM transactions t
                    JOIN users u ON t.user_id = u.id
                    JOIN wallets w ON t.wallet_id = w.id
                    JOIN currencies c ON t.currency_id = c.id
                    WHERE t.type = ? " . ($where ? 'AND ' . implode(' AND ', $where) : '') . "
                    ORDER BY t.created_at DESC
                ");
                array_unshift($params, $type);
            }

            $stmt->execute($params);
            $results = $stmt->fetchAll();

            if ($results): ?>
                <h3 class="text-xl font-semibold mb-4">
                    Результаты отчёта: 
                    <?= $type === 'income' ? 'Приход' : ($type === 'expense' ? 'Расход' : 'Сдача денег') ?>
                </h3>

                <table>
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Пользователь</th>
                            <th>Кошелёк</th>
                            <th>Валюта</th>
                            <th>Сумма</th>
                            <th>Описание</th>
                            <?php if ($type === 'submission'): ?>
                                <th>Статус</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totals = [];
                        foreach ($results as $r) {
                            $key = $r['currency_name'] . ' (' . $r['symbol'] . ')';
                            if (!isset($totals[$key])) $totals[$key] = 0;
                            $totals[$key] += $r['amount'];
                        }
                        foreach ($results as $r): ?>
                            <tr>
                                <td><?= $r['created_at'] ?></td>
                                <td><?= htmlspecialchars($r['username']) ?></td>
                                <td><?= htmlspecialchars($r['wallet_name']) ?></td>
                                <td><?= htmlspecialchars($r['currency_name']) ?> (<?= $r['symbol'] ?>)</td>
                                <td><?= number_format($r['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($r['description']) ?></td>
                                <?php if ($type === 'submission'): ?>
                                    <td>
                                        <?php if ($r['status'] === 'approved'): ?>
                                            <span class="status-approved">Одобрено</span>
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            <span class="status-rejected">Отклонено</span>
                                        <?php else: ?>
                                            <span class="status-pending">Ожидает</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr class="total-row">
                            <td colspan="<?= $type === 'submission' ? 6 : 5 ?>" class="total-cell">Итого:</td>
                            <td class="total-cell">
                                <?php foreach ($totals as $currency => $sum): ?>
                                    <?= number_format($sum, 2) ?> <?= $currency ?><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

            <?php else: ?>
                <p>Нет данных по выбранным фильтрам.</p>
            <?php endif;
        }
        ?>
    </div>
</body>
</html>




