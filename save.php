<?php
require_once 'config.php';

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $config = require 'config.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    return $pdo;
}

function generateUniqueLogin(PDO $pdo): string {
    do {
        $login = 'user_' . substr(md5(uniqid()), 0, 8);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM application_accounts WHERE login = ?");
        $stmt->execute([$login]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    return $login;
}

function generatePassword(): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, 10);
}

function saveNewApplication(array $data, PDO $pdo): int {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (full_name, phone, email, birth_date, gender, bio, contract_agreed)
            VALUES (:full_name, :phone, :email, :birth_date, :gender, :bio, :contract_agreed)
        ");
        $stmt->execute([
            ':full_name'       => $data['full_name'],
            ':phone'           => $data['phone'],
            ':email'           => $data['email'],
            ':birth_date'      => $data['birth_date'],
            ':gender'          => $data['gender'],
            ':bio'             => $data['bio'],
            ':contract_agreed' => 1
        ]);
        $appId = (int)$pdo->lastInsertId();

        if (!empty($data['languages'])) {
            $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $linkStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($data['languages'] as $lang) {
                $langStmt->execute([$lang]);
                $langId = $langStmt->fetchColumn();
                if ($langId) {
                    $linkStmt->execute([$appId, $langId]);
                }
            }
        }

        $pdo->commit();
        return $appId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateApplication(int $appId, array $data, PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE applications
            SET full_name = :full_name,
                phone = :phone,
                email = :email,
                birth_date = :birth_date,
                gender = :gender,
                bio = :bio,
                contract_agreed = :contract_agreed
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'              => $appId,
            ':full_name'       => $data['full_name'],
            ':phone'           => $data['phone'],
            ':email'           => $data['email'],
            ':birth_date'      => $data['birth_date'],
            ':gender'          => $data['gender'],
            ':bio'             => $data['bio'],
            ':contract_agreed' => 1
        ]);

        $delStmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $delStmt->execute([$appId]);

        if (!empty($data['languages'])) {
            $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
            $linkStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($data['languages'] as $lang) {
                $langStmt->execute([$lang]);
                $langId = $langStmt->fetchColumn();
                if ($langId) {
                    $linkStmt->execute([$appId, $langId]);
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function createAccount(int $appId, string $login, string $password, PDO $pdo): void {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO application_accounts (application_id, login, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$appId, $login, $hash]);
}

function getApplicationById(int $appId, PDO $pdo): ?array {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
    if (!$app) return null;

    $langStmt = $pdo->prepare("
        SELECT pl.name FROM application_languages al
        JOIN programming_languages pl ON al.language_id = pl.id
        WHERE al.application_id = ?
    ");
    $langStmt->execute([$appId]);
    $languages = $langStmt->fetchAll(PDO::FETCH_COLUMN);

    $app['languages'] = $languages ?? [];
    return $app;
}

function authenticate(string $login, string $password, PDO $pdo): ?int {
    $stmt = $pdo->prepare("SELECT application_id, password_hash FROM application_accounts WHERE login = ?");
    $stmt->execute([$login]);
    $acc = $stmt->fetch();
    if ($acc && password_verify($password, $acc['password_hash'])) {
        return (int)$acc['application_id'];
    }
    return null;
}