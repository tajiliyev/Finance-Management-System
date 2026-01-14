<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if ($username && $password && in_array($role, ['admin', 'cashier', 'manager'])) {
        $hash = hash('sha256', $password);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$username, $hash, $role]);
            $message = "Пользователь добавлен.";
        } catch (PDOException $e) {
            $error = "Ошибка: возможно пользователь с таким именем уже существует.";
        }
    } else {
        $error = "Заполните все поля корректно.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $password = $_POST['password'];
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;

    if (in_array($role, ['admin', 'cashier', 'manager'])) {
        try {
            if ($password && $username) {
                $hash = hash('sha256', $password);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $role, $hash, $user_id]);
            } elseif ($password) {
                $hash = hash('sha256', $password);
                $stmt = $pdo->prepare("UPDATE users SET role = ?, password = ? WHERE id = ?");
                $stmt->execute([$role, $hash, $user_id]);
            } elseif ($username) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$role, $user_id]);
            }
            $message = "Данные пользователя обновлены.";
        } catch (PDOException $e) {
            $error = "Ошибка при обновлении данных: возможно пользователь с таким именем уже существует.";
        }
    } else {
        $error = "Неверная роль.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    if ($user_id !== $_SESSION['user']['id']) { // Запрещаем удалять самого себя
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Пользователь удален.";
    } else {
        $error = "Вы не можете удалить себя.";
    }
}

$users = $pdo->query("SELECT id, username, role FROM users ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="style/users.css ">
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
        <a href="dashboard.php" class="btn-back">⬅ Вернуться в панель</a>    
        <h2>👥 Управление пользователями</h2>
            
        </div>

        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <h3>➕ Добавить нового пользователя</h3>
        <form method="post">
            <input type="hidden" name="add_user" value="1">
            <div class="form-group">
                <label for="username_add">Логин:</label>
                <input type="text" id="username_add" name="username" required>
            </div>
            <div class="form-group">
                <label for="password_add">Пароль:</label>
                <input type="password" id="password_add" name="password" required>
            </div>
            <div class="form-group">
                <label for="role_add">Роль:</label>
                <select id="role_add" name="role" class="role-select">
                    <option value="cashier">Кассир</option>
                    <option value="manager">Бухгалтер</option>
                    <option value="admin">Админ</option>
                </select>
            </div>
            <button type="submit">Добавить</button>
        </form>

        <h3>📋 Существующие пользователи</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Изменить</th>
                    <th>Удалить</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <form method="post" style="margin:0;">
                            <td><?= $user['id'] ?></td>
                            <td>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </td>
                            <td>
                                <select name="role" class="role-select" required>
                                    <option value="cashier" <?= $user['role'] === 'cashier' ? 'selected' : '' ?>>Кассир</option>
                                    <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Бухгалтер</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Админ</option>
                                </select>
                            </td>
                            <td>
                                <input type="password" name="password" placeholder="Новый пароль (если нужен)">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="edit_user" value="1">
                                <button type="submit" class="update-btn">Обновить</button>
                            </td>
                        </form>
                        <td>
                            <form method="post" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');" style="margin:0;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="delete-btn">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>
</html>
