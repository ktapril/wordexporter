<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;

// Требуем авторизацию и права на просмотр результатов
requireAuth();

if (!canViewResults()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для просмотра результатов.</p><a href="index.php">На главную</a>');
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

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

$selectedCompetitionId = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);
$selectedCompetition = $selectedCompetitionId ? $service->getCompetitionById($selectedCompetitionId) : null;
$categories = $selectedCompetitionId !== null ? $service->getCategoriesByCompetition($selectedCompetitionId) : [];
$overallResults = $selectedCompetitionId !== null ? $service->getOverallResults($selectedCompetitionId) : [];
$qualificationResults = $selectedCompetitionId !== null ? $service->getQualificationResults($selectedCompetitionId) : [];
$deletedResults = $selectedCompetitionId !== null ? $service->getDeletedResults($selectedCompetitionId) : [];

// Загружаем все категории для отображения названий в таблице удалённых результатов
$allCategoriesMap = [];
if ($selectedCompetitionId !== null) {
    foreach ($categories as $cat) {
        $allCategoriesMap[$cat->getId()] = $cat->getName();
    }
    // Если есть удалённые результаты, убедимся, что у нас есть названия всех категорий
    if (count($deletedResults) > 0) {
        $missingCategoryIds = [];
        foreach ($deletedResults as $result) {
            $catId = $result['category_id'];
            if (!isset($allCategoriesMap[$catId])) {
                $missingCategoryIds[] = $catId;
            }
        }
        if (count($missingCategoryIds) > 0) {
            $missingCategories = $service->getCategoriesByIds($missingCategoryIds);
            foreach ($missingCategories as $cat) {
                $allCategoriesMap[$cat->getId()] = $cat->getName();
            }
        }
    }
}

