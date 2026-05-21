<?php



function utf8_strlen($string) {
    if (function_exists('iconv_strlen')) {
        return iconv_strlen($string, 'UTF-8');
    }
    return preg_match_all('/./u', $string, $matches);
}


function validateFullName($value) {
    $value = trim($value);
    if ($value === '') return 'empty';
    if (utf8_strlen($value) > 150) return 'length';
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $value)) return 'invalid';
    return null;
}


function validatePhone($value) {
    $value = trim($value);
    if ($value === '') return 'empty';
    $digits = preg_replace('/\D/', '', $value);
    if (!preg_match('/^[78]\d{10}$/', $digits)) return 'invalid';
    return null;
}


function validateEmail($value) {
    $value = trim($value);
    if ($value === '') return 'empty';
    if (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/u', $value)) return 'invalid';
    return null;
}


function validateBirthDate($value) {
    if (empty($value)) return 'empty';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return 'invalid_format';
    $parts = explode('-', $value);
    if (count($parts) != 3) return 'invalid';
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];
    if (!checkdate($month, $day, $year)) return 'invalid_date';
    $date = new DateTime($value);
    $now = new DateTime();
    if ($date > $now) return 'future';
    $minDate = (new DateTime())->sub(new DateInterval('P120Y'));
    if ($date < $minDate) return 'too_old';
    return null;
}


function validateGender($value) {
    if (!in_array($value, ['male', 'female'])) return 'invalid';
    return null;
}


function validateLanguages($values) {
    if (!is_array($values) || count($values) == 0) return 'empty';
    $allowed = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
    foreach ($values as $lang) {
        if (!in_array($lang, $allowed)) return 'invalid';
    }
    return null;
}


function validateBio($value) {
    $value = trim($value);
    if ($value === '') return 'empty';
    if (utf8_strlen($value) > 5000) return 'length';
    return null;
}


function validateContract($value) {
    if ($value != '1') return 'not_checked';
    return null;
}


function validateAllFields($data) {
    $errors = [];
    $fields = [
        'full_name' => 'validateFullName',
        'phone' => 'validatePhone',
        'email' => 'validateEmail',
        'birth_date' => 'validateBirthDate',
        'gender' => 'validateGender',
        'languages' => 'validateLanguages',
        'bio' => 'validateBio',
        'contract_agreed' => 'validateContract'
    ];
    foreach ($fields as $field => $validator) {
        $value = $data[$field] ?? '';
        $error = $validator($value);
        if ($error !== null) {
            $errors[$field] = $error;
        }
    }
    return $errors;
}