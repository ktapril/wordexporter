<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/auth.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\PenaltyRule;

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

function ensureDefaultData(CompetitionService $service): void
{
    if (count($service->getCompetitions()) > 0) {
        return;
    }

    $competitionId = $service->createCompetition('Nosework Cup 2026', 'Открытый кубок по ноузворку с несколькими категориями.');
    $service->createCategory(
        $competitionId,
        'Начальный уровень',
        120.0,
        5,
        100.0,
        [
            ['name' => 'Общий штраф', 'type' => PenaltyRule::TYPE_FLAT, 'points' => [2.0]],
            ['name' => 'Прогрессивный штраф', 'type' => PenaltyRule::TYPE_PROGRESSIVE, 'points' => [1.0, 2.0, 3.0]],
        ]
    );
}

ensureDefaultData($service);

$competitions = $service->getCompetitions();
$isLoggedIn = isLoggedIn();

// Для авторизованных пользователей фильтруем соревнования по доступу
// Для неавторизованных показываем все соревнования
if ($isLoggedIn) {
    $accessibleCompetitions = array_filter($competitions, function($competition) {
        return hasCompetitionAccess($competition->getId());
    });
} else {
    // Показываем все соревнования для неавторизованных пользователей
    $accessibleCompetitions = $competitions;
}

// Подготавливаем дополнительную информацию для каждого соревнования (для неавторизованных пользователей)
$competitionInfo = [];
foreach ($accessibleCompetitions as $competition) {
    $service->getCategoriesByCompetition($competition->getId());
    
    if (!$isLoggedIn) {
        $judgesWithNames = $service->getJudgesWithDisplayName($competition->getId());
        $judgeNames = array_map(function($judge) {
            return $judge->getDisplayName();
        }, $judgesWithNames);
        
        $startDate = $competition->getStartDate();
        $endDate = $competition->getEndDate();
        
        $competitionInfo[$competition->getId()] = [
            'judgeNames' => $judgeNames,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список соревнований — Nosework</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$currentPage = 'home';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell">
    <section class="hero">
        <div>
            <div class="hero__eyebrow">Список соревнований</div>
            <h1 class="hero__title">Все соревнования</h1>
            <?php if ($isLoggedIn): ?>
                <p class="hero__subtitle">Просматривайте все доступные соревнования и управляйте ими.</p>
            <?php else: ?>
                <p class="hero__subtitle">Ознакомьтесь с доступными соревнованиями. Войдите в систему для участия.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <article class="panel">
            <?php if (count($accessibleCompetitions) === 0): ?>
                <div class="empty-state">
                    <?php if ($isLoggedIn): ?>
                        У вас нет доступа к соревнованиям или пока не создано ни одного соревнования.
                    <?php else: ?>
                        Пока не создано ни одного соревнования.
                    <?php endif; ?>
                </div>
                <?php if ($isLoggedIn && canCreateCompetition()): ?>
                    <div style="margin-top: 1rem;">
                        <a class="button button--primary" href="add_competition.php">Создать соревнование</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Название</th>
                            <th>Описание</th>
                            <?php if ($isLoggedIn): ?>
                                <th>Действия</th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($accessibleCompetitions as $competition): ?>
                            <tr>
                                <td><?= escape($competition->getName()) ?></td>
                                <td>
                                    <?= escape($competition->getDescription()) ?>
                                    <?php if (!$isLoggedIn && isset($competitionInfo[$competition->getId()])): ?>
                                        <?php
                                            $info = $competitionInfo[$competition->getId()];
                                            $judgeNames = $info['judgeNames'];
                                            $startDate = $info['startDate'];
                                            $endDate = $info['endDate'];
                                        ?>
                                        <?php if (!empty($judgeNames)): ?>
                                            <br><strong>Судьи:</strong> <?= escape(implode(', ', $judgeNames)) ?>
                                        <?php endif; ?>
                                        <?php if ($startDate !== null || $endDate !== null): ?>
                                            <br><strong>Дата:</strong> 
                                            <?php if ($startDate !== null && $endDate !== null && $startDate === $endDate): ?>
                                                <?= escape(date('d.m.Y', strtotime($startDate))) ?>
                                            <?php elseif ($startDate !== null && $endDate !== null): ?>
                                                <?= escape(date('d.m.Y', strtotime($startDate))) ?> — <?= escape(date('d.m.Y', strtotime($endDate))) ?>
                                            <?php elseif ($startDate !== null): ?>
                                                <?= escape(date('d.m.Y', strtotime($startDate))) ?>
                                            <?php elseif ($endDate !== null): ?>
                                                <?= escape(date('d.m.Y', strtotime($endDate))) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isLoggedIn): ?>
                                    <td>
                                        <div class="actions-cell">
                                            <!-- Группа: Дашборд -->
                                            <?php if (hasCompetitionAccess($competition->getId())): ?>
                                                <div class="actions-group actions-group--dashboard">
                                                    <a class="button button--dashboard" href="dashboard.php?competition_id=<?= $competition->getId() ?>">Дашборд</a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Группа: Управление -->
                                            <?php if (canManageCompetition()): ?>
                                                <div class="actions-group">
                                                    <a class="button button--secondary button--manage" href="manage.php?competition_id=<?= $competition->getId() ?>">Управление</a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Группа: Судейство -->
                                            <div class="actions-group actions-group--judge">
                                                <?php 
                                                    $canAccessVideo = canAccessVideoJudging($competition->getId());
                                                    $isActiveVideo = $competition->isActive();
                                                ?>
                                                <?php if ($canAccessVideo): ?>
                                                    <a class="button button--secondary button--judge" href="video_judge.php?competition_id=<?= $competition->getId() ?>">Видеосудья</a>
                                                <?php else: ?>
                                                    <button class="button button--secondary button--judge" disabled title="<?= !$isActiveVideo ? 'Соревнование не активно' : '' ?>">Видеосудья</button>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                    $canAccessJudge = canAccessJudging($competition->getId());
                                                    $isActiveJudge = $competition->isActive();
                                                ?>
                                                <?php if ($canAccessJudge): ?>
                                                    <a class="button button--secondary button--judge" href="judge.php?competition_id=<?= $competition->getId() ?>">Судейство</a>
                                                <?php else: ?>
                                                    <button class="button button--secondary button--judge" disabled title="<?= !$isActiveJudge ? 'Соревнование не активно' : '' ?>">Судейство</button>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                    $canAccessManual = canAccessManualResult($competition->getId());
                                                    $isActiveManual = $competition->isActive();
                                                ?>
                                                <?php if ($canAccessManual): ?>
                                                    <a class="button button--secondary button--judge" href="manual_result.php?competition_id=<?= $competition->getId() ?>">Результат вручную</a>
                                                <?php else: ?>
                                                    <button class="button button--secondary button--judge" disabled title="<?= !$isActiveManual ? 'Соревнование не активно' : '' ?>">Результат вручную</button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Группа: Результаты -->
                                            <?php if (canViewResults()): ?>
                                                <div class="actions-group">
                                                    <a class="button button--primary button--results" href="results.php?competition_id=<?= $competition->getId() ?>">Результаты</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td>
                                        <?php if ($competition->isPublished()): ?>
                                            <a class="button button--primary" href="public_results.php?competition_id=<?= $competition->getId() ?>">Результаты</a>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
</body>
</html>
