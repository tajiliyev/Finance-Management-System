<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$wallet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем данные кошелька
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id = ?");
$stmt->execute([$wallet_id]);
$wallet = $stmt->fetch();

if (!$wallet) {
    header("Location: manage_wallets.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $exclude_from_total = isset($_POST['exclude_from_total']) ? (int)$_POST['exclude_from_total'] : 0;

    if (empty($name)) {
        $error = "Название кошелька обязательно";
    } else {
        // Проверяем, нет ли другого кошелька с таким именем
        $stmt = $pdo->prepare("SELECT id FROM wallets WHERE name = ? AND id != ?");
        $stmt->execute([$name, $wallet_id]);
        
        if ($stmt->fetch()) {
            $error = "Кошелек с таким названием уже существует";
        } else {
            // Обновляем кошелек
            $stmt = $pdo->prepare("UPDATE wallets SET name = ?, description = ?, exclude_from_total = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $exclude_from_total, $wallet_id])) {
                $message = "Кошелек успешно обновлен!";
                // Обновляем данные в переменной
                $wallet['name'] = $name;
                $wallet['description'] = $description;
                $wallet['exclude_from_total'] = $exclude_from_total;
            } else {
                $error = "Ошибка при обновлении кошелька";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать кошелек</title>
    <link rel="stylesheet" href="style/dashboard.css">
    <style>
        .container { max-width: 600px; margin: 30px auto; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input[type="text"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="flex">
            <a href="manage_wallets.php" class="return-link">⬅ Назад</a>
            <h2>✏️ Редактировать кошелек</h2>
        </div>

        <?php if ($message): ?>
            <div class="card" style="background-color: #d4edda; color: #155724;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="card" style="background-color: #f8d7da; color: #721c24;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Название:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($wallet['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Описание:</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($wallet['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Тип:</label>
                <select name="exclude_from_total">
                    <option value="0" <?= $wallet['exclude_from_total'] == 0 ? 'selected' : '' ?>>Обычный</option>
                    <option value="1" <?= $wallet['exclude_from_total'] == 1 ? 'selected' : '' ?>>Валютный</option>
                    <option value="2" <?= $wallet['exclude_from_total'] == 2 ? 'selected' : '' ?>>Банковский</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-admin">💾 Сохранить изменения</button>
        </form>
    </div>
</body>
</html>