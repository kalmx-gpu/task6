<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 5 - Анкета с авторизацией</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="glass-container">
    <div class="form-card">
        <h1>Анкета пользователя</h1>
        <p class="subtitle">Первая отправка создаст логин и пароль</p>

        <?php if (!empty($messages)): ?>
            <div class="messages">
                <?php foreach ($messages as $msg) echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!$isAuthenticated): ?>
            <div class="auth-panel">
                <form action="" method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Логин</label>
                        <input type="text" name="auth_login" required>
                    </div>
                    <div class="form-group">
                        <label>Пароль</label>
                        <input type="password" name="auth_password" required>
                    </div>
                    <button type="submit" class="btn-secondary">Войти</button>
                </form>
            </div>
            <div class="divider"></div>
        <?php else: ?>
            <div class="auth-panel success">
                <p>Вы авторизованы как <strong><?= htmlspecialchars($authLogin) ?></strong>
                <a href="#" onclick="document.getElementById('logout-form').submit(); return false;">(Выйти)</a></p>
                <form id="logout-form" action="" method="post" style="display:none;">
                    <input type="hidden" name="action" value="logout">
                </form>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="action" value="save">

            <div class="form-group">
                <label class="required" for="full_name">ФИО</label>
                <input type="text" name="full_name" id="full_name"
                       class="<?= isset($errors['full_name']) ? 'error' : '' ?>"
                       value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
                       maxlength="150">
                <?php if (isset($errorMessages['full_name'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required" for="phone">Телефон</label>
                <input type="tel" name="phone" id="phone"
                       class="<?= isset($errors['phone']) ? 'error' : '' ?>"
                       value="<?= htmlspecialchars($values['phone'] ?? '') ?>"
                       placeholder="+7XXXXXXXXXX">
                <?php if (isset($errorMessages['phone'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['phone']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required" for="email">E-mail</label>
                <input type="email" name="email" id="email"
                       class="<?= isset($errors['email']) ? 'error' : '' ?>"
                       value="<?= htmlspecialchars($values['email'] ?? '') ?>">
                <?php if (isset($errorMessages['email'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required" for="birth_date">Дата рождения</label>
                <input type="date" name="birth_date" id="birth_date"
                       class="<?= isset($errors['birth_date']) ? 'error' : '' ?>"
                       value="<?= htmlspecialchars($values['birth_date'] ?? '') ?>">
                <?php if (isset($errorMessages['birth_date'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['birth_date']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required">Пол</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= ($values['gender'] ?? '') == 'male' ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= ($values['gender'] ?? '') == 'female' ? 'checked' : '' ?>> Женский</label>
                </div>
                <?php if (isset($errorMessages['gender'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['gender']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required" for="languages">Любимые языки программирования</label>
                <select name="languages[]" id="languages" multiple
                        class="<?= isset($errors['languages']) ? 'error' : '' ?>">
                    <?php
                    $all_languages = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
                    $selected_langs = $values['languages'] ?? [];
                    if (!is_array($selected_langs)) $selected_langs = [];
                    foreach ($all_languages as $lang) {
                        $selected = in_array($lang, $selected_langs) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($lang) . "\" $selected>" . htmlspecialchars($lang) . "</option>";
                    }
                    ?>
                </select>
                <small>Удерживайте Ctrl (Cmd) для выбора нескольких</small>
                <?php if (isset($errorMessages['languages'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['languages']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="required" for="bio">Биография</label>
                <textarea name="bio" id="bio" rows="5"
                          class="<?= isset($errors['bio']) ? 'error' : '' ?>"><?=
                    htmlspecialchars($values['bio'] ?? '')
                ?></textarea>
                <?php if (isset($errorMessages['bio'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['bio']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" name="contract_agreed" id="contract" value="1"
                       class="<?= isset($errors['contract_agreed']) ? 'error' : '' ?>"
                    <?= !empty($values['contract_agreed']) ? 'checked' : '' ?>>
                <label for="contract" class="required">С контрактом ознакомлен(а)</label>
                <?php if (isset($errorMessages['contract_agreed'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errorMessages['contract_agreed']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit"><?= $isAuthenticated ? 'Обновить данные' : 'Сохранить' ?></button>
        </form>
    </div>
</div>
</body>
</html>