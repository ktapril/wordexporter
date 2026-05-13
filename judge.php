<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\PenaltyRule;

// Требуем авторизацию и права на судейство
requireAuth();

$selectedCompetitionIdForCheck = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);
if (!canAccessJudging($selectedCompetitionIdForCheck)) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для судейства или соревнование не активно.</p><a href="index.php">На главную</a>');
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

function parsePenaltyCounts(array $input): array
{
    $counts = [];
    foreach ($input as $field => $value) {
        if (strpos($field, 'penalty_count_') !== 0) {
            continue;
        }

        $ruleId = (int) substr($field, strlen('penalty_count_'));
        $count = filter_var(str_replace(',', '.', trim((string)$value)), FILTER_VALIDATE_FLOAT);
        if ($ruleId > 0 && $count !== false && $count > 0) {
            $counts[$ruleId] = (int)$count;
        }
    }

    return $counts;
}

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
$selectedCompetitionId = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);
$selectedCompetitionId = $selectedCompetitionId ?: (count($competitions) ? $competitions[0]->getId() : null);
$selectedCompetition = $selectedCompetitionId ? $service->getCompetitionById($selectedCompetitionId) : null;
$categories = $selectedCompetitionId !== null ? $service->getCategoriesByCompetition($selectedCompetitionId) : [];
$selectedCategoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$selectedCategoryId = $selectedCategoryId ?: (count($categories) ? $categories[0]->getId() : null);
$selectedCategory = $selectedCategoryId ? $service->getCategoryById($selectedCategoryId) : null;
$competitionParticipants = $selectedCompetitionId !== null ? $service->getParticipantsByCompetition($selectedCompetitionId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectUrl = $_SERVER['PHP_SELF'];

    if ($action === 'add_result') {
        $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $participantIdRaw = $_POST['participant_id'] ?? '';
        $participantId = $participantIdRaw !== '' ? (int)$participantIdRaw : null;
        $time = filter_var($_POST['time'] ?? '', FILTER_VALIDATE_FLOAT);
        $foundItems = filter_var($_POST['found_items'] ?? '', FILTER_VALIDATE_INT);
        $penaltyCounts = parsePenaltyCounts($_POST);
        $judgeComment = trim($_POST['judge_comment'] ?? '');

        // Проверка: выбран ли участник явно (participant_id должен быть положительным integer)
        $participantNotSelected = ($participantId === null || $participantId <= 0);
        
        if ($categoryId === false || $categoryId === null || $participantNotSelected || $time === false || $time < 0 || $foundItems === false || $foundItems < 0) {
            $message = 'Пожалуйста, выберите участника и заполните данные попытки корректно.';
            $messageType = 'error';
        } elseif ($time <= 0) {
            $message = 'Время попытки не может быть равно 0. Пожалуйста, проведите попытку.';
            $messageType = 'error';
        } else {
            // Проверка: есть ли уже результат у этого участника в этой категории
            $existingResult = $service->getResultByParticipantAndCategory($categoryId, $participantId);
            if ($existingResult !== null) {
                $participant = $service->getParticipantById($participantId);
                $category = $service->getCategoryById($categoryId);
                $participantName = $participant ? escape($participant->getName()) : 'Участник';
                $categoryName = $category ? escape($category->getName()) : 'Категория';
                $message = "Результат участника '{$participantName}' в категории '{$categoryName}' уже был добавлен ранее.";
                $messageType = 'error';
            } else {
                $service->addResult($categoryId, $participantId, $time, $foundItems, $penaltyCounts, $judgeComment !== '' ? $judgeComment : null);
                header('Location: ' . $redirectUrl . '?competition_id=' . $selectedCompetitionId . '&category_id=' . $categoryId . '&saved=1');
                exit;
            }
        }
    } elseif ($action === 'remove_penalty') {
        // Обработка удаления штрафа через AJAX будет выполняться на клиенте
        // Этот блок нужен для совместимости
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

if (isset($_GET['saved'])) {
    $message = 'Результат успешно сохранен.';
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Судейство — <?= $selectedCompetition ? escape($selectedCompetition->getName()) : 'Nosework' ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$currentPage = 'judge';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Судейство</div>
            <h1 class="hero__title"><?= $selectedCompetition ? escape($selectedCompetition->getName()) : 'Соревнование' ?></h1>
            <p class="hero__subtitle">Запись результатов попытки</p>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <article class="panel">
            <?php if ($message !== ''): ?>
                <div class="alert alert--<?= $messageType ?>"><?= escape($message) ?></div>
            <?php endif; ?>

            <form id="judge-form" method="post" novalidate>
                <input type="hidden" name="action" value="add_result">
                <input type="hidden" name="category_id" id="category_id_hidden" value="<?= escape((string)$selectedCategoryId) ?>">
                <input type="hidden" id="time" name="time" value="0">
                <input type="hidden" id="found_items" name="found_items" value="0">

                <div style="display: grid; gap: 18px;">
                    <div class="judge-selectors" style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        <div class="form-control" style="margin-bottom: 0;">
                            <label for="category_filter_inner">Категория</label>
                            <select id="category_filter_inner" name="category_id_display" required>
                                <option value="">Выберите категорию</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category->getId() ?>" <?= $category->getId() === $selectedCategoryId ? 'selected' : '' ?>><?= escape($category->getName()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-control" style="margin-bottom: 0;">
                            <label for="participant_id">Участник</label>
                            <select id="participant_id" name="participant_id" required <?= !$selectedCategoryId ? 'disabled' : '' ?>>
                                <?php if (!$selectedCategoryId): ?>
                                    <option value="">Сначала выберите категорию</option>
                                <?php elseif (count($competitionParticipants) === 0): ?>
                                    <option disabled>Нет участников</option>
                                <?php else: ?>
                                    <?php 
                                    $existingResults = $selectedCategoryId ? $service->getResultsByCategory($selectedCategoryId) : [];
                                    $participantsWithResults = [];
                                    foreach ($existingResults as $result) {
                                        if ($result->getParticipantId() !== null) {
                                            $participantsWithResults[] = $result->getParticipantId();
                                        }
                                    }
                                    $hasAvailableParticipants = false;
                                    foreach ($competitionParticipants as $participant): 
                                        $hasResult = in_array($participant->getId(), $participantsWithResults, true);
                                        if (!$hasResult):
                                            $hasAvailableParticipants = true;
                                    ?>
                                        <option value="<?= $participant->getId() ?>"><?= escape($participant->getName()) ?><?= $participant->getNickname() ? ' (' . escape($participant->getNickname()) . ')' : '' ?></option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    if (!$hasAvailableParticipants):
                                    ?>
                                        <option disabled>Все участники уже прошли попытку</option>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="judge-controls" id="judge-controls-section" <?= !$selectedCategoryId ? 'style="display:none;"' : '' ?>>
                        <div class="timer-card">
                            <div class="timer-label">Секундомер</div>
                            <div id="timer" class="timer-value">00:00.0</div>
                            <div class="timer-meta">Максимум: <?= number_format($selectedCategory->getTimeLimit(), 1) ?> сек</div>
                        </div>

                        <div class="button-group" style="margin-top: 12px;">
                            <button type="button" id="start-button" class="button">Старт</button>
                            <button type="button" id="found-button" class="button button--secondary" disabled>Верно</button>
                            <button type="button" id="stop-button" class="button button--secondary" disabled>Стоп</button>
                        </div>
                    </div>

                    <div class="form-control" id="penalties-section" <?= !$selectedCategoryId ? 'style="display:none;"' : '' ?>>
                        <label>Штрафы</label>
                        <div id="penalty-buttons" class="penalty-grid">
                            <?php foreach ($selectedCategory->getPenaltyRules() as $rule): ?>
                                <div class="penalty-card" data-rule-id="<?= $rule->getId() ?>" data-rule-type="<?= escape($rule->getType()) ?>" data-rule-points="<?= implode(',', $rule->getPoints()) ?>">
                                    <div class="penalty-card__header">
                                        <strong><?= escape($rule->getName()) ?></strong>
                                        <span class="penalty-card__type"><?= escape($rule->getType()) ?></span>
                                    </div>
                                    <?php if ($rule->getType() === PenaltyRule::TYPE_FLAT): ?>
                                        <div class="penalty-card__actions" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                                            <?php foreach ($rule->getPoints() as $value): ?>
                                                <button type="button" class="button penalty-flat-btn" data-rule-id="<?= $rule->getId() ?>" data-penalty-value="<?= (int)$value ?>"><?= (int)$value ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="penalty-card__info">
                                            <span class="penalty-card__selected">Выбрано: <strong>0</strong></span>
                                            <span class="penalty-card__amount">Сумма: <strong>0</strong></span>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="button penalty-add-btn" data-rule-id="<?= $rule->getId() ?>"><?= (int)$rule->getPoints()[0] ?></button>
                                        <div class="penalty-card__info">
                                            <span class="penalty-card__next">Следующий штраф: <strong><?= number_format($rule->getPoints()[0], 1) ?></strong></span>
                                            <span class="penalty-card__count">Добавлено: <strong>0</strong></span>
                                            <span class="penalty-card__amount">Сумма: <strong>0</strong></span>
                                        </div>
                                    <?php endif; ?>
                                    <input type="hidden" name="penalty_count_<?= $rule->getId() ?>" value="0">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="penalty-summary">
                            <span>Общая сумма штрафов:</span>
                            <strong id="total-penalty">0</strong>
                        </div>
                    </div>

                    <div class="attempt-status" id="attempt-status-section" <?= !$selectedCategoryId ? 'style="display:none;"' : '' ?>>
                        <p>Найдено закладок: <strong id="found-count">0</strong> / <span id="hides-count-display"><?= $selectedCategory->getHidesCount() ?></span></p>
                        <div class="detection-times">
                            <p>Время обнаружения:</p>
                            <ol id="found-times-list" class="detection-list"></ol>
                        </div>
                    </div>

                    <div id="final-summary" class="panel" style="margin-top: 18px; padding: 18px;">
                        <h3>Итоги попытки</h3>
                        <p>Время: <strong id="summary-time">0</strong> сек</p>
                        <p>Найдено закладок: <strong id="summary-found">0</strong></p>
                        <p>Штрафы: <strong id="summary-penalty">0</strong></p>
                        <p>Общее: <strong id="summary-total">---</strong></p>
                        <div class="form-control" style="margin-top: 18px;">
                            <label for="judge_comment">Комментарий судьи</label>
                            <textarea id="judge_comment" name="judge_comment" rows="4" placeholder="Введите комментарий к попытке участника (необязательно). Детали штрафов будут добавлены автоматически."></textarea>
                        </div>
                        <div style="margin-top: 12px;">
                            <p style="margin: 0 0 6px; color: var(--text-muted);">Детали штрафов:</p>
                            <ul id="summary-penalty-list" style="margin: 0 0 0 20px; color: var(--text);"></ul>
                        </div>
                    </div>

                    <div class="button-group" style="margin-top: 18px;" id="save-button-section" <?= !$selectedCategoryId ? 'style="display:none;"' : '' ?>>
                        <button type="submit" id="save-button" class="button" disabled>Сохранить результат</button>
                    </div>
                </div>
            </form>
        </article>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Функция для обновления списка участников при изменении категории
        function updateParticipantsList(categoryId) {
            const participantSelect = document.getElementById('participant_id');
            const judgeControls = document.getElementById('judge-controls-section');
            const penaltiesSection = document.getElementById('penalties-section');
            const penaltyButtonsContainer = document.getElementById('penalty-buttons');
            const attemptStatusSection = document.getElementById('attempt-status-section');
            const saveButtonSection = document.getElementById('save-button-section');
            const categoryIdHidden = document.getElementById('category_id_hidden');
        
        if (!categoryId || categoryId === '') {
            // Категория не выбрана - скрываем все элементы управления
            participantSelect.innerHTML = '<option value="">Сначала выберите категорию</option>';
            participantSelect.disabled = true;
            if (judgeControls) judgeControls.style.display = 'none';
            if (penaltiesSection) penaltiesSection.style.display = 'none';
            if (attemptStatusSection) attemptStatusSection.style.display = 'none';
            if (saveButtonSection) saveButtonSection.style.display = 'none';
            if (categoryIdHidden) categoryIdHidden.value = '';
            return;
        }
        
        // Показываем элементы управления
        if (judgeControls) judgeControls.style.display = 'block';
        if (penaltiesSection) penaltiesSection.style.display = 'block';
        if (attemptStatusSection) attemptStatusSection.style.display = 'block';
        if (saveButtonSection) saveButtonSection.style.display = 'block';
        if (categoryIdHidden) categoryIdHidden.value = categoryId;
        
        // Загружаем список участников и правила штрафов через AJAX
        fetch('get_participants.php?category_id=' + encodeURIComponent(categoryId))
            .then(response => response.json())
            .then(data => {
                participantSelect.innerHTML = '';
                
                // Обновляем кнопки штрафов на основе данных из сервера
                if (data.penaltyRules && penaltyButtonsContainer) {
                    let penaltyHtml = '';
                    data.penaltyRules.forEach(function(rule) {
                        const pointsStr = rule.points.join(',');
                        penaltyHtml += '<div class="penalty-card" data-rule-id="' + rule.id + '" data-rule-type="' + rule.type + '" data-rule-points="' + pointsStr + '">';
                        penaltyHtml += '<div class="penalty-card__header">';
                        penaltyHtml += '<strong>' + rule.name + '</strong>';
                        penaltyHtml += '<span class="penalty-card__type">' + rule.type + '</span>';
                        penaltyHtml += '</div>';
                        
                        if (rule.type === 'flat') {
                            penaltyHtml += '<div class="penalty-card__actions" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">';
                            rule.points.forEach(function(value) {
                                penaltyHtml += '<button type="button" class="button penalty-flat-btn" data-rule-id="' + rule.id + '" data-penalty-value="' + Math.round(value) + '">' + Math.round(value) + '</button>';
                            });
                            penaltyHtml += '</div>';
                            penaltyHtml += '<div class="penalty-card__info">';
                            penaltyHtml += '<span class="penalty-card__selected">Выбрано: <strong>0</strong></span>';
                            penaltyHtml += '<span class="penalty-card__amount">Сумма: <strong>0</strong></span>';
                            penaltyHtml += '</div>';
                        } else {
                            penaltyHtml += '<button type="button" class="button penalty-add-btn" data-rule-id="' + rule.id + '">' + Math.round(rule.points[0]) + '</button>';
                            penaltyHtml += '<div class="penalty-card__info">';
                            penaltyHtml += '<span class="penalty-card__next">Следующий штраф: <strong>' + rule.points[0] + '</strong></span>';
                            penaltyHtml += '<span class="penalty-card__count">Добавлено: <strong>0</strong></span>';
                            penaltyHtml += '<span class="penalty-card__amount">Сумма: <strong>0</strong></span>';
                            penaltyHtml += '</div>';
                        }
                        
                        penaltyHtml += '<input type="hidden" name="penalty_count_' + rule.id + '" value="0">';
                        penaltyHtml += '</div>';
                    });
                    penaltyButtonsContainer.innerHTML = penaltyHtml;
                    
                    // Пересоздаём обработчики для новых кнопок штрафов
                    const newPenaltyCards = Array.from(penaltyButtonsContainer.querySelectorAll('.penalty-card'));
                    newPenaltyCards.forEach(card => {
                        const addBtn = card.querySelector('.penalty-add-btn');
                        if (addBtn) {
                            addBtn.addEventListener('click', function () {
                                addPenalty(card);
                            });
                        }

                        const flatButtons = card.querySelectorAll('.penalty-flat-btn');
                        flatButtons.forEach(button => {
                            button.addEventListener('click', function () {
                                addPenalty(card, button.dataset.penaltyValue);
                            });
                        });
                    });
                    
                    // Обновляем глобальную переменную penaltyCards
                    window.penaltyCards = newPenaltyCards;
                    penaltyCards = newPenaltyCards;
                }
                
                // Обновляем количество закладок (hides count) для новой категории
                const hidesCountDisplay = document.getElementById('hides-count-display');
                if (hidesCountDisplay && data.hidesCount !== undefined) {
                    hidesCountDisplay.textContent = data.hidesCount;
                }
                
                // Сброс всех данных при смене категории (после обновления DOM)
                const summaryTime = document.getElementById('summary-time');
                const summaryFound = document.getElementById('summary-found');
                const summaryPenalty = document.getElementById('summary-penalty');
                const summaryTotal = document.getElementById('summary-total');
                const summaryPenaltyList = document.getElementById('summary-penalty-list');
                const judgeCommentTextarea = document.getElementById('judge_comment');
                const totalPenaltyEl = document.getElementById('total-penalty');
                
                if (summaryTime) summaryTime.textContent = '0';
                if (summaryFound) summaryFound.textContent = '0';
                if (summaryPenalty) summaryPenalty.textContent = '0';
                if (summaryTotal) summaryTotal.textContent = '---';
                if (summaryPenaltyList) summaryPenaltyList.innerHTML = '';
                if (judgeCommentTextarea) judgeCommentTextarea.value = '';
                if (totalPenaltyEl) totalPenaltyEl.textContent = '0';
                
                // Сброс penaltyEvents и скрытых полей штрафов
                window.penaltyEvents = [];
                document.querySelectorAll('.penalty-card').forEach(card => {
                    const countInput = card.querySelector('input[type="hidden"]');
                    if (countInput) countInput.value = 0;
                    
                    const ruleType = card.dataset.ruleType;
                    if (ruleType === 'flat') {
                        const selectedLabel = card.querySelector('.penalty-card__selected strong');
                        if (selectedLabel) selectedLabel.textContent = '0';
                    } else {
                        const nextElement = card.querySelector('.penalty-card__next strong');
                        if (nextElement) nextElement.textContent = card.dataset.rulePoints.split(',')[0] || '0';
                        const countElement = card.querySelector('.penalty-card__count strong');
                        if (countElement) countElement.textContent = '0';
                        const addBtn = card.querySelector('.penalty-add-btn');
                        if (addBtn) {
                            addBtn.textContent = Math.round(parseFloat(card.dataset.rulePoints.split(',')[0]) || 0);
                            addBtn.disabled = false;
                        }
                    }
                    const amountElement = card.querySelector('.penalty-card__amount strong');
                    if (amountElement) amountElement.textContent = '0';
                });
                
                if (data.participants && data.participants.length > 0) {
                    data.participants.forEach(function(participant) {
                        const option = document.createElement('option');
                        option.value = participant.id;
                        option.textContent = participant.name + (participant.nickname ? ' (' + participant.nickname + ')' : '');
                        participantSelect.appendChild(option);
                    });
                    participantSelect.disabled = false;
                } else {
                    const option = document.createElement('option');
                    option.disabled = true;
                    option.textContent = 'Все участники уже прошли попытку';
                    participantSelect.appendChild(option);
                    participantSelect.disabled = true;
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки участников:', error);
                participantSelect.innerHTML = '<option disabled>Ошибка загрузки</option>';
                participantSelect.disabled = true;
            });
    }

    // Функция для сброса состояния штрафов
    function resetPenaltyState() {
        // Очищаем массив событий штрафов
        window.penaltyEvents = [];
        
        // Сбрасываем все скрытые поля штрафных счетов в 0
        penaltyCards.forEach(card => {
            const countInput = card.querySelector('input[type="hidden"]');
            if (countInput) {
                countInput.value = '0';
            }
            
            // Сбрасываем отображение для flat штрафов
            const selectedLabel = card.querySelector('.penalty-card__selected strong');
            if (selectedLabel) {
                selectedLabel.textContent = '0';
            }
            
            // Сбрасываем отображение для progressive штрафов
            const nextElement = card.querySelector('.penalty-card__next strong');
            const points = card.dataset.rulePoints.split(',').map(value => parseFloat(value));
            if (nextElement && points.length > 0) {
                nextElement.textContent = points[0];
            }
            
            const addBtn = card.querySelector('.penalty-add-btn');
            if (addBtn && points.length > 0) {
                addBtn.textContent = Math.round(points[0]);
                addBtn.disabled = false;
            }
            
            // Сбрасываем счетчики
            const countElement = card.querySelector('.penalty-card__count strong');
            if (countElement) {
                countElement.textContent = '0';
            }
            
            const amountElement = card.querySelector('.penalty-card__amount strong');
            if (amountElement) {
                amountElement.textContent = '0';
            }
        });
        
        // Сбрасываем общую сумму штрафов
        totalPenalty = 0;
        totalPenaltyElement.textContent = '0';
        summaryPenalty.textContent = '0';
        summaryTime.textContent = '0';
        summaryFound.textContent = '0';
        summaryTotal.textContent = '---';
        
        // Очищаем поле комментария судьи от деталей штрафов
        if (judgeCommentTextarea) {
            let currentComment = judgeCommentTextarea.value || '';
            const detailsIndex = currentComment.indexOf('Детали штрафов:');
            if (detailsIndex !== -1) {
                currentComment = currentComment.substring(0, detailsIndex).trim();
            }
            judgeCommentTextarea.value = currentComment;
        }
        
        // Обновляем текстовое описание штрафов (очищаем)
        updateSummaryPenaltyList();
    }
    
    const startButton = document.getElementById('start-button');
    const foundButton = document.getElementById('found-button');
    const stopButton = document.getElementById('stop-button');
    const saveButton = document.getElementById('save-button');
    const timerElement = document.getElementById('timer');
    const foundCountElement = document.getElementById('found-count');
    const foundTimesList = document.getElementById('found-times-list');
    const totalPenaltyElement = document.getElementById('total-penalty');
    const summaryPanel = document.getElementById('final-summary');
    const summaryTime = document.getElementById('summary-time');
    const summaryFound = document.getElementById('summary-found');
    const summaryPenalty = document.getElementById('summary-penalty');
    const summaryTotal = document.getElementById('summary-total');
    const summaryPenaltyList = document.getElementById('summary-penalty-list');
    const judgeCommentTextarea = document.getElementById('judge_comment');
    const hiddenTime = document.getElementById('time');
    const hiddenFound = document.getElementById('found_items');
    let penaltyCards = Array.from(document.querySelectorAll('.penalty-card'));
    const timeLimit = <?= json_encode($selectedCategory->getTimeLimit()) ?>;
    const hidesCount = <?= json_encode($selectedCategory->getHidesCount()) ?>;
    const maxScore = <?= json_encode($selectedCategory->getMaxScore()) ?>;

    let timerInterval = null;
    let startTime = 0;
    let currentTime = 0;
    let attemptCompleted = false;
    let foundCount = 0;
    let foundTimes = [];
    let penaltyEvents = [];
    let totalPenalty = 0;
    let resultSaved = false;

    // Блокировка обновления/закрытия страницы во время попытки
    function shouldBlockNavigation() {
        // Блокируем, если секундомер запущен или попытка завершена, но результат ещё не сохранён
        return timerInterval !== null || (attemptCompleted && !resultSaved);
    }

    window.addEventListener('beforeunload', function (event) {
        if (shouldBlockNavigation()) {
            event.preventDefault();
            event.returnValue = '';
            return '';
        }
    });

    function formatTime(value) {
        const minutes = Math.floor(value / 60);
        const seconds = value % 60;
        return `${String(minutes).padStart(2, '0')}:${seconds.toFixed(1).padStart(4, '0')}`;
    }

    function updateTimer() {
        const now = performance.now();
        currentTime = Math.min((now - startTime) / 1000, timeLimit);
        timerElement.textContent = formatTime(currentTime);

        if (currentTime >= timeLimit) {
            stopAttempt('time');
        }
    }

    function startAttempt() {
        if (attemptCompleted) {
            return;
        }

        startTime = performance.now() - currentTime * 1000;
        timerInterval = setInterval(updateTimer, 100);
        foundButton.disabled = false;
        stopButton.disabled = false;
        startButton.disabled = true;
    }

    function stopAttempt(reason = 'stop') {
        if (!timerInterval) {
                return;
            }

            clearInterval(timerInterval);
            timerInterval = null;
            currentTime = Math.min(currentTime, timeLimit);
            timerElement.textContent = formatTime(currentTime);
            attemptCompleted = true;
            foundButton.disabled = true;
            stopButton.disabled = true;
            startButton.disabled = false;
            saveButton.disabled = false;
            hiddenTime.value = currentTime.toFixed(1);
            hiddenFound.value = foundCount;
            renderSummary(reason);
        }

        function addFoundItem() {
            if (attemptCompleted || timerInterval === null) {
                return;
            }

            if (foundCount >= hidesCount) {
                return;
            }

            foundCount += 1;
            foundCountElement.textContent = foundCount;
            foundTimes.push(currentTime.toFixed(1));
            const item = document.createElement('li');
            item.textContent = `${foundCount}. ${currentTime.toFixed(1)} сек`;
            foundTimesList.appendChild(item);
            hiddenFound.value = foundCount;

            // Обновляем итоги попытки
            summaryFound.textContent = foundCount;
            updateSummaryPenaltyList();

            if (foundCount >= hidesCount) {
                stopAttempt('found');
            }
        }

        function updatePenaltyState() {
            totalPenalty = 0;

            penaltyCards.forEach(card => {
                const countInput = card.querySelector('input[type="hidden"]');
                const ruleType = card.dataset.ruleType;
                const points = card.dataset.rulePoints.split(',').map(value => parseFloat(value));
                let score = 0;
                let count = 0;

                if (ruleType === 'flat') {
                    count = parseFloat(countInput.value) || 0;
                    score = count;
                    const selectedLabel = card.querySelector('.penalty-card__selected strong');
                    if (selectedLabel) {
                        selectedLabel.textContent = Math.round(score);
                    }
                } else {
                    count = parseInt(countInput.value, 10) || 0;
                    for (let i = 0; i < count; i += 1) {
                        const index = Math.min(i, points.length - 1);
                        score += points[index] || 0;
                    }
                    const nextIndex = Math.min(count, points.length - 1);
                    const nextValue = points[nextIndex] || 0;
                    const nextElement = card.querySelector('.penalty-card__next strong');
                    if (nextElement) {
                        nextElement.textContent = nextValue;
                    }
                    
                    // Обновляем текст на кнопке progressive штрафа
                    const addBtn = card.querySelector('.penalty-add-btn');
                    if (addBtn) {
                        // Показываем следующее значение штрафа, которое будет добавлено
                        // Если достигнут лимит штрафов, блокируем кнопку
                        if (count >= points.length) {
                            addBtn.textContent = 'Лимит';
                            addBtn.disabled = true;
                        } else {
                            addBtn.textContent = Math.round(points[count] || 0);
                            addBtn.disabled = false;
                        }
                    }
                }

                const amountElement = card.querySelector('.penalty-card__amount strong');
                if (amountElement) {
                    amountElement.textContent = Math.round(score);
                }

                const countElement = card.querySelector('.penalty-card__count strong');
                if (countElement) {
                    countElement.textContent = count;
                }

                totalPenalty += score;
            });

            totalPenaltyElement.textContent = Math.round(totalPenalty);
            summaryPenalty.textContent = Math.round(totalPenalty);
            
            // Обновляем итоги попытки
            updateSummaryPenaltyList();
        }

        function addPenalty(card, value = null) {
            const countInput = card.querySelector('input[type="hidden"]');
            const ruleType = card.dataset.ruleType;
            const points = card.dataset.rulePoints.split(',').map(value => parseFloat(value));
            let current = ruleType === 'flat' ? parseFloat(countInput.value) || 0 : parseInt(countInput.value, 10) || 0;
            
            // Для прогрессивных штрафов проверяем, не достигнут ли лимит
            if (ruleType !== 'flat' && current >= points.length) {
                return; // Лимит достигнут, ничего не делаем
            }
            
            const ruleName = card.querySelector('.penalty-card__header strong').textContent;
            let eventScore = 0;

            if (ruleType === 'flat' && value !== null) {
                eventScore = parseFloat(value) || 0;
                current += eventScore;
                countInput.value = current;
            } else {
                eventScore = points[Math.min(current, points.length - 1)] || 0;
                current += 1;
                countInput.value = current;
            }

            penaltyEvents.push({ name: ruleName, score: Math.round(eventScore), id: Date.now() });
            updatePenaltyState();
            updateSummaryPenaltyList();

            // Проверка: если сумма штрафов >= maxScore, останавливаем попытку
            if (timerInterval !== null && totalPenalty >= maxScore) {
                stopAttempt('penalty');
            }
        }

        function removePenalty(eventId) {
            const index = penaltyEvents.findIndex(e => e.id === eventId);
            if (index === -1) {
                return;
            }

            const event = penaltyEvents[index];
            const card = penaltyCards.find(c => {
                const ruleName = c.querySelector('.penalty-card__header strong').textContent;
                return ruleName === event.name;
            });

            if (!card) {
                return;
            }

            const countInput = card.querySelector('input[type="hidden"]');
            const ruleType = card.dataset.ruleType;
            const points = card.dataset.rulePoints.split(',').map(value => parseFloat(value));

            if (ruleType === 'flat') {
                let current = parseFloat(countInput.value) || 0;
                current -= event.score;
                countInput.value = Math.max(0, current);
            } else {
                let current = parseInt(countInput.value, 10) || 0;
                if (current > 0) {
                    current -= 1;
                    countInput.value = current;
                }
            }

            penaltyEvents.splice(index, 1);
            updatePenaltyState();
            updateSummaryPenaltyList();
        }

        function updateSummaryPenaltyList() {
            // Формируем текстовое описание штрафов для поля комментария
            let penaltyText = '';
            
            if (penaltyEvents.length > 0) {
                const penaltyLines = penaltyEvents.map((event, index) => {
                    return `${index + 1}. ${event.name}: ${event.score}`;
                });
                penaltyText = 'Детали штрафов:\n' + penaltyLines.join('\n');
            }
            
            // Получаем текущий текст комментария (без деталей штрафов)
            let currentComment = judgeCommentTextarea.value || '';
            
            // Удаляем старые детали штрафов из комментария, если они есть
            const oldDetailsIndex = currentComment.indexOf('Детали штрафов:');
            if (oldDetailsIndex !== -1) {
                currentComment = currentComment.substring(0, oldDetailsIndex).trim();
            }
            
            // Добавляем новые детали штрафов
            if (penaltyText) {
                if (currentComment) {
                    currentComment += '\n\n' + penaltyText;
                } else {
                    currentComment = penaltyText;
                }
            }
            
            judgeCommentTextarea.value = currentComment;
            
            // Обновляем список штрафов в UI
            summaryPenaltyList.innerHTML = '';

            penaltyEvents.forEach((event, index) => {
                const item = document.createElement('li');
                item.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 12px;';
                
                const textSpan = document.createElement('span');
                textSpan.textContent = `${index + 1}. ${event.name}: ${event.score}`;
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.textContent = 'Удалить';
                removeBtn.className = 'button button--secondary';
                removeBtn.style.cssText = 'padding: 6px 12px; font-size: 0.8rem; min-width: auto;';
                removeBtn.addEventListener('click', () => removePenalty(event.id));
                
                item.appendChild(textSpan);
                item.appendChild(removeBtn);
                summaryPenaltyList.appendChild(item);
            });
        }

        function renderSummary(reason) {
            summaryTime.textContent = currentTime.toFixed(1);
            summaryFound.textContent = foundCount;
            summaryPenalty.textContent = totalPenalty;
            const totalScore = foundCount < hidesCount ? 0 : Math.max(0, maxScore - totalPenalty);
            summaryTotal.textContent = totalScore;
            
            updateSummaryPenaltyList();

            const totalText = reason === 'found' ? 'Завершено по найденным закладкам' : reason === 'time' ? 'Время истекло' : reason === 'penalty' ? 'Превышен лимит штрафов' : 'Попытка остановлена';
            let summaryTitle = summaryPanel.querySelector('.summary-reason');
            if (!summaryTitle) {
                summaryTitle = document.createElement('p');
                summaryTitle.classList.add('summary-reason');
                summaryTitle.style.margin = '12px 0 0';
                summaryTitle.style.color = 'var(--text-muted)';
                summaryPanel.appendChild(summaryTitle);
            }
            summaryTitle.textContent = totalText;
        }

        startButton.addEventListener('click', startAttempt);
        foundButton.addEventListener('click', addFoundItem);
        stopButton.addEventListener('click', () => stopAttempt('stop'));

        // Обработчик отправки формы - снимаем блокировку после успешного сохранения
        const judgeForm = document.getElementById('judge-form');
        if (judgeForm) {
            judgeForm.addEventListener('submit', function (event) {
                // Если результат ещё не был помечен как сохранённый, ждём завершения отправки
                if (!resultSaved) {
                    // Помечаем, что результат сохранён (форма отправляется)
                    resultSaved = true;
                    // Блокировка будет снята автоматически, так как shouldBlockNavigation() вернёт false
                }
            });
        }

        // Обработчик изменения категории
        const categorySelect = document.getElementById('category_filter_inner');
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                updateParticipantsList(this.value);
            });
        }

        penaltyCards.forEach(card => {
            const addBtn = card.querySelector('.penalty-add-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    addPenalty(card);
                });
            }

            const flatButtons = card.querySelectorAll('.penalty-flat-btn');
            flatButtons.forEach(button => {
                button.addEventListener('click', function () {
                    addPenalty(card, button.dataset.penaltyValue);
                });
            });
        });

        updatePenaltyState();
    });
</script>
</body>
</html>
