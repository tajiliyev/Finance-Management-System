<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    exit("Нет доступа");
}

// Отправка BOM для UTF-8
echo "\xEF\xBB\xBF";

// Заголовки
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=report_" . date('Y-m-d_H-i-s') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$type = $_GET['report_type'] ?? null;
if (!$type) {
    exit("Тип отчета не указан");
}

// Фильтры
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

// Запрос
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

// Вывод HTML-таблицы с мета-тегом
echo "<html><head><meta charset='UTF-8'></head><body>";
echo "<table border='1'>";
echo "<tr>
        <th>Дата</th>
        <th>Пользователь</th>
        <th>Кошелёк</th>
        <th>Валюта</th>
        <th>Сумма</th>
        <th>Описание</th>";

if ($type === 'submission') {
    echo "<th>Статус</th>";
}
echo "</tr>";

foreach ($results as $r) {
    echo "<tr>";
    echo "<td>{$r['created_at']}</td>";
    echo "<td>" . htmlspecialchars($r['username']) . "</td>";
    echo "<td>" . htmlspecialchars($r['wallet_name']) . "</td>";
    echo "<td>" . htmlspecialchars($r['currency_name']) . " ({$r['symbol']})</td>";
    echo "<td>" . number_format($r['amount'], 2, ',', ' ') . "</td>";
    echo "<td>" . htmlspecialchars($r['description']) . "</td>";

    if ($type === 'submission') {
        $status = $r['status'] === 'approved' ? 'Одобрено' :
                  ($r['status'] === 'rejected' ? 'Отклонено' : 'Ожидает');
        echo "<td>{$status}</td>";
    }
    echo "</tr>";
}
echo "</table>";
echo "</body></html>";