// Проверяем право на удаление результатов
$canDelete = canDeleteResults();
// Проверяем право на публикацию/снятие с публикации результатов
$canPublish = canPublishResults();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты соревнования — Nosework</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background-color 0.2s;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .delete-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .restore-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background-color 0.2s;
        }
        .restore-btn:hover {
            background-color: #218838;
        }
        .restore-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .deleted-results-table {
            margin-top: 24px;
            opacity: 0.85;
        }
        .deleted-results-table th,
        .deleted-results-table td {
            color: #666;
        }
        .action-cell {
            text-align: right;
            white-space: nowrap;
        }
        
        /* Модальное окно деталей результата - единый стиль с системой */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            max-width: 600px;
            width: min(90%, 600px);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-header h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            color: var(--text);
        }
        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.5rem;
            padding: 4px;
            line-height: 1;
        }
        .modal-close:hover {
            color: var(--text);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: var(--text-muted);
            font-weight: 500;
        }
        .detail-value {
            color: var(--text);
            font-weight: 600;
            text-align: right;
        }
        .penalty-detail-list {
            list-style: none;
            padding: 0;
            margin: 8px 0;
        }
        .penalty-detail-list li {
            padding: 6px 0;
            color: var(--text);
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
        }
        .judge-comment-box {
            margin-top: 20px;
            padding: 16px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .judge-comment-box h4 {
            margin: 0 0 10px;
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .judge-comment-text {
            color: var(--text);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .no-comment {
            color: var(--text-muted);
            font-style: italic;
        }
    </style>
</head>
<body>
<?php
$currentPage = 'view_results';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Результаты</div>
            <?php if ($selectedCompetition === null): ?>
                <h1 class="hero__title">Соревнование не найдено</h1>
                <p class="hero__subtitle">Выбранное соревнование недоступно или было удалено.</p>
            <?php else: ?>
                <h1 class="hero__title"><?= escape($selectedCompetition->getName()) ?></h1>
                <p class="hero__subtitle"><?= escape($selectedCompetition->getDescription()) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <?php if ($selectedCompetition !== null): ?>
            <a href="index.php" class="back-link">← Вернуться на главную</a>
            <?php if (count($categories) === 0): ?>
                <div class="empty-state">У этого соревнования нет категорий.</div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <?php 
                        $categoryResults = $service->getResultsByCategory($category->getId());
                        // Загружаем правила штрафов для категории
                        $penaltyRulesMap = [];
                        foreach ($category->getPenaltyRules() as $rule) {
                            $penaltyRulesMap[$rule->getId()] = [
                                'name' => $rule->getName(),
                                'points' => $rule->getPoints(),
                                'type' => $rule->getType()
                            ];
                        }
                    ?>
                    <article class="panel">
                        <h2>Категория «<?= escape($category->getName()) ?>»</h2>
                        <?php if (count($categoryResults) === 0): ?>
                            <div class="empty-state">Нет результатов для этой категории.</div>
                        <?php else: ?>
                            <div class="table-wrapper" style="margin-top: 12px;">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th>Участник</th>
                                        <th>Время</th>
                                        <th>Закладки</th>
                                        <th>Штраф</th>
                                        <th>Итог</th>
                                        <th style="width: 100px;"></th>
                                        <?php if ($canDelete): ?>
                                            <th style="width: 120px;"></th>
                                        <?php endif; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($categoryResults as $result): ?>
                                        <tr data-result-id="<?= $result->getId() ?>">
                                            <td><?= escape($result->getParticipantName()) ?></td>
                                            <td><?= formatTime($result->getTime()) ?></td>
                                            <td><?= $result->getFoundItems() ?></td>
                                            <td><?= $result->getPenaltyScore() ?></td>
                                            <td><?= $result->getTotalScore() ?></td>
                                            <td style="text-align: center;">
                                                <button class="button button--secondary details-btn" 
                                                        data-result-id="<?= $result->getId() ?>"
                                                        data-participant-name="<?= escape($result->getParticipantName()) ?>"
                                                        data-category-name="<?= escape($category->getName()) ?>"
                                                        data-time="<?= $result->getTime() ?>"
                                                        data-found-items="<?= $result->getFoundItems() ?>"
                                                        data-penalty-score="<?= $result->getPenaltyScore() ?>"
                                                        data-total-score="<?= $result->getTotalScore() ?>"
                                                        data-penalty-counts='<?= json_encode($result->getPenaltyCounts(), JSON_THROW_ON_ERROR) ?>'
                                                        data-penalty-rules='<?= json_encode($penaltyRulesMap, JSON_THROW_ON_ERROR) ?>'
                                                        data-judge-comment="<?= escape($result->getJudgeComment() ?? '') ?>">
                                                    Детали
                                                </button>
                                            </td>
                                            <?php if ($canDelete): ?>
                                                <td class="action-cell">
                                                    <button class="delete-btn" 
                                                            data-result-id="<?= $result->getId() ?>"
                                                            data-participant-name="<?= escape($result->getParticipantName()) ?>"
                                                            data-category-name="<?= escape($category->getName()) ?>">
                                                        Удалить результат
                                                    </button>
                                                </td>
                                            <?php endif; ?>
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
                                <th>Участник</th>
                                <th>Суммарное время</th>
                                <th>Суммарный итоговый балл</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($overallResults as $result): ?>
                                <tr>
                                    <td><?= escape($result['participant_name']) ?></td>
                                    <td><?= formatTime($result['total_time']) ?></td>
                                    <td><?= $result['total_score'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endif; ?>

            <?php if (count($qualificationResults) > 0): ?>
                <article class="panel">
                    <h2>Квалификация</h2>
                    <div class="table-wrapper" style="margin-top: 12px;">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Участник</th>
                                <th>Статус</th>
                                <th>Сумма баллов</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($qualificationResults as $result): ?>
                                <tr>
                                    <td><?= escape($result['participant_name']) ?></td>
                                    <td>
                                        <?php if ($result['is_qualified']): ?>
                                            <span style="color: green;">Квалифицирован</span>
                                        <?php else: ?>
                                            <span style="color: red;">Не квалифицирован</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($result['is_qualified']): ?>
                                            <?= $result['qualified_sum'] ?>
                                            <?php if ($result['qualified_count'] === 3): ?>
                                                (по трём)
                                            <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endif; ?>

            <?php if (count($deletedResults) > 0 && $canDelete): ?>
                <article class="panel deleted-results-table">
                    <h2>Удалённые результаты</h2>
                    <div class="table-wrapper" style="margin-top: 12px;">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Участник</th>
                                <th>Категория</th>
                                <th>Время</th>
                                <th>Закладки</th>
                                <th>Штраф</th>
                                <th>Итог</th>
                                <th>Удалил</th>
                                <th>Дата удаления</th>
                                <?php if ($canDelete): ?>
                                    <th style="width: 120px;"></th>
                                <?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($deletedResults as $result): ?>
                                <tr data-deleted-result-id="<?= $result['id'] ?>">
                                    <td><?= escape($result['participant_name']) ?></td>
                                    <td>
                                        <?php 
                                        $categoryName = $allCategoriesMap[$result['category_id']] ?? 'Категория #' . $result['category_id'];
                                        ?>
                                        <?= escape($categoryName) ?>
                                    </td>
                                    <td><?= formatTime($result['time']) ?></td>
                                    <td><?= $result['found_items'] ?></td>
                                    <td><?= $result['penalty_score'] ?></td>
                                    <td><?= $result['total_score'] ?></td>
                                    <td><?= escape($result['deleted_by_username']) ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($result['deleted_at'])) ?></td>
                                    <?php if ($canDelete): ?>
                                        <td class="action-cell">
                                            <button class="restore-btn" 
                                                    data-deleted-result-id="<?= $result['id'] ?>"
                                                    data-participant-name="<?= escape($result['participant_name']) ?>"
                                                    data-category-name="<?= escape($categoryName) ?>">
                                                Восстановить
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endif; ?>

            <div style="margin-top: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <a href="export.php?competition_id=<?= $selectedCompetitionId ?>" class="button">Экспортировать результаты</a>
                <?php if ($canPublish && $selectedCompetition !== null): ?>
                    <?php if ($selectedCompetition->isPublished()): ?>
                        <button id="unpublishBtn" class="button button--secondary" 
                                data-competition-id="<?= $selectedCompetitionId ?>">
                            Снять результаты с публикации
                        </button>
                    <?php else: ?>
                        <button id="publishBtn" class="button button--primary" 
                                data-competition-id="<?= $selectedCompetitionId ?>">
                            Опубликовать результаты
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Модальное окно деталей результата -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Детали результата</h3>
            <button class="modal-close" id="modalClose">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Содержимое будет заполнено через JS -->
        </div>
    </div>
</div>

<?php if ($canDelete && $selectedCompetition !== null): ?>
<script>
(function() {
    const competitionId = <?= json_encode($selectedCompetitionId) ?>;
    
    // Обработчики для кнопок удаления
    document.querySelectorAll('.delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const resultId = parseInt(this.getAttribute('data-result-id'));
            const participantName = this.getAttribute('data-participant-name');
            const categoryName = this.getAttribute('data-category-name');
            
            if (!confirm('Вы уверены, что хотите удалить результат участника "' + participantName + '" из категории "' + categoryName + '"?\n\nРезультат будет перемещён в таблицу "Удалённые результаты".')) {
                return;
            }
            
            fetch('delete_result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    result_id: resultId,
                    competition_id: competitionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Находим строку с результатом и удаляем её
                    const row = document.querySelector('tr[data-result-id="' + resultId + '"]');
                    if (row) {
                        row.remove();
                    }
                    
                    // Проверяем, осталась ли таблица пустой
                    const table = row.closest('table');
                    const tbody = table.querySelector('tbody');
                    if (tbody.querySelectorAll('tr').length === 0) {
                        const panel = table.closest('.panel');
                        const h2 = panel.querySelector('h2');
                        const categoryName = h2.textContent;
                        panel.innerHTML = '<h2>' + categoryName + '</h2><div class="empty-state">Нет результатов для этой категории.</div>';
                    }
                    
                    // Перезагружаем страницу для обновления таблицы удалённых результатов
                    window.location.reload();
                } else {
                    alert('Ошибка при удалении результата: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Игнорируем ошибки сети после успешного удаления (страница уже перезагружается)
                console.log('Завершение операции удаления...');
            });
        });
    });
    
    // Обработчики для кнопок восстановления
    document.querySelectorAll('.restore-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const deletedResultId = parseInt(this.getAttribute('data-deleted-result-id'));
            const participantName = this.getAttribute('data-participant-name');
            const categoryName = this.getAttribute('data-category-name');
            
            if (!confirm('Вы уверены, что хотите восстановить результат участника "' + participantName + '" в категорию "' + categoryName + '"?')) {
                return;
            }
            
            fetch('restore_result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    deleted_result_id: deletedResultId,
                    competition_id: competitionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Перезагружаем страницу для отображения восстановленного результата
                    window.location.reload();
                } else {
                    alert('Ошибка при восстановлении результата: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Произошла ошибка при восстановлении результата. Пожалуйста, попробуйте снова.');
            });
        });
    });
})();
</script>
<?php endif; ?>

