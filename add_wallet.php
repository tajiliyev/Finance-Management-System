<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $exclude_from_total = isset($_POST['exclude_from_total']) ? (int)$_POST['exclude_from_total'] : 0;

    // Валидация
    if (empty($name)) {
        $error = "Название кошелька обязательно";
    } else {
        // Проверяем, нет ли уже кошелька с таким именем
        $stmt = $pdo->prepare("SELECT id FROM wallets WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->fetch()) {
            $error = "Кошелек с таким названием уже существует";
        } else {
            // Добавляем кошелек
            $stmt = $pdo->prepare("INSERT INTO wallets (name, description, exclude_from_total) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $description, $exclude_from_total])) {
                $message = "Кошелек успешно добавлен!";
                // Очищаем поля формы
                $_POST = [];
            } else {
                $error = "Ошибка при добавлении кошелька";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить кошелек</title>
    <link rel="stylesheet" href="style/dashboard.css">
    <style>
        .container {
            max-width: 600px;
            margin: 30px auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .radio-option input[type="radio"] {
            width: auto;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="flex">
            <a href="dashboard.php" class="return-link">⬅ Назад в панель</a>
            <a href="manage_wallets.php" class="btn btn-purple">📋 Управление кошельками</a>
            <h2>➕ Добавить кошелек</h2>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Название кошелька:</label>
                <input type="text" id="name" name="name" 
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                       required 
                       placeholder="Например: Основной кошелек">
            </div>

            <div class="form-group">
                <label for="description">Описание (необязательно):</label>
                <textarea id="description" name="description" 
                          placeholder="Краткое описание назначения кошелька"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Учет в общем балансе:</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="exclude_from_total" value="0" 
                               <?= (!isset($_POST['exclude_from_total']) || $_POST['exclude_from_total'] == 0) ? 'checked' : '' ?>>
                        Включить в общий баланс
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="exclude_from_total" value="1"
                               <?= (isset($_POST['exclude_from_total']) && $_POST['exclude_from_total'] == 1) ? 'checked' : '' ?>>
                        Исключить из общего баланса (валютный)
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="exclude_from_total" value="2"
                               <?= (isset($_POST['exclude_from_total']) && $_POST['exclude_from_total'] == 2) ? 'checked' : '' ?>>
                        Банковский счет
                    </label>
                </div>
                <small style="color: #666; display: block; margin-top: 5px;">
                    • "Включить в общий баланс" - обычный кошелек<br>
                    • "Исключить из общего баланса" - для валютных операций<br>
                    • "Банковский счет" - для банковских переводов
                </small>
            </div>

            <button type="submit" class="btn btn-admin" style="width: 100%; margin-top: 20px;">
                💾 Сохранить кошелек
            </button>
        </form>
    </div>
</body>
</html>