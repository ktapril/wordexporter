<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\User;

// Требуем авторизацию и права на создание соревнований (только администратор)
requireAuth();

if (!canCreateCompetition()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для создания соревнований.</p><a href="index.php">На главную</a>');
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['competition_name'] ?? ''));
    $description = trim((string)($_POST['competition_description'] ?? ''));
    $startDate = trim((string)($_POST['start_date'] ?? '')) ?: null;
    $endDate = trim((string)($_POST['end_date'] ?? '')) ?: null;
    $judgeId = filter_var($_POST['judge_id'] ?? '', FILTER_VALIDATE_INT);
    $secretaryId = filter_var($_POST['secretary_id'] ?? '', FILTER_VALIDATE_INT);

    if ($name === '') {
        $message = 'Название соревнования обязательно.';
        $messageType = 'error';
    } else {
        try {
            $competitionId = $service->createCompetition($name, $description, $startDate, $endDate);
            
            // Назначаем судью и секретаря, если выбраны
            if ($judgeId !== false && $judgeId !== null) {
                $judge = $service->getUserById($judgeId);
                if ($judge && $judge->getRole() === User::ROLE_JUDGE) {
                    $service->updateUser($judgeId, $judge->getUsername(), null, User::ROLE_JUDGE, $competitionId, $judge->getDisplayName());
                }
            }
            
            if ($secretaryId !== false && $secretaryId !== null) {
                $secretary = $service->getUserById($secretaryId);
                if ($secretary && $secretary->getRole() === User::ROLE_SECRETARY) {
                    $service->updateUser($secretaryId, $secretary->getUsername(), null, User::ROLE_SECRETARY, $competitionId, $secretary->getDisplayName());
                }
            }
            
            header('Location: manage.php?competition_id=' . $competitionId);
            exit;
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

$competitions = $service->getCompetitions();
$allUsers = $service->getAllUsers();
$judges = array_filter($allUsers, fn($u) => $u->getRole() === User::ROLE_JUDGE || $u->getRole() === User::ROLE_ADMIN);
$secretaries = array_filter($allUsers, fn($u) => $u->getRole() === User::ROLE_SECRETARY || $u->getRole() === User::ROLE_ADMIN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать соревнование — Nosework</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$currentPage = 'create_competition';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Создать соревнование</div>
            <h1 class="hero__title">Новое соревнование</h1>
            <p class="hero__subtitle">Добавьте название и описание. При желании назначьте судью и секретаря.</p>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <article class="panel">
            <a href="index.php" class="back-link">← Вернуться на главную</a>
            <h2>Новое соревнование</h2>
            <?php if ($message !== ''): ?>
                <div class="alert alert--<?= escape($messageType) ?>"><?= escape($message) ?></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <div class="form-control">
                    <label for="competition_name">Название соревнования</label>
                    <input id="competition_name" name="competition_name" type="text" placeholder="Введите название" required>
                </div>
                <div class="form-control">
                    <label for="competition_description">Описание</label>
                    <textarea id="competition_description" name="competition_description" rows="4" placeholder="Введите описание"></textarea>
                </div>
                <div class="form-control">
                    <label for="start_date">Дата начала</label>
                    <input id="start_date" name="start_date" type="date" placeholder="ГГГГ-ММ-ДД">
                </div>
                <div class="form-control">
                    <label for="end_date">Дата окончания</label>
                    <input id="end_date" name="end_date" type="date" placeholder="ГГГГ-ММ-ДД">
                </div>
                
                <hr style="border: none; border-top: 1px solid var(--border); margin: 24px 0;">
                
                <h3>Назначить ответственных</h3>
                
                <div class="form-control">
                    <label for="judge_id">Судья (необязательно)</label>
                    <select id="judge_id" name="judge_id">
                        <option value="">Не назначен</option>
                        <?php foreach ($judges as $user): ?>
                            <option value="<?= $user->getId() ?>"><?= escape($user->getUsername()) ?><?= $user->getCompetitionId() ? ' (уже назначен)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label for="secretary_id">Секретарь (необязательно)</label>
                    <select id="secretary_id" name="secretary_id">
                        <option value="">Не назначен</option>
                        <?php foreach ($secretaries as $user): ?>
                            <option value="<?= $user->getId() ?>"><?= escape($user->getUsername()) ?><?= $user->getCompetitionId() ? ' (уже назначен)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="button">Создать соревнование</button>
                </div>
            </form>
        </article>
    </section>
</main>
</body>
</html>
