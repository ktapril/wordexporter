<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$selectedCompetitionId = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);
$selectedCompetition = $selectedCompetitionId ? $service->getCompetitionById($selectedCompetitionId) : null;

// Проверяем, опубликовано ли соревнование
if ($selectedCompetition === null || !$selectedCompetition->isPublished()) {
    http_response_code(404);
    die('<h1>Страница не найдена</h1><p>Результаты этого соревнования не опубликованы или соревнование не существует.</p><a href="index.php">На главную</a>');
}

$categories = $service->getCategoriesByCompetition($selectedCompetitionId);
$overallResults = $service->getOverallResults($selectedCompetitionId);

/**
 * Форматирует время из секунд в формат минуты:секунды.десятые
 * @param float $seconds Время в секундах
 * @return string Время в формате ММ:СС.д
 */
function formatTime(float $seconds): string
{
    $minutes = (int) floor($seconds / 60);
    $remainingSeconds = fmod($seconds, 60);
    $wholeSeconds = (int) floor($remainingSeconds);
    $tenths = (int) round(($remainingSeconds - $wholeSeconds) * 10);
    
    return sprintf('%02d:%02d.%d', $minutes, $wholeSeconds, $tenths);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты соревнования — <?= escape($selectedCompetition->getName()) ?> — Nosework</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$currentPage = 'public_results';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Публичные результаты</div>
            <h1 class="hero__title"><?= escape($selectedCompetition->getName()) ?></h1>
            <p class="hero__subtitle"><?= escape($selectedCompetition->getDescription()) ?></p>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <div style="margin-bottom: 16px;">
            <a href="index.php" class="button button--secondary">← Назад к списку соревнований</a>
        </div>
        <?php if (count($categories) === 0): ?>
            <div class="empty-state">У этого соревнования нет категорий.</div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <?php $categoryResults = $service->getResultsByCategory($category->getId()); ?>
                <article class="panel">
                    <h2>Категория «<?= escape($category->getName()) ?>»</h2>
                    <?php if (count($categoryResults) === 0): ?>
                        <div class="empty-state">Нет результатов для этой категории.</div>
                    <?php else: ?>
                        <div class="table-wrapper" style="margin-top: 12px;">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Место</th>
                                    <th>Участник</th>
                                    <th>Время</th>
                                    <th>Закладки</th>
                                    <th>Штраф</th>
                                    <th>Итог</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php 
                                $place = 1;
                                foreach ($categoryResults as $result): 
                                ?>
                                    <tr>
                                        <td><?= $place++ ?></td>
                                        <td><?= escape($result->getParticipantName()) ?></td>
                                        <td><?= formatTime($result->getTime()) ?></td>
                                        <td><?= $result->getFoundItems() ?></td>
                                        <td><?= $result->getPenaltyScore() ?></td>
                                        <td><strong><?= $result->getTotalScore() ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (count($overallResults) > 0): ?>
            <article class="panel">
                <h2>Общие результаты</h2>
                <div class="table-wrapper" style="margin-top: 12px;">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Место</th>
                            <th>Участник</th>
                            <th>Суммарное время</th>
                            <th>Суммарный итоговый балл</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $place = 1;
                        foreach ($overallResults as $result): 
                        ?>
                            <tr>
                                <td><?= $place++ ?></td>
                                <td><?= escape($result['participant_name']) ?></td>
                                <td><?= formatTime($result['total_time']) ?></td>
                                <td><strong><?= $result['total_score'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endif; ?>

        <div style="margin-top: 24px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
            <p>Результаты опубликованы и доступны для всеобщего просмотра.</p>
        </div>
    </section>
</main>
</body>
</html>
