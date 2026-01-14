<?php
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Удаление кошелька (если нет транзакций)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $wallet_id = (int)$_GET['delete'];
    
    // Проверяем, есть ли транзакции с этим кошельком
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE wallet_id = ?");
    $stmt->execute([$wallet_id]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $pdo->prepare("DELETE FROM wallets WHERE id = ?");
        $stmt->execute([$wallet_id]);
        $message = "Кошелек успешно удален";
    } else {
        $error = "Нельзя удалить кошелек, к которому привязаны транзакции";
    }
}

// Получаем список всех кошельков
$stmt = $pdo->query("SELECT * FROM wallets ORDER BY name");
$wallets = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление кошельками</title>
    <link rel="stylesheet" href="style/dashboard.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 30px auto;
        }
        .wallet-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .type-0 { background: #e3f2fd; color: #1565c0; } /* Обычный */
        .type-1 { background: #fff3e0; color: #ef6c00; } /* Валютный */
        .type-2 { background: #e8f5e9; color: #2e7d32; } /* Банковский */
        .actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="flex">
            <a href="dashboard.php" class="return-link">⬅ Назад в панель</a>
            <a href="add_wallet.php" class="btn btn-admin">➕ Добавить кошелек</a>
            <h2>📋 Управление кошельками</h2>
        </div>

        <?php if (isset($message)): ?>
            <div class="card" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="card" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Тип</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wallets as $wallet): 
                        $type_text = '';
                        $type_class = '';
                        switch ($wallet['exclude_from_total']) {
                            case 0:
                                $type_text = 'Обычный';
                                $type_class = 'type-0';
                                break;
                            case 1:
                                $type_text = 'Валютный';
                                $type_class = 'type-1';
                                break;
                            case 2:
                                $type_text = 'Банковский';
                                $type_class = 'type-2';
                                break;
                        }
                    ?>
                    <tr>
                        <td><?= $wallet['id'] ?></td>
                        <td><strong><?= htmlspecialchars($wallet['name']) ?></strong></td>
                        <td><?= htmlspecialchars($wallet['description'] ?? '') ?></td>
                        <td><span class="wallet-type <?= $type_class ?>"><?= $type_text ?></span></td>
                        <td><?= $wallet['created_at'] ?></td>
                        <td class="actions">
                            <a href="edit_wallet.php?id=<?= $wallet['id'] ?>" class="btn btn-small">✏️</a>
                            <?php if ($wallet['id'] > 4): // Не удалять системные кошельки ?>
                                <a href="?delete=<?= $wallet['id'] ?>" 
                                   class="btn btn-small btn-red"
                                   onclick="return confirm('Удалить кошелек <?= htmlspecialchars($wallet['name']) ?>?')">🗑️</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>