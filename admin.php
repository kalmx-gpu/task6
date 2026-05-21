<?php
/**
 * Задание 6: Панель администратора с HTTP-авторизацией
 * - Просмотр, редактирование, удаление заявок
 * - Статистика по языкам программирования
 */
require_once 'config.php';
require_once 'save.php';
require_once 'validators.php';

$pdo = getDBConnection();

// 1. HTTP BASIC AUTHENTICATION
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('Требуется авторизация');
}

$login = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];

// Проверяем админа в БД
$stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE login = ?");
$stmt->execute([$login]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('Неверный логин или пароль');
}

// 2. ОБРАБОТКА ДЕЙСТВИЙ
$message = '';
$msgType = 'success';
$editErrors = [];
$editData = null;
$editingId = null;

// Удаление заявки
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

// Сохранение редактирования
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
    } else {
        $message = 'Исправьте ошибки в форме';
        $msgType = 'error';
    }
}

// Режим редактирования
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editingId = (int)$_GET['edit'];
    if (!$editData) {
        $editData = getApplicationById($editingId, $pdo);
    }
}

// Сообщения из GET параметров
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $message = 'Запись успешно удалена';
    } elseif ($_GET['msg'] === 'saved') {
        $message = 'Данные успешно обновлены';
    }
}

// 3. ПОЛУЧЕНИЕ ВСЕХ ЗАЯВОК
$appsStmt = $pdo->query("
    SELECT id, full_name, email, birth_date, created_at 
    FROM applications 
    ORDER BY id DESC
");
$applications = $appsStmt->fetchAll();

// Получаем языки для каждой заявки
if ($applications) {
    $ids = array_column($applications, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $langStmt = $pdo->prepare("
        SELECT al.application_id, pl.name 
        FROM application_languages al
        JOIN programming_languages pl ON al.language_id = pl.id
        WHERE al.application_id IN ($placeholders)
    ");
    $langStmt->execute($ids);
    
    $langMap = [];
    while ($row = $langStmt->fetch()) {
        $langMap[$row['application_id']][] = $row['name'];
    }
    
    foreach ($applications as &$app) {
        $app['languages'] = $langMap[$app['id']] ?? [];
    }
}

// 4. СТАТИСТИКА ПО ЯЗЫКАМ
$statsStmt = $pdo->query("
    SELECT pl.name, COUNT(al.application_id) as user_count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id, pl.name
    ORDER BY user_count DESC
");
$stats = $statsStmt->fetchAll();

// Получаем все языки для формы редактирования
$langsStmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
$allLanguages = $langsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb; }
        .admin-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: white; border-radius: 12px; overflow: hidden; }
        .admin-table th, .admin-table td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 0.9rem; }
        .admin-table th { background: #f8fafc; font-weight: 600; color: #334155; }
        .admin-table tr:last-child td { border-bottom: none; }
        .btn-action { display: inline-block; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; text-decoration: none; color: white; margin-right: 5px; cursor: pointer; border: none; }
        .btn-delete { background: #ef4444; }
        .btn-edit { background: #f59e0b; }
        .btn-cancel { background: #6b7280; }
        .edit-form { background: white; padding: 30px; border-radius: 16px; margin-top: 30px; border-top: 4px solid #3b82f6; }
        .stats-container { background: white; padding: 25px; border-radius: 16px; margin-top: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
        .stat-card { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-count { font-size: 2rem; font-weight: bold; margin-top: 8px; }
        .stat-lang { font-size: 0.9rem; opacity: 0.9; }
        select[multiple] { height: 120px; }
    </style>
</head>
<body>
    <div class="glass-container">
        <div class="form-card">
            <h1 class="admin-header">🔐 Панель администратора</h1>
            
            <?php if ($message): ?>
                <div class="<?= $msgType === 'success' ? 'success-message' : 'error-message' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <h2>📋 Заявки пользователей</h2>
            
            <?php if (empty($applications)): ?>
                <p style="text-align: center; color: #6b7280; padding: 40px 0;">Заявок пока нет</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Email</th>
                            <th>Дата рождения</th>
                            <th>Языки</th>
                            <th>Действия</th>
                        </tr>
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
                                <a href="?edit=<?= $app['id'] ?>" class="btn-action btn-edit">✏️ Изменить</a>
                                <a href="?action=delete&id=<?= $app['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Удалить заявку #<?= $app['id'] ?>?')">🗑 Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>📊 Статистика по языкам</h2>
            <div class="stats-container">
                <div class="stats-grid">
                    <?php foreach ($stats as $stat): ?>
                        <div class="stat-card">
                            <div class="stat-lang"><?= htmlspecialchars($stat['name']) ?></div>
                            <div class="stat-count"><?= $stat['user_count'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($editingId && $editData): ?>
            <div class="edit-form">
                <h3>✏️ Редактирование заявки #<?= $editingId ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_edit">
                    <input type="hidden" name="id" value="<?= $editingId ?>">
                    
                    <div class="form-group">
                        <label for="full_name">ФИО <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($editData['full_name']) ?>" class="<?= isset($editErrors['full_name']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['full_name'])): ?>
                            <div class="field-error">ФИО должно содержать только буквы, пробелы и дефис</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="phone">Телефон <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($editData['phone']) ?>" class="<?= isset($editErrors['phone']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['phone'])): ?>
                            <div class="field-error">Телефон должен содержать 11 цифр, начиная с 7 или 8</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($editData['email']) ?>" class="<?= isset($editErrors['email']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['email'])): ?>
                            <div class="field-error">Введите корректный email</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="birth_date">Дата рождения <span class="required">*</span></label>
                        <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($editData['birth_date']) ?>" class="<?= isset($editErrors['birth_date']) ? 'error' : '' ?>">
                        <?php if (isset($editErrors['birth_date'])): ?>
                            <div class="field-error">Некорректная дата</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Пол</label>
                        <div class="radio-group">
                            <label><input type="radio" name="gender" value="male" <?= ($editData['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                            <label><input type="radio" name="gender" value="female" <?= ($editData['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Любимые языки <span class="required">*</span></label>
                        <select name="languages[]" multiple class="<?= isset($editErrors['languages']) ? 'error' : '' ?>">
                            <?php foreach ($allLanguages as $lang): 
                                $selected = in_array($lang['name'], $editData['languages'] ?? []) ? 'selected' : '';
                                echo "<option value=\"{$lang['name']}\" $selected>{$lang['name']}</option>";
                            endforeach; ?>
                        </select>
                        <small>Удерживайте Ctrl (Cmd) для выбора нескольких</small>
                    </div>

                    <div class="form-group">
                        <label for="bio">Биография</label>
                        <textarea id="bio" name="bio" rows="4" class="<?= isset($editErrors['bio']) ? 'error' : '' ?>"><?= htmlspecialchars($editData['bio']) ?></textarea>
                    </div>

                    <button type="submit">💾 Сохранить изменения</button>
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="admin.php" class="btn-action btn-cancel" style="padding: 12px 24px; font-size: 1rem;">❌ Отмена</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>