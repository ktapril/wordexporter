<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\User;

// Требуем авторизацию
requireAuth();

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$user = getCurrentUser();
$message = '';
$messageType = '';

// Обработка формы обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_display_name') {
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        
        try {
            $currentUser = $service->getUserById($user['id']);
            if ($currentUser !== null) {
                $service->updateUser(
                    $user['id'],
                    $currentUser->getUsername(),
                    null,
                    $currentUser->getRole(),
                    $currentUser->getCompetitionId(),
                    $displayName !== '' ? $displayName : null
                );
                $message = 'Имя успешно обновлено.';
                $messageType = 'success';
                $user = getCurrentUser();
            }
        } catch (\Exception $e) {
            $message = 'Ошибка при обновлении имени: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $message = 'Заполните все поля пароля.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Новые пароли не совпадают.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Пароль должен быть не менее 6 символов.';
            $messageType = 'error';
        } else {
            try {
                // Проверяем текущий пароль
                $currentUser = $service->getUserById($user['id']);
                if ($currentUser !== null && password_verify($currentPassword, $currentUser->getPasswordHash())) {
                    $service->updateUser(
                        $user['id'],
                        $currentUser->getUsername(),
                        $newPassword,
                        $currentUser->getRole(),
                        $currentUser->getCompetitionId(),
                        $currentUser->getDisplayName()
                    );
                    $message = 'Пароль успешно изменён.';
                    $messageType = 'success';
                } else {
                    $message = 'Неверный текущий пароль.';
                    $messageType = 'error';
                }
            } catch (\Exception $e) {
                $message = 'Ошибка при смене пароля: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Получаем список соревнований для судей и секретарей (поддержка множественных назначений)
$competitions = [];
if (isJudge() || isSecretary()) {
    $currentUser = $service->getUserById($user['id']);
    if ($currentUser !== null) {
        // Получаем все соревнования пользователя
        $competitionIds = $currentUser->getCompetitionIds();
        if (!empty($competitionIds)) {
            foreach ($competitionIds as $compId) {
                $competition = $service->getCompetitionById($compId);
                if ($competition !== null) {
                    $competitions[] = $competition;
                }
            }
        }
    }
}

$roles = User::getAvailableRoles();
$roleLabel = $roles[$user['role']] ?? $user['role'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет — Nosework</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$currentPage = 'profile';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Личный кабинет</div>
            <h1 class="hero__title"><?= escape($user['username']) ?></h1>
            <p class="hero__subtitle">Управление профилем и настройками аккаунта</p>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <?php if ($message !== ''): ?>
            <div class="alert alert--<?= escape($messageType) ?>"><?= escape($message) ?></div>
        <?php endif; ?>

        <article class="panel">
            <h2>Информация о пользователе</h2>
            <div style="display: grid; gap: 16px; margin-bottom: 24px;">
                <div>
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Логин:</span>
                    <div style="color: var(--text); font-size: 1.1rem; font-weight: 600; margin-top: 4px;"><?= escape($user['username']) ?></div>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Роль:</span>
                    <div style="color: var(--text); font-size: 1.1rem; font-weight: 600; margin-top: 4px;"><?= escape($roleLabel) ?></div>
                </div>
                <?php if ($currentUser = $service->getUserById($user['id'])): ?>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Имя:</span>
                    <div style="color: var(--text); font-size: 1.1rem; font-weight: 600; margin-top: 4px;"><?= escape($currentUser->getDisplayName() ?? '—') ?></div>
                </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="panel">
            <h2>Изменить имя</h2>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="update_display_name">
                <div class="form-control">
                    <label for="display_name">Ваше имя (как вас зовут)</label>
                    <input id="display_name" name="display_name" type="text" value="<?= escape($service->getUserById($user['id'])?->getDisplayName() ?? '') ?>" placeholder="Введите ваше имя" autocomplete="name">
                </div>
                <div class="button-group">
                    <button type="submit" class="button button--primary">Сохранить</button>
                </div>
            </form>
        </article>

        <article class="panel">
            <h2>Сменить пароль</h2>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="change_password">
                <div class="form-control">
                    <label for="current_password">Текущий пароль</label>
                    <input id="current_password" name="current_password" type="password" placeholder="Введите текущий пароль" required autocomplete="current-password">
                </div>
                <div class="form-control">
                    <label for="new_password">Новый пароль</label>
                    <input id="new_password" name="new_password" type="password" placeholder="Введите новый пароль" required autocomplete="new-password">
                </div>
                <div class="form-control">
                    <label for="confirm_password">Подтверждение нового пароля</label>
                    <input id="confirm_password" name="confirm_password" type="password" placeholder="Подтвердите новый пароль" required autocomplete="new-password">
                </div>
                <div class="button-group">
                    <button type="submit" class="button button--primary">Сменить пароль</button>
                </div>
            </form>
        </article>

        <?php if (!empty($competitions)): ?>
        <article class="panel">
            <h2>Мои соревнования</h2>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($competitions as $competition): ?>
                        <tr>
                            <td><?= escape($competition->getName()) ?></td>
                            <td><?= escape($competition->getDescription()) ?></td>
                            <td>
                                <?php if (canManageCompetition()): ?>
                                    <a class="button button--secondary" href="manage.php?competition_id=<?= $competition->getId() ?>">Управление</a>
                                <?php endif; ?>
                                <?php if (canAccessVideoJudging()): ?>
                                    <a class="button button--secondary" href="video_judge.php?competition_id=<?= $competition->getId() ?>">Видеосудья</a>
                                <?php endif; ?>
                                <?php if (canViewResults()): ?>
                                    <a class="button button--secondary" href="results.php?competition_id=<?= $competition->getId() ?>">Результаты</a>
                                <?php endif; ?>
                                <?php if (canAccessJudging()): ?>
                                    <a class="button button--secondary" href="judge.php?competition_id=<?= $competition->getId() ?>">Судейство</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
