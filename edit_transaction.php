<?php
include 'config.php';

// --- Проверка авторизации ---
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// --- Проверка прав доступа ---
if ($_SESSION['user']['role'] !== 'admin') {
    echo "<h3 style='color:red; text-align:center;'>❌ Доступ запрещён. Только администратор может редактировать транзакции.</h3>";
    exit;
}

// --- AJAX: получение данных по ID ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($transaction ?: []);
    exit;
}

// --- AJAX: обновление данных ---
if (isset($_POST['ajax']) && $_POST['ajax'] === 'update') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $wallet_id = (int)($_POST['wallet_id'] ?? 0);
    $currency_id = (int)($_POST['currency_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($id && in_array($type, ['income','expense']) && $wallet_id && $currency_id && $amount > 0) {
        $stmt = $pdo->prepare("
            UPDATE transactions
            SET wallet_id = ?, currency_id = ?, type = ?, amount = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$wallet_id, $currency_id, $type, $amount, $description, $id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
    }
    exit;
}

// --- AJAX: удаление транзакции ---
if (isset($_POST['ajax']) && $_POST['ajax'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Транзакция не найдена']);
    }
    exit;
}

// --- Получаем список транзакций ---
$transactions = $pdo->query("
    SELECT t.id, t.description, w.name AS wallet, c.name AS currency, t.amount
    FROM transactions t
    JOIN wallets w ON w.id = t.wallet_id
    JOIN currencies c ON c.id = t.currency_id
    ORDER BY t.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$wallets = $pdo->query("SELECT * FROM wallets ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$currencies = $pdo->query("SELECT * FROM currencies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Редактировать транзакцию</title>
<link rel="stylesheet" href="style/add_transaction.css">
<style>
.container { max-width: 700px; margin: 30px auto; background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
select, input, textarea, button { width: 100%; margin-top: 8px; margin-bottom: 15px; padding: 8px; border-radius: 8px; border: 1px solid #ccc; }
button { background: #4CAF50; color: white; cursor: pointer; transition: 0.3s; }
button:hover { background: #45a049; }
button.delete { background: #e74c3c; }
button.delete:hover { background: #c0392b; }
.notification { background-color:#d1ffd1; color:#006600; padding:10px 20px; border-radius:10px; text-align:center; margin-bottom:10px; display:none; }
.flex { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
#searchInput { padding: 8px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 10px; }
.highlight { background-color: yellow; }
</style>
</head>
<body>
<div class="container">
<div class="flex">
<a href="dashboard.php" class="return-link">⬅ Вернуться</a>
<h2>✏️ Редактировать / Удалить</h2>
</div>

<div id="notif" class="notification">✅ Действие выполнено успешно</div>

<!-- Поиск -->
<input type="text" id="searchInput" placeholder="🔍 Поиск транзакции по описанию">

<!-- Выбор транзакции -->
<label for="transactionSelect">Выберите транзакцию:</label>
<select id="transactionSelect" size="10">
    <?php foreach ($transactions as $t): ?>
        <option value="<?= $t['id'] ?>">
            #<?= $t['id'] ?> — <?= htmlspecialchars($t['description']) ?> (<?= htmlspecialchars($t['wallet']) ?>, <?= htmlspecialchars($t['currency']) ?>, <?= htmlspecialchars($t['amount']) ?>)
        </option>
    <?php endforeach; ?>
</select>

<!-- Форма редактирования -->
<form id="editForm" style="display:none;">
    <input type="hidden" name="id" id="id">
    <label>Тип:</label>
    <select name="type" id="type" required>
        <option value="income">Приход</option>
        <option value="expense">Расход</option>
    </select>

    <label>Кошелёк:</label>
    <select name="wallet_id" id="wallet_id" required>
        <?php foreach ($wallets as $w): ?>
            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Валюта:</label>
    <select name="currency_id" id="currency_id" required>
        <?php foreach ($currencies as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Сумма:</label>
    <input type="number" step="0.01" name="amount" id="amount" required>

    <label>Описание:</label>
    <textarea name="description" id="description" rows="3" required></textarea>

    <div class="flex">
        <button type="submit">💾 Сохранить изменения</button>
        <button type="button" class="delete" id="deleteBtn">🗑 Удалить</button>
    </div>
</form>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const transactionSelect = document.getElementById('transactionSelect');
    const form = document.getElementById('editForm');
    const notif = document.getElementById('notif');
    const searchInput = document.getElementById('searchInput');
    const deleteBtn = document.getElementById('deleteBtn');

    // --- Поиск / фильтр + подсветка ---
    searchInput.addEventListener('input', () => {
        const filter = searchInput.value.toLowerCase();
        Array.from(transactionSelect.options).forEach(option => {
            const text = option.text.toLowerCase();
            option.style.display = text.includes(filter) ? '' : 'none';
            option.innerHTML = option.text.replace(new RegExp(filter, 'gi'), match => `<span class="highlight">${match}</span>`);
        });
    });

    // --- Выбор транзакции ---
    transactionSelect.addEventListener('change', async function() {
        const id = this.value;
        if (!id) { form.style.display='none'; return; }

        try {
            const response = await fetch('<?= $_SERVER['PHP_SELF'] ?>?ajax=get&id='+id);
            const data = await response.json();
            if (data && data.id) {
                form.style.display = 'block';
                document.getElementById('id').value = data.id;
                document.getElementById('type').value = data.type;
                document.getElementById('wallet_id').value = data.wallet_id;
                document.getElementById('currency_id').value = data.currency_id;
                document.getElementById('amount').value = data.amount;
                document.getElementById('description').value = data.description;
            }
        } catch(e) { console.error(e); alert('Ошибка загрузки данных'); }
    });

    // --- Сохранение изменений с подтверждением ---
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!confirm('Вы уверены, что хотите сохранить изменения в этой транзакции?')) return;

        const formData = new FormData(this);
        formData.append('ajax','update');

        try {
            const response = await fetch('<?= $_SERVER['PHP_SELF'] ?>', { method:'POST', body:formData });
            const result = await response.json();
            if (result.success) {
                alert('✅ Изменения сохранены');
                location.reload(); // Перезагружаем страницу после сохранения
            } else { 
                alert(result.error || 'Ошибка при сохранении'); 
            }
        } catch(e){ 
            console.error(e); 
            alert('Ошибка при отправке'); 
        }
    });

    // --- Удаление транзакции ---
    deleteBtn.addEventListener('click', async () => {
        if (!confirm('Вы уверены, что хотите удалить эту транзакцию?')) return;
        const id = document.getElementById('id').value;
        const formData = new FormData();
        formData.append('ajax','delete');
        formData.append('id', id);

        try {
            const response = await fetch('<?= $_SERVER['PHP_SELF'] ?>', { method:'POST', body:formData });
            const result = await response.json();
            if (result.success) {
                notif.textContent = '✅ Транзакция удалена';
                notif.style.display='block';
                setTimeout(()=>notif.style.display='none',3000);
                form.style.display='none';
                Array.from(transactionSelect.options).forEach(option => { if(option.value==id) option.remove(); });
            } else { 
                alert(result.error || 'Ошибка при удалении'); 
            }
        } catch(e){ console.error(e); alert('Ошибка при удалении'); }
    });
});
</script>
</body>
</html>
