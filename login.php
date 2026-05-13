<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\User;

// Запускаем сессию только если она ещё не активна
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

// Если пользователь уже авторизован, перенаправляем на главную
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = 'Введите логин и пароль.';
        $messageType = 'error';
    } else {
        $user = $service->authenticate($username, $password);
        if ($user !== null) {
            // Регенерируем ID сессии для защиты от фиксации сессии
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['username'] = $user->getUsername();
            $_SESSION['role'] = $user->getRole();
            // Сохраняем все соревнования пользователя (для поддержки множественных назначений)
            $competitionIds = $user->getCompetitionIds();
            $_SESSION['competition_ids'] = $competitionIds;
            // Для обратной совместимости сохраняем первое соревнование
            $_SESSION['competition_id'] = !empty($competitionIds) ? reset($competitionIds) : null;

            header('Location: index.php');
            exit;
        } else {
            $message = 'Неверный логин или пароль.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему — Nosework</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 60px auto;
            padding: 32px;
        }
        .login-form {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            box-shadow: var(--shadow);
        }
        .login-form h1 {
            margin: 0 0 24px;
            font-size: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
<main class="page-shell">
    <div class="login-container">
        <div class="login-form">
            <h1>Вход в систему</h1>
            
            <?php if ($message !== ''): ?>
                <div class="alert alert--<?= escape($messageType) ?>"><?= escape($message) ?></div>
            <?php endif; ?>
            
            <form method="post" novalidate>
                <div class="form-control">
                    <label for="username">Логин</label>
                    <input id="username" name="username" type="text" placeholder="Введите логин" required autocomplete="username">
                </div>
                <div class="form-control">
                    <label for="password">Пароль</label>
                    <input id="password" name="password" type="password" placeholder="Введите пароль" required autocomplete="current-password">
                </div>
                <div class="button-group">
                    <button type="submit" class="button" style="width: 100%;">Войти</button>
                </div>
            </form>
            
        </div>
    </div>
</main>
</body>
</html>