<script>
(function() {
    // Модальное окно деталей - универсальный обработчик для всех ролей
    const modal = document.getElementById('detailsModal');
    if (!modal) return;
    
    const modalClose = document.getElementById('modalClose');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    
    function formatTime(seconds) {
        if (seconds === null || seconds === undefined) return '—';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        const ms = Math.floor((seconds % 1) * 100);
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0') + '.' + String(ms).padStart(2, '0');
    }
    
    function openDetailsModal(btn) {
        const participantName = btn.getAttribute('data-participant-name');
        const categoryName = btn.getAttribute('data-category-name');
        const time = parseFloat(btn.getAttribute('data-time'));
        const foundItems = parseInt(btn.getAttribute('data-found-items'));
        const penaltyScore = parseInt(btn.getAttribute('data-penalty-score'));
        const totalScore = parseInt(btn.getAttribute('data-total-score'));
        const penaltyCounts = JSON.parse(btn.getAttribute('data-penalty-counts'));
        const penaltyRules = JSON.parse(btn.getAttribute('data-penalty-rules') || '{}');
        const judgeComment = btn.getAttribute('data-judge-comment') || '';
        
        modalTitle.textContent = 'Детали: ' + participantName;
        
        let penaltyDetailsHtml = '<ul class="penalty-detail-list">';
        let hasPenalties = false;
        
        if (penaltyCounts && typeof penaltyCounts === 'object') {
            // Собираем все штрафы в единый массив для отображения
            let allPenalties = [];
            
            for (const [ruleId, count] of Object.entries(penaltyCounts)) {
                const ruleCount = parseInt(count);
                if (ruleCount > 0) {
                    const ruleInfo = penaltyRules[ruleId];
                    const label = ruleInfo ? ruleInfo.name : 'Неизвестный штраф';
                    const points = ruleInfo ? ruleInfo.points : [10];
                    
                    // Рассчитываем общее количество баллов за этот тип штрафа
                    let totalPoints = 0;
                    for (let i = 0; i < ruleCount; i++) {
                        const penaltyPoints = points.length > 0 ? points[Math.min(i, points.length - 1)] : 10;
                        totalPoints += penaltyPoints;
                    }
                    
                    allPenalties.push({
                        label: label,
                        count: ruleCount,
                        totalPoints: totalPoints
                    });
                }
            }
            
            // Отображаем все штрафы с указанием количества и баллов
            allPenalties.forEach(function(penalty) {
                const countText = penalty.count > 1 ? ' (' + penalty.count + '×)' : '';
                penaltyDetailsHtml += '<li><span><strong>' + escapeHtml(penalty.label) + countText + '</strong></span><span>' + penalty.totalPoints + ' баллов</span></li>';
                hasPenalties = true;
            });
        }
        penaltyDetailsHtml += '</ul>';
        
        if (!hasPenalties) {
            penaltyDetailsHtml = '<p style="margin: 8px 0; color: var(--text-muted);">Штрафов нет</p>';
        }
        
        let commentHtml = judgeComment 
            ? '<p class="judge-comment-text">' + escapeHtml(judgeComment) + '</p>'
            : '<p class="no-comment">Комментарий отсутствует</p>';
        
        modalBody.innerHTML = '' +
            '<div class="detail-row"><span class="detail-label">Категория:</span><span class="detail-value">' + escapeHtml(categoryName || '—') + '</span></div>' +
            '<div class="detail-row"><span class="detail-label">Время:</span><span class="detail-value">' + formatTime(time) + '</span></div>' +
            '<div class="detail-row"><span class="detail-label">Найдено закладок:</span><span class="detail-value">' + foundItems + '</span></div>' +
            '<div class="detail-row"><span class="detail-label">Сумма штрафов:</span><span class="detail-value">' + penaltyScore + '</span></div>' +
            '<div class="detail-row"><span class="detail-label">Общий балл:</span><span class="detail-value">' + totalScore + '</span></div>' +
            '<div style="margin-top: 20px;"><strong class="detail-label" style="display: block; margin-bottom: 12px;">Комментарий судьи:</strong>' + commentHtml + '</div>';
        
        modal.classList.add('active');
    }
    
    function closeDetailsModal() {
        modal.classList.remove('active');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Обработчики для кнопок "Детали"
    document.querySelectorAll('.details-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openDetailsModal(this);
        });
    });
    
    // Закрытие модального окна
    if (modalClose) {
        modalClose.addEventListener('click', closeDetailsModal);
    }
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeDetailsModal();
        }
    });
    
    // Закрытие по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeDetailsModal();
        }
    });
})();
</script>

