<?php
require_once 'config.php';
require_once 'save.php';
$pdo = getDBConnection();

// HTTP auth (как в примере)
if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' || md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin"');
    exit('Auth required');
}

$message = '';
$editingId = null;
$editData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int)$_POST['id'];
    $data = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'email' => $_POST['email'],
        'birth_date' => $_POST['birth_date'],
        'gender' => $_POST['gender'],
        'languages' => $_POST['languages'] ?? [],
        'bio' => $_POST['bio'],
        'contract_agreed' => 1
    ];
    try {
        updateApplication($id, $data, $pdo);
        $message = "Сохранено";
        $editData = getApplicationById($id, $pdo);
    } catch (Exception $e) {
        $message = "Ошибка: " . $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $editingId = (int)$_GET['edit'];
    $editData = getApplicationById($editingId, $pdo);
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Edit</title></head>
<body>
<h1>Редактирование заявки</h1>
<?php if ($message): ?><p><strong><?= $message ?></strong></p><?php endif; ?>
<?php if ($editData): ?>
<form method="POST">
    <input type="hidden" name="id" value="<?= $editingId ?>">
    <input type="text" name="full_name" value="<?= htmlspecialchars($editData['full_name']) ?>"><br>
    <input type="text" name="phone" value="<?= htmlspecialchars($editData['phone']) ?>"><br>
    <input type="email" name="email" value="<?= htmlspecialchars($editData['email']) ?>"><br>
    <input type="date" name="birth_date" value="<?= htmlspecialchars($editData['birth_date']) ?>"><br>
    <select name="gender">
        <option value="male" <?= $editData['gender']=='male'?'selected':'' ?>>Муж</option>
        <option value="female" <?= $editData['gender']=='female'?'selected':'' ?>>Жен</option>
    </select><br>
    <select name="languages[]" multiple>
        <?php 
        $langs = $pdo->query("SELECT name FROM programming_languages")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($langs as $lang): 
            $sel = in_array($lang, $editData['languages']) ? 'selected' : '';
        ?>
            <option value="<?= $lang ?>" <?= $sel ?>><?= $lang ?></option>
        <?php endforeach; ?>
    </select><br>
    <textarea name="bio"><?= htmlspecialchars($editData['bio']) ?></textarea><br>
    <button type="submit" name="save">Сохранить</button>
</form>
<?php elseif ($editingId): ?>
<p>Заявка не найдена</p>
<?php else: ?>
<p>Выберите заявку для редактирования (передайте ?edit=ID)</p>
<?php endif; ?>
</body>
</html>