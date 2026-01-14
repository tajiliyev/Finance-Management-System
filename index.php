<?php include 'config.php'; ?>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = hash('sha256', $_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Вход</title>
    <link rel="stylesheet" href="style/login.css">
    <!-- Подключаем шрифт из Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="login-container">
    <!-- Логотип -->
    <img src="img/logo.png" alt="Логотип" class="logo" />

    <h2>Вход в аккаунт</h2>
    <form method="post" novalidate>
        <input type="text" name="username" placeholder="Логин" required autocomplete="username" />
        <input type="password" name="password" placeholder="Пароль" required autocomplete="current-password" />
        <button type="submit">Войти</button>
        <?php if (!empty($error)) : ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </form>
</div>

</body>
</html>