<?php if ($canPublish && $selectedCompetition !== null): ?>
<script>
(function() {
    const publishBtn = document.getElementById('publishBtn');
    const unpublishBtn = document.getElementById('unpublishBtn');

    if (publishBtn) {
        publishBtn.addEventListener('click', function() {
            const competitionId = this.getAttribute('data-competition-id');
            
            if (!confirm('Вы уверены, что хотите опубликовать результаты этого соревнования?\n\nРезультаты станут доступны для просмотра всем пользователям на главной странице.')) {
                return;
            }

            fetch('toggle_publish.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    competition_id: competitionId,
                    action: 'publish'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Ошибка при публикации результатов: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Произошла ошибка при публикации результатов. Пожалуйста, попробуйте снова.');
            });
        });
    }

    if (unpublishBtn) {
        unpublishBtn.addEventListener('click', function() {
            const competitionId = this.getAttribute('data-competition-id');
            
            if (!confirm('Вы уверены, что хотите снять результаты с публикации?\n\nРезультаты больше не будут видны незарегистрированным пользователям.')) {
                return;
            }

            fetch('toggle_publish.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    competition_id: competitionId,
                    action: 'unpublish'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Ошибка при снятии результатов с публикации: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Произошла ошибка при снятии результатов с публикации. Пожалуйста, попробуйте снова.');
            });
        });
    }
})();
</script>
<?php endif; ?>
</body>
</html>
