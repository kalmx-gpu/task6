<?php
header('Content-Type: text/html; charset=UTF-8');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

require_once 'validators.php';
require_once 'save.php';

$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'bio', 'contract_agreed'];
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit();
    }

    if ($action === 'login') {
        $login = trim($_POST['auth_login'] ?? '');
        $password = $_POST['auth_password'] ?? '';
        if ($login === '' || $password === '') {
            $_SESSION['auth_error'] = 'Введите логин и пароль.';
        } else {
            $appId = authenticate($login, $password, $pdo);
            if ($appId) {
                $_SESSION['application_id'] = $appId;
                $_SESSION['login'] = $login;
                unset($_SESSION['auth_error']);
            } else {
                $_SESSION['auth_error'] = 'Неверный логин или пароль.';
            }
        }
        header('Location: index.php');
        exit();
    }

    if ($action === 'save') {
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'birth_date' => trim($_POST['birth_date'] ?? ''),
            'gender' => $_POST['gender'] ?? '',
            'languages' => $_POST['languages'] ?? [],
            'bio' => trim($_POST['bio'] ?? ''),
            'contract_agreed' => isset($_POST['contract_agreed']) ? 1 : 0
        ];

        $errors = validateAllFields($data);
        $hasErrors = !empty($errors);

        foreach ($fields as $field) {
            setcookie($field . '_error', '', 100000);
            if (isset($errors[$field])) {
                setcookie($field . '_error', '1', time() + 86400);
            }
            $val = $data[$field] ?? '';
            if (is_array($val)) $val = serialize($val);
            setcookie($field . '_value', $val, time() + 86400);
        }

        if ($hasErrors) {
            header('Location: index.php');
            exit();
        }

        try {
            $isAuth = isset($_SESSION['application_id']);
            if ($isAuth) {
                updateApplication((int)$_SESSION['application_id'], $data, $pdo);
                $_SESSION['success'] = 'Данные успешно обновлены.';
            } else {
                $appId = saveNewApplication($data, $pdo);
                $login = generateUniqueLogin($pdo);
                $password = generatePassword();
                createAccount($appId, $login, $password, $pdo);
                $_SESSION['generated_creds'] = ['login' => $login, 'password' => $password];
                $_SESSION['success'] = 'Анкета сохранена.';
                foreach ($fields as $field) {
                    $val = $data[$field] ?? '';
                    if (is_array($val)) $val = serialize($val);
                    setcookie($field, $val, time() + 365 * 86400);
                }
            }
            foreach ($fields as $field) {
                setcookie($field . '_error', '', 100000);
                setcookie($field . '_value', '', 100000);
            }
            setcookie('save', '1', time() + 86400);
        } catch (Exception $e) {
            $_SESSION['db_error'] = 'Ошибка сохранения: ' . $e->getMessage();
        }
        header('Location: index.php');
        exit();
    }
}

$messages = [];
$errors = [];
$values = [];

foreach ($fields as $f) {
    $values[$f] = ($f === 'languages') ? [] : '';
}

if (!empty($_COOKIE['save'])) {
    setcookie('save', '', 100000);
    $messages[] = '<div class="success-message">' . ($_SESSION['success'] ?? 'Спасибо, результаты сохранены.') . '</div>';
    unset($_SESSION['success']);
}

if (!empty($_SESSION['generated_creds'])) {
    $creds = $_SESSION['generated_creds'];
    $messages[] = '<div class="success-message">Сохраните данные для входа:<br>Логин: <strong>' . htmlspecialchars($creds['login']) . '</strong><br>Пароль: <strong>' . htmlspecialchars($creds['password']) . '</strong></div>';
    unset($_SESSION['generated_creds']);
}

if (!empty($_SESSION['db_error'])) {
    $messages[] = '<div class="error-message">' . htmlspecialchars($_SESSION['db_error']) . '</div>';
    unset($_SESSION['db_error']);
}
if (!empty($_SESSION['auth_error'])) {
    $messages[] = '<div class="error-message">' . htmlspecialchars($_SESSION['auth_error']) . '</div>';
    unset($_SESSION['auth_error']);
}

$isAuthenticated = isset($_SESSION['application_id']);
$authLogin = $_SESSION['login'] ?? '';

if ($isAuthenticated) {
    $appData = getApplicationById((int)$_SESSION['application_id'], $pdo);
    if ($appData) {
        foreach ($fields as $f) {
            if ($f === 'languages') {
                $values[$f] = $appData[$f] ?? [];
            } else {
                $values[$f] = $appData[$f] ?? '';
            }
        }
        $values['contract_agreed'] = !empty($appData['contract_agreed']);
    } else {
        session_destroy();
        $isAuthenticated = false;
        $messages[] = '<div class="error-message">Анкета не найдена. Войдите заново.</div>';
    }
}

if (!$isAuthenticated) {
    $flashValues = [];
    foreach ($fields as $field) {
        $valueKey = $field . '_value';
        if (!empty($_COOKIE[$valueKey])) {
            $val = $_COOKIE[$valueKey];
            if ($field === 'languages') $val = unserialize($val);
            $flashValues[$field] = $val;
        }
        setcookie($valueKey, '', 100000);
        setcookie($field . '_error', '', 100000);
    }
    if (!empty($flashValues)) {
        $values = $flashValues;
    } else {
        foreach ($fields as $field) {
            if (!empty($_COOKIE[$field])) {
                $val = $_COOKIE[$field];
                if ($field === 'languages') $val = unserialize($val);
                $values[$field] = $val;
            }
        }
    }
    if (!isset($values['languages']) || !is_array($values['languages'])) {
        $values['languages'] = [];
    }
    $values['contract_agreed'] = !empty($values['contract_agreed']);

    foreach ($fields as $field) {
        if (!empty($_COOKIE[$field . '_error'])) {
            $errors[$field] = true;
        }
    }
}

$errorMessages = [];
foreach ($fields as $field) {
    if (isset($errors[$field])) {
        $errorMessages[$field] = getFieldError($field);
    }
}

function getFieldError($field) {
    $map = [
        'full_name' => 'ФИО должно содержать только буквы, пробелы и дефис',
        'phone' => 'Телефон должен содержать 11 цифр, начиная с 7 или 8',
        'email' => 'Введите корректный email',
        'birth_date' => 'Дата рождения должна быть в формате ГГГГ-ММ-ДД и не быть позже текущей',
        'gender' => 'Выберите пол',
        'languages' => 'Выберите хотя бы один язык',
        'bio' => 'Биография не должна быть пустой и превышать 5000 символов',
        'contract_agreed' => 'Необходимо подтвердить ознакомление с контрактом'
    ];
    return $map[$field] ?? 'Некорректное значение';
}

include 'form.php';