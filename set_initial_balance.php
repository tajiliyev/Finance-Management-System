<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Проверка: уже задано?
$stmt = $pdo->query("SELECT amount FROM initial_balance WHERE id = 1");
$row = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$row) {
    $amount = floatval($_POST['amount']);
    $stmt = $pdo->prepare("INSERT INTO initial_balance (id, amount) VALUES (1, ?)");
    $stmt->execute([$amount]);
    header("Location: dashboard.php");
    exit;
}
?>

<h2>Установка начального сальдо</h2>

<?php if ($row): ?>
    <p>Начальное сальдо уже задано: <strong><?= number_format($row['amount'], 2) ?> TMT</strong></p>
<?php else: ?>
    <form method="post">
        <label>Введите начальное сальдо (TMT):</label>
        <input type="number" step="0.01" name="amount" required>
        <button type="submit">Сохранить</button>
    </form>
<?php endif; ?>

<a href="dashboard.php">⬅ Назад</a>
