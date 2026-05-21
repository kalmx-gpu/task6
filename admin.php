<?php

require_once 'config.php';
require_once 'save.php';
require_once 'validators.php';

$pdo = getDBConnection();

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('Требуется авторизация.');
}

$stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE login = ?");
$stmt->execute([$_SERVER['PHP_AUTH_USER']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('Неверный логин или пароль.');
}

$message = '';
$msgType = 'success';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$delId]);
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$delId]);
        $pdo->commit();
        header("Location: admin.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Ошибка при удалении: ' . htmlspecialchars($e->getMessage());
        $msgType = 'error';
    }
}

$editErrors = [];
$editData = null;
$editingId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_edit') {
    $editingId = (int)$_POST['id'];
    $editData = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'gender' => $_POST['gender'] ?? '',
        'languages' => $_POST['languages'] ?? [],
        'bio' => trim($_POST['bio'] ?? ''),
        'contract_agreed' => 1
    ];

    $editErrors = validateAllFields($editData);
    if (empty($editErrors)) {
        try {
            updateApplication($editingId, $editData, $pdo);
            header("Location: admin.php?msg=saved");
            exit;
        } catch (Exception $e) {
            $message = 'Ошибка сохранения: ' . htmlspecialchars($e->getMessage());
            $msgType = 'error';
        }
    }
}

if (!empty($editErrors)) {
    $editingId = $editingId ?? (int)($_POST['id'] ?? 0);
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editingId = (int)$_GET['edit'];
    $editData = getApplicationById($editingId, $pdo);
    if (!$editData) $editingId = null;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = 'Запись успешно удалена.';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $message = 'Данные успешно обновлены.';
}

$apps = $pdo->query("SELECT id, full_name, email, birth_date FROM applications ORDER BY id DESC")->fetchAll();
$langMap = [];
if ($apps) {
    $ids = array_column($apps, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT al.application_id, pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id IN ($placeholders)");
    $stmt->execute($ids);
    while ($row = $stmt->fetch()) {
        $langMap[$row['application_id']][] = $row['name'];
    }
}

$stats = $pdo->query("
    SELECT pl.name, COUNT(al.application_id) as cnt 
    FROM programming_languages pl 
    LEFT JOIN application_languages al ON pl.id = al.language_id 
    GROUP BY pl.id 
    ORDER BY cnt DESC
")->fetchAll();

$langsStmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
$allLangs = $langsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #fff; border-radius: 12px; overflow: hidden; }
        .admin-table th, .admin-table td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 0.9rem; }
        .admin-table th { background: #f8fafc; font-weight: 600; color: #334155; }
        .btn-action { display: inline-block; padding: 6px 12px; font-size: 0.8rem; border-radius: 20px; text-decoration: none; color: #fff; margin-right: 5px; cursor: pointer; border: none; }
        .btn-del { background: #ef4444; }
        .btn-edit { background: #f59e0b; }
        .btn-cancel { background: #6b7280; }
        .edit-form { background: #fff; padding: 25px; border-radius: 16px; margin-top: 20px; border: 1px solid #3b82f6; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-top: 15px; }
        .stat-card { background: #fff; padding: 12px; border-radius: 10px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .stat-num { font-size: 1.5rem; font-weight: bold; color: #2563eb; }
        select[multiple] { height: 120px; }
    </style>
</head>
<body>
    <div class="glass-container">
        <div class="form-card">
            <h1>Панель администратора</h1>
            <?php if ($message): ?>
                <div class="<?= $msgType === 'success' ? 'success-message' : 'error-message' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <h2>Заявки пользователей</h2>
            <?php if (empty($apps)): ?>
                <p style="text-align:center; color:#6b7280; padding: 20px 0;">Заявок пока нет.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>ФИО</th><th>Email</th><th>Языки</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($apps as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td><?= htmlspecialchars(implode(', ', $langMap[$app['id']] ?? ['Не выбрано'])) ?></td>
                            <td>
                                <a href="?edit=<?= $app['id'] ?>" class="btn-action btn-edit">✏️ Ред.</a>
                                <a href="?action=delete&id=<?= $app['id'] ?>" class="btn-action btn-del" onclick="return confirm('Удалить заявку #<?= $app['id'] ?>?')">🗑 Удал.</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Статистика по языкам</h2>
            <div class="stats-grid">
                <?php foreach ($stats as $s): ?>
                    <div class="stat-card">
                        <div><?= htmlspecialchars($s['name']) ?></div>
                        <div class="stat-num"><?= $s['cnt'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($editingId): ?>
            <div class="edit-form">
                <h3>Редактирование заявки #<?= $editingId ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_edit">
                    <input type="hidden" name="id" value="<?= $editingId ?>">
                    <div class="form-group">
                        <label>ФИО <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($editData['full_name']) ?>" class="<?= isset($editErrors['full_name']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['full_name'])): ?><div class="field-error">ФИО должно содержать только буквы, пробелы и дефис</div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Телефон <span class="required">*</span></label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($editData['phone']) ?>" class="<?= isset($editErrors['phone']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['phone'])): ?><div class="field-error">Телефон должен содержать 11 цифр, начиная с 7 или 8</div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" value="<?= htmlspecialchars($editData['email']) ?>" class="<?= isset($editErrors['email']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['email'])): ?><div class="field-error">Введите корректный email</div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Дата рождения <span class="required">*</span></label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($editData['birth_date']) ?>" class="<?= isset($editErrors['birth_date']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['birth_date'])): ?><div class="field-error">Некорректная дата</div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Пол</label>
                        <div class="radio-group">
                            <label><input type="radio" name="gender" value="male" <?= ($editData['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                            <label><input type="radio" name="gender" value="female" <?= ($editData['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Языки <span class="required">*</span></label>
                        <select name="languages[]" multiple class="<?= isset($editErrors['languages']) ? 'error' : '' ?>">
                            <?php foreach ($allLangs as $l): 
                                $sel = in_array($l['name'], $editData['languages'] ?? []) ? 'selected' : '';
                                echo "<option value=\"{$l['name']}\" $sel>{$l['name']}</option>";
                            endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Биография</label>
                        <textarea name="bio" rows="3" class="<?= isset($editErrors['bio']) ? 'error' : '' ?>"><?= htmlspecialchars($editData['bio']) ?></textarea>
                    </div>
                    <button type="submit">Сохранить изменения</button>
                    <div style="margin-top:10px; text-align:center;">
                        <a href="admin.php" class="btn-action btn-cancel">Отмена</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>