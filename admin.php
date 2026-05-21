<?php
require_once 'config.php';
require_once 'save.php';
require_once 'validators.php';

$pdo = getDBConnection();

if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('Требуется авторизация');
}

$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE login = ?");
$stmt->execute([$_SERVER['PHP_AUTH_USER']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('Неверный логин или пароль');
}

$message = '';
$msgType = 'success';
$editingId = null;
$editData = null;

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $delId = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$delId]);
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$delId]);
        $pdo->commit();
        header("Location: admin.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $message = 'Ошибка удаления: ' . htmlspecialchars($e->getMessage());
        $msgType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_edit') {
    $editingId = (int)$_POST['id'];
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'gender' => $_POST['gender'] ?? '',
        'languages' => $_POST['languages'] ?? [],
        'bio' => trim($_POST['bio'] ?? ''),
        'contract_agreed' => 1
    ];
    $errors = validateAllFields($data);
    if (empty($errors)) {
        try {
            updateApplication($editingId, $data, $pdo);
            header("Location: admin.php?msg=saved");
            exit;
        } catch (Exception $e) {
            $message = 'Ошибка сохранения: ' . htmlspecialchars($e->getMessage());
            $msgType = 'error';
        }
    } else {
        $message = 'Исправьте ошибки в форме';
        $msgType = 'error';
        $editData = $data;
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit']) && !$editData) {
    $editingId = (int)$_GET['edit'];
    $editData = getApplicationById($editingId, $pdo);
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'] === 'deleted' ? 'Запись успешно удалена' : ($_GET['msg'] === 'saved' ? 'Данные успешно обновлены' : $message);
}

$applications = $pdo->query("SELECT id, full_name, email, birth_date, created_at FROM applications ORDER BY id DESC")->fetchAll();

if ($applications) {
    $ids = array_column($applications, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $langStmt = $pdo->prepare("SELECT al.application_id, pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id IN ($placeholders)");
    $langStmt->execute($ids);
    $langMap = [];
    while ($row = $langStmt->fetch()) {
        $langMap[$row['application_id']][] = $row['name'];
    }
    foreach ($applications as &$app) {
        $app['languages'] = $langMap[$app['id']] ?? [];
    }
}

$stats = $pdo->query("SELECT pl.name, COUNT(al.application_id) as user_count FROM programming_languages pl LEFT JOIN application_languages al ON pl.id = al.language_id GROUP BY pl.id ORDER BY user_count DESC")->fetchAll();

$allLanguages = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; }
        .admin-table th, .admin-table td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .admin-table th { background: #f8fafc; font-weight: 600; }
        .btn-action { display: inline-block; padding: 6px 12px; border-radius: 6px; text-decoration: none; color: white; margin-right: 5px; }
        .btn-delete { background: #ef4444; }
        .btn-edit { background: #f59e0b; }
        .btn-cancel { background: #6b7280; }
        .edit-form { background: white; padding: 30px; border-radius: 16px; margin-top: 30px; border-top: 4px solid #3b82f6; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
        .stat-card { background: linear-gradient(135deg, #3b82f6, #1e40af); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-count { font-size: 2rem; font-weight: bold; }
        select[multiple] { height: 120px; }
        .field-error { color: #dc2626; font-size: 0.8rem; margin-top: 4px; }
        input.error, select.error, textarea.error { border-color: #dc2626; }
    </style>
</head>
<body>
<div class="glass-container">
    <div class="form-card">
        <h1 style="text-align:center">Панель администратора</h1>
        <?php if ($message): ?>
            <div class="<?= $msgType === 'success' ? 'success-message' : 'error-message' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <h2>Заявки пользователей</h2>
        <?php if (empty($applications)): ?>
            <p style="text-align:center; padding:40px 0;">Заявок пока нет</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr><th>ID</th><th>ФИО</th><th>Email</th><th>Дата рождения</th><th>Языки</th><th>Действия</th></tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= htmlspecialchars($app['full_name']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?= htmlspecialchars($app['birth_date']) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $app['languages'] ?: ['Не выбрано'])) ?></td>
                        <td>
                            <a href="?edit=<?= $app['id'] ?>" class="btn-action btn-edit">Изменить</a>
                            <a href="?action=delete&id=<?= $app['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Удалить заявку?')">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Статистика по языкам</h2>
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <div><?= htmlspecialchars($stat['name']) ?></div>
                    <div class="stat-count"><?= $stat['user_count'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($editingId && $editData): ?>
        <div class="edit-form">
            <h3>Редактирование заявки #<?= $editingId ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_edit">
                <input type="hidden" name="id" value="<?= $editingId ?>">

                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($editData['full_name']) ?>" class="<?= isset($errors['full_name']) ? 'error' : '' ?>">
                    <?php if (isset($errors['full_name'])): ?><div class="field-error">Некорректное ФИО</div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($editData['phone']) ?>" class="<?= isset($errors['phone']) ? 'error' : '' ?>">
                    <?php if (isset($errors['phone'])): ?><div class="field-error">Некорректный телефон</div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editData['email']) ?>" class="<?= isset($errors['email']) ? 'error' : '' ?>">
                    <?php if (isset($errors['email'])): ?><div class="field-error">Некорректный email</div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Дата рождения *</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($editData['birth_date']) ?>" class="<?= isset($errors['birth_date']) ? 'error' : '' ?>">
                    <?php if (isset($errors['birth_date'])): ?><div class="field-error">Некорректная дата</div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Пол</label>
                    <div class="radio-group">
                        <label><input type="radio" name="gender" value="male" <?= ($editData['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                        <label><input type="radio" name="gender" value="female" <?= ($editData['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Языки программирования *</label>
                    <select name="languages[]" multiple class="<?= isset($errors['languages']) ? 'error' : '' ?>">
                        <?php foreach ($allLanguages as $lang): ?>
                            <option value="<?= $lang['name'] ?>" <?= in_array($lang['name'], $editData['languages'] ?? []) ? 'selected' : '' ?>><?= $lang['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Удерживайте Ctrl для выбора нескольких</small>
                </div>

                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="bio" rows="4"><?= htmlspecialchars($editData['bio']) ?></textarea>
                </div>

                <button type="submit">Сохранить изменения</button>
                <div style="margin-top:15px; text-align:center;">
                    <a href="admin.php" class="btn-action btn-cancel" style="padding:12px 24px;">Отмена</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>