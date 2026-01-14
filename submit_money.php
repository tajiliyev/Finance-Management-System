<?php
include 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user']['id'];

// Получаем списки кошельков для формы (валюты больше не нужны)
$wallets = $pdo->query("SELECT id, name FROM wallets WHERE id != 4")->fetchAll();


$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wallet_id = (int)$_POST['wallet_id'];
    $currency_id = 1; // фиксированное значение для TMT
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);

    if ($wallet_id && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO money_submissions (user_id, wallet_id, currency_id, amount, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $wallet_id, $currency_id, $amount, $description]);
        $message = "Заявка успешно отправлена! Сейчас вы будете перенаправлены на главную страницу.";
    } else {
        $error = "Пожалуйста, заполните все поля корректно.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>💰 Сдача денег</title>
    <link rel="stylesheet" href="style/submitm.css">
</head>
<body>

<div class="container">
  <div class="header">
    <a href="dashboard.php" class="back-button">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6" />
      </svg>
      <span>Назад</span>
    </a>
    <h2 class="title">Сдача денег 💸</h2>
  </div>

  <?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
    <script>
      setTimeout(() => window.location.href = 'dashboard.php', 3000);
    </script>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label for="wallet_id">Кошелёк:</label>
      <div class="input-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="5" width="18" height="14" rx="2" ry="2" />
          <line x1="16" y1="12" x2="16.01" y2="12" />
        </svg>
        <select name="wallet_id" id="wallet_id" required>
          <?php foreach ($wallets as $w): ?>
            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="amount">Сумма:</label>
      <div class="input-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 1v22" />
          <path d="M17 5H9a4 4 0 000 8h6a4 4 0 010 8H8" />
        </svg>
        <input type="number" step="0.01" name="amount" id="amount" required>
      </div>
    </div>

    <div class="form-group">
      <label for="description">Описание:</label>
      <div class="input-icon textarea-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="16" rx="2" ry="2" />
          <line x1="8" y1="8" x2="16" y2="8" />
          <line x1="8" y1="12" x2="16" y2="12" />
          <line x1="8" y1="16" x2="13" y2="16" />
        </svg>
        <textarea name="description" id="description" rows="4"></textarea>
      </div>
    </div>

    <button type="submit">Отправить заявку</button>
  </form>
</div>


</body>
</html>
