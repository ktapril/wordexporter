<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\PenaltyRule;
use NoseworkV2\User;

// Требуем авторизацию и права на управление соревнованием
requireAuth();

if (!canManageCompetition()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для управления этим соревнованием.</p><a href="index.php">На главную</a>');
}

// Проверяем, имеет ли пользователь право добавлять/удалять участников (не доступно судьям)
$canManageParticipants = canManageParticipants();

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

// Получаем текущего пользователя для фильтрации участников
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? User::ROLE_ADMIN;
$userId = $currentUser['id'] ?? null;

function parsePenaltyRules(array $names, array $types, array $points): array
{
    $rules = [];
    $count = max(count($names), count($types), count($points));

    for ($i = 0; $i < $count; $i++) {
        $name = trim($names[$i] ?? '');
        $type = strtolower(trim($types[$i] ?? ''));
        $pointString = trim($points[$i] ?? '');

        if ($name === '' || !in_array($type, [PenaltyRule::TYPE_FLAT, PenaltyRule::TYPE_PROGRESSIVE], true) || $pointString === '') {
            continue;
        }

        $pointValues = array_filter(array_map('trim', explode(',', $pointString)));
        if (empty($pointValues)) {
            continue;
        }

        $pointInts = array_map(function ($value) {
            return filter_var($value, FILTER_VALIDATE_INT);
        }, $pointValues);

        if (in_array(false, $pointInts, true)) {
            continue;
        }

        $rules[] = [
            'name' => $name,
            'type' => $type,
            'points' => array_map('intval', $pointInts),
        ];
    }

    return $rules;
}

$competitions = $service->getCompetitions();

// Фильтруем соревнования по доступу пользователя (поддержка множественных назначений)
$accessibleCompetitions = array_filter($competitions, function($competition) {
    return hasCompetitionAccess($competition->getId());
});

// Выбираем первое доступное соревнование или то, которое передано в GET-параметре
$selectedCompetitionId = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);

// Если competition_id не указан или недоступен, выбираем первое доступное
if ($selectedCompetitionId === false || $selectedCompetitionId === null || !hasCompetitionAccess($selectedCompetitionId)) {
    $selectedCompetitionId = !empty($accessibleCompetitions) ? reset($accessibleCompetitions)->getId() : null;
}

$selectedCompetition = $selectedCompetitionId ? $service->getCompetitionById($selectedCompetitionId) : null;
$categories = $selectedCompetitionId !== null ? $service->getCategoriesByCompetition($selectedCompetitionId) : [];

// Получаем участников с учётом роли пользователя (администратор видит всех, секретарь - только своих)
$participants = $service->getParticipants($userId, $userRole);
$competitionParticipants = $selectedCompetitionId !== null ? $service->getParticipantsByCompetition($selectedCompetitionId) : [];
$assignedParticipantIds = array_map(fn($participant) => $participant->getId(), $competitionParticipants);
$availableParticipants = array_filter($participants, fn($participant) => !in_array($participant->getId(), $assignedParticipantIds, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectUrl = $_SERVER['PHP_SELF'] . '?competition_id=' . $selectedCompetitionId;

    if ($action === 'delete_competition') {
        // Обработка AJAX-запроса на удаление соревнования
        header('Content-Type: application/json');
        
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'У вас нет прав для удаления соревнований.']);
            exit;
        }
        
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
        $password = $_POST['password'] ?? '';
        
        if ($competitionId === false || $competitionId === null) {
            echo json_encode(['success' => false, 'message' => 'Некорректный ID соревнования.']);
            exit;
        }
        
        if ($password === '') {
            echo json_encode(['success' => false, 'message' => 'Введите пароль.']);
            exit;
        }
        
        // Проверяем пароль текущего пользователя
        $currentUser = getCurrentUser();
        $userId = $currentUser['id'] ?? null;
        
        if ($userId === null) {
            echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован.']);
            exit;
        }
        
        $user = $service->getUserById($userId);
        if ($user === null || !password_verify($password, $user->getPasswordHash())) {
            echo json_encode(['success' => false, 'message' => 'Неверный пароль.']);
            exit;
        }
        
        try {
            $service->deleteCompetition($competitionId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Ошибка при удалении соревнования: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'edit_competition') {
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['competition_name'] ?? ''));
        $description = trim((string)($_POST['competition_description'] ?? ''));
        $startDate = trim((string)($_POST['start_date'] ?? '')) ?: null;
        $endDate = trim((string)($_POST['end_date'] ?? '')) ?: null;
        
        // Получаем данные о судьях и секретаре из формы
        $judgeIds = isset($_POST['judge_ids']) && is_array($_POST['judge_ids']) 
            ? array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $_POST['judge_ids']) 
            : [];
        $judgeIds = array_filter($judgeIds);
        
        $secretaryId = isset($_POST['secretary_id']) && $_POST['secretary_id'] !== '' 
            ? filter_var($_POST['secretary_id'], FILTER_VALIDATE_INT) 
            : null;

        if ($competitionId === false || $name === '') {
            $message = 'Пожалуйста, заполните название соревнования.';
            $messageType = 'error';
        } else {
            $service->updateCompetition($competitionId, $name, $description, $startDate, $endDate);
            
            // Обновляем судей и секретаря только если пользователь администратор
            if (isAdmin()) {
                $service->updateCompetitionStaff($competitionId, $judgeIds, $secretaryId);
            }
            
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    if ($action === 'create_category') {
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $timeLimit = filter_var($_POST['time_limit'] ?? '', FILTER_VALIDATE_FLOAT);
        $hidesCount = filter_var($_POST['hides_count'] ?? '', FILTER_VALIDATE_INT);
        $maxScore = filter_var($_POST['max_score'] ?? '', FILTER_VALIDATE_FLOAT);
        $penaltyRules = parsePenaltyRules(
            $_POST['penalty_name'] ?? [],
            $_POST['penalty_type'] ?? [],
            $_POST['penalty_points'] ?? []
        );

        if ($competitionId === false || $name === '' || $timeLimit === false || $timeLimit < 0 || $hidesCount === false || $hidesCount < 0 || $maxScore === false || $maxScore < 0 || empty($penaltyRules)) {
            $message = 'Пожалуйста, заполните все поля категории и правила штрафов.';
            $messageType = 'error';
        } else {
            $service->createCategory($competitionId, $name, $timeLimit, $hidesCount, $maxScore, $penaltyRules);
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    if ($action === 'edit_category') {
        $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $timeLimit = filter_var($_POST['time_limit'] ?? '', FILTER_VALIDATE_FLOAT);
        $hidesCount = filter_var($_POST['hides_count'] ?? '', FILTER_VALIDATE_INT);
        $maxScore = filter_var($_POST['max_score'] ?? '', FILTER_VALIDATE_INT);
        $penaltyRules = parsePenaltyRules(
            $_POST['penalty_name'] ?? [],
            $_POST['penalty_type'] ?? [],
            $_POST['penalty_points'] ?? []
        );

        if ($categoryId === false || $competitionId === false || $name === '' || $timeLimit === false || $timeLimit < 0 || $hidesCount === false || $hidesCount < 0 || $maxScore === false || $maxScore < 0 || empty($penaltyRules)) {
            $message = 'Пожалуйста, заполните все поля категории и правила штрафов.';
            $messageType = 'error';
        } else {
            $service->updateCategory($categoryId, $name, $timeLimit, $hidesCount, $maxScore, $penaltyRules);
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    if ($action === 'delete_category') {
        $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);

        if ($categoryId === false || $competitionId === false) {
            $message = 'Не удалось удалить категорию.';
            $messageType = 'error';
        } else {
            $service->deleteCategory($categoryId);
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    if ($action === 'assign_participant') {
        if (!$canManageParticipants) {
            $message = 'У вас нет прав для добавления участников.';
            $messageType = 'error';
        } else {
            $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
            $participantIds = $_POST['participant_ids'] ?? [];
            
            if (!is_array($participantIds)) {
                $participantIds = [$participantIds];
            }
            
            if ($competitionId === false || empty($participantIds)) {
                $message = 'Выберите соревнование и хотя бы одного участника.';
                $messageType = 'error';
            } else {
                foreach ($participantIds as $participantId) {
                    $pid = filter_var($participantId, FILTER_VALIDATE_INT);
                    if ($pid !== false) {
                        $service->assignParticipantToCompetition($competitionId, $pid);
                    }
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }

    if ($action === 'remove_participant') {
        if (!$canManageParticipants) {
            $message = 'У вас нет прав для удаления участников.';
            $messageType = 'error';
        } else {
            $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
            $participantId = filter_var($_POST['participant_id'] ?? '', FILTER_VALIDATE_INT);

            if ($competitionId === false || $participantId === false) {
                $message = 'Не удалось удалить участника.';
                $messageType = 'error';
            } else {
                $service->removeParticipantFromCompetition($competitionId, $participantId);
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }

    if ($action === 'update_participant_order') {
        if (!$canManageParticipants) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'У вас нет прав для изменения порядка участников.']);
            exit;
        }
        
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
        $participantIds = $_POST['participant_ids'] ?? [];
        
        if (!is_array($participantIds)) {
            $participantIds = [$participantIds];
        }
        
        // Фильтруем и валидируем ID участников
        $participantIds = array_filter(array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $participantIds));
        
        if ($competitionId === false || empty($participantIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Некорректные данные.']);
            exit;
        }
        
        try {
            $service->updateParticipantSortOrder($competitionId, $participantIds);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении порядка.']);
        }
        exit;
    }

    if ($action === 'update_category_order') {
        $competitionId = filter_var($_POST['competition_id'] ?? '', FILTER_VALIDATE_INT);
        $categoryIds = $_POST['category_ids'] ?? [];

        if (!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }


        
        // Фильтруем и валидируем ID категорий
        $categoryIds = array_filter(array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $categoryIds));

        if ($competitionId === false || empty($categoryIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Некорректные данные.']);
            exit;
        }

        try {
            $service->updateCategorySortOrder($competitionId, $categoryIds);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении порядка категорий.']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Менеджер соревнования — <?= $selectedCompetition ? escape($selectedCompetition->getName()) : 'Nosework' ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .manage-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .manage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .manage-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        .manage-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .section-panel {
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 28px;
            margin-bottom: 24px;
        }
        .section-panel h2 {
            margin: 0 0 18px;
            font-size: 1.35rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
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
        .modal {
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
        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
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
        .category-row-actions {
            display: flex;
            gap: 8px;
        }
        .btn-small {
            padding: 8px 14px;
            font-size: 0.85rem;
            border-radius: 12px;
        }
        .draggable-row {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        .draggable-row.dragging {
            opacity: 0.5;
            background: rgba(255, 255, 255, 0.1);
        }
        .drag-handle {
            cursor: grab;
            touch-action: none;
            text-align: center;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .button--danger {
            background: #dc3545;
            color: white;
            border: none;
        }
        .button--danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
<?php
$currentPage = 'manage';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell">
    <div class="manage-container">
        <a href="index.php" class="back-link">← Вернуться на главную</a>
        <?php if ($message !== ''): ?>
            <div class="alert alert--<?= escape($messageType) ?>"><?= escape($message) ?></div>
        <?php endif; ?>

        <?php if (!$selectedCompetition): ?>
            <div class="section-panel">
                <h2 class="manage-title">Нет выбранного соревнования</h2>
                <p>Перейдите на страницу соревнования через кнопку "Управление" в списке соревнований.</p>
                <div class="button-group" style="margin-top: 18px;">
                    <a class="button" href="index.php">К списку соревнований</a>
                </div>
            </div>
        <?php else: ?>
            <div class="manage-header">
                <h1 class="manage-title"><?= escape($selectedCompetition->getName()) ?></h1>
                <div class="manage-actions">
                    <a class="button button--secondary" href="results.php?competition_id=<?= $selectedCompetition->getId() ?>">Результаты</a>
                    <?php 
                        $canAccessJudgeManage = canAccessJudging($selectedCompetition->getId());
                        $isActiveManage = $selectedCompetition->isActive();
                    ?>
                    <?php if ($canAccessJudgeManage): ?>
                        <a class="button button--secondary" href="judge.php?competition_id=<?= $selectedCompetition->getId() ?>">Судья</a>
                    <?php else: ?>
                        <button class="button button--secondary" disabled title="<?= !$isActiveManage ? 'Соревнование не активно' : '' ?>">Судья</button>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                        <button type="button" class="button button--danger btn-small" onclick="openDeleteCompetitionModal()">Удалить соревнование</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Секция: Информация о соревновании -->
            <div class="section-panel">
                <h2>
                    Информация о соревновании
                    <button type="button" class="button btn-small" onclick="openModal('edit-competition-modal')">Отредактировать соревнование</button>
                </h2>
                <div style="color: var(--text-muted); line-height: 1.7;">
                    <?php if ($selectedCompetition->getDescription()): ?>
                        <p><?= nl2br(escape($selectedCompetition->getDescription())) ?></p>
                    <?php else: ?>
                        <p><em>Описание не указано</em></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Секция: Категории -->
            <div class="section-panel">
                <h2>
                    Категории
                    <button type="button" class="button btn-small" onclick="openModal('add-category-modal')">Добавить категорию</button>
                </h2>
                <?php if (count($categories) === 0): ?>
                    <div class="empty-state">У этого соревнования пока нет категорий.</div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table" id="categories-table">
                            <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Название</th>
                                <th>Время</th>
                                <th>Закладки</th>
                                <th>Макс. балл</th>
                                <th>Действия</th>
                            </tr>
                            </thead>
                            <tbody id="categories-tbody">
                            <?php foreach ($categories as $index => $category): ?>
                                <tr data-category-id="<?= $category->getId() ?>" class="draggable-row" draggable="true">
                                    <td class="drag-handle" title="Перетащите для изменения порядка"><?= $index + 1 ?></td>
                                    <td><?= escape($category->getName()) ?></td>
                                    <td><?= number_format($category->getTimeLimit(), 1) ?> сек</td>
                                    <td><?= $category->getHidesCount() ?></td>
                                    <td><?= $category->getMaxScore() ?></td>
                                    <td>
                                        <div class="category-row-actions">
                                            <button type="button" class="button button--secondary btn-small" onclick='openEditCategoryModal(<?= json_encode([
                                                'id' => $category->getId(),
                                                'name' => $category->getName(),
                                                'timeLimit' => $category->getTimeLimit(),
                                                'hidesCount' => $category->getHidesCount(),
                                                'maxScore' => $category->getMaxScore(),
                                                'penaltyRules' => array_map(fn($rule) => [
                                                    'name' => $rule->getName(),
                                                    'type' => $rule->getType(),
                                                    'points' => implode(', ', $rule->getPoints())
                                                ], $category->getPenaltyRules())
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Редактировать</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Вы уверены, что хотите удалить категорию?')">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">
                                                <input type="hidden" name="category_id" value="<?= $category->getId() ?>">
                                                <button type="submit" class="button button--secondary btn-small">Удалить</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin-top: 16px; color: var(--text-muted); font-size: 0.9rem;">
                        💡 Перетаскивайте категории, чтобы изменить порядок. Порядок сохраняется автоматически.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Секция: Участники -->
            <div class="section-panel">
                <h2>
                    Участники
                    <?php if ($canManageParticipants): ?>
                    <button type="button" class="button btn-small" onclick="openModal('add-participant-modal')">Добавить участников</button>
                    <?php endif; ?>
                </h2>
                <?php if (count($competitionParticipants) === 0): ?>
                    <div class="empty-state">В этом соревновании пока нет участников.</div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table" id="participants-table">
                            <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Имя</th>
                                <th>Кличка</th>
                                <th>Порода</th>
                                <th>Действия</th>
                            </tr>
                            </thead>
                            <tbody id="participants-tbody">
                            <?php foreach ($competitionParticipants as $index => $participant): ?>
                                <tr data-participant-id="<?= $participant->getId() ?>" class="draggable-row" draggable="true">
                                    <td class="drag-handle" title="Перетащите для изменения порядка">☰</td>
                                    <td><?= escape($participant->getName()) ?></td>
                                    <td><?= escape($participant->getNickname() ?? '-') ?></td>
                                    <td><?= escape($participant->getBreed() ?? '-') ?></td>
                                    <td>
                                        <?php if ($canManageParticipants): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Вы уверены, что хотите удалить участника из соревнования?')">
                                            <input type="hidden" name="action" value="remove_participant">
                                            <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">
                                            <input type="hidden" name="participant_id" value="<?= $participant->getId() ?>">
                                            <button type="submit" class="button button--secondary btn-small">Удалить</button>
                                        </form>
                                        <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">Нет прав</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($canManageParticipants): ?>
                    <p style="margin-top: 16px; color: var(--text-muted); font-size: 0.9rem;">
                        💡 Перетаскивайте участников, чтобы изменить порядок старта. Порядок сохраняется автоматически.
                    </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Модальное окно: Редактирование соревнования -->
    <div id="edit-competition-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Отредактировать соревнование</h3>
                <button type="button" class="modal-close" onclick="closeModal('edit-competition-modal')">&times;</button>
            </div>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="edit_competition">
                <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">

                <div class="form-control">
                    <label for="competition_name">Название</label>
                    <input id="competition_name" name="competition_name" type="text" value="<?= escape($selectedCompetition->getName()) ?>" required>
                </div>
                <div class="form-control">
                    <label for="competition_description">Описание</label>
                    <textarea id="competition_description" name="competition_description" rows="4"><?= escape($selectedCompetition->getDescription()) ?></textarea>
                </div>
                <div class="form-control">
                    <label for="start_date">Дата начала</label>
                    <input id="start_date" name="start_date" type="date" value="<?= escape($selectedCompetition->getStartDate() ?? '') ?>">
                </div>
                <div class="form-control">
                    <label for="end_date">Дата окончания</label>
                    <input id="end_date" name="end_date" type="date" value="<?= escape($selectedCompetition->getEndDate() ?? '') ?>">
                </div>
                
                <?php if (isAdmin()): ?>
                <div class="form-control">
                    <label for="judge_ids">Судьи</label>
                    <select id="judge_ids" name="judge_ids[]" multiple style="min-height: 120px;">
                        <?php 
                        $allJudges = $service->getAllJudges();
                        $currentJudgeIds = array_map(fn($j) => $j->getId(), $service->getJudgesByCompetition($selectedCompetition->getId()));
                        foreach ($allJudges as $judge): 
                            $selected = in_array($judge->getId(), $currentJudgeIds, true) ? ' selected' : '';
                            $displayName = $judge->getDisplayName() ?: $judge->getUsername();
                            $competitionsList = '';
                            if (!empty($judge->getCompetitionIds())) {
                                $compNames = [];
                                foreach ($judge->getCompetitionIds() as $compId) {
                                    $comp = $service->getCompetitionById($compId);
                                    if ($comp) {
                                        $compNames[] = $comp->getName();
                                    }
                                }
                                $competitionsList = ' (' . implode(', ', $compNames) . ')';
                            }
                        ?>
                        <option value="<?= $judge->getId() ?>"<?= $selected ?>>
                            <?= escape($displayName . $competitionsList) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Удерживайте Ctrl/Cmd для выбора нескольких судей</small>
                </div>
                
                <div class="form-control">
                    <label for="secretary_id">Секретарь</label>
                    <select id="secretary_id" name="secretary_id">
                        <option value="">-- Не назначен --</option>
                        <?php 
                        $allSecretaries = $service->getAllSecretaries();
                        $currentSecretary = $service->getSecretaryByCompetition($selectedCompetition->getId());
                        $currentSecretaryId = $currentSecretary ? $currentSecretary->getId() : null;
                        foreach ($allSecretaries as $secretary): 
                            $selected = ($secretary->getId() === $currentSecretaryId) ? ' selected' : '';
                            $displayName = $secretary->getDisplayName() ?: $secretary->getUsername();
                            $competitionsList = '';
                            if (!empty($secretary->getCompetitionIds())) {
                                $compNames = [];
                                foreach ($secretary->getCompetitionIds() as $compId) {
                                    $comp = $service->getCompetitionById($compId);
                                    if ($comp) {
                                        $compNames[] = $comp->getName();
                                    }
                                }
                                $competitionsList = ' (' . implode(', ', $compNames) . ')';
                            }
                        ?>
                        <option value="<?= $secretary->getId() ?>"<?= $selected ?>>
                            <?= escape($displayName . $competitionsList) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="button-group">
                    <button type="submit" class="button">Сохранить</button>
                    <button type="button" class="button button--secondary" onclick="closeModal('edit-competition-modal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно: Подтверждение удаления соревнования -->
    <div id="delete-competition-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Удаление соревнования</h3>
                <button type="button" class="modal-close" onclick="closeModal('delete-competition-modal')">&times;</button>
            </div>
            <p style="margin-bottom: 20px;">
                Вы действительно хотите безвозвратно удалить соревнование 
                <strong id="delete-competition-name" style="color: var(--text);"></strong>?
            </p>
            <p style="color: #dc3545; font-size: 0.9rem; margin-bottom: 20px;">
                ⚠️ Это действие удалит все результаты, категории и информацию о соревновании. Участники останутся в списке участников.
            </p>
            <div class="button-group">
                <button type="button" class="button button--danger" onclick="confirmDeleteWithPassword()">Да, удалить</button>
                <button type="button" class="button button--secondary" onclick="closeModal('delete-competition-modal')">Отмена</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно: Ввод пароля администратора -->
    <div id="delete-competition-password-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Подтверждение пароля</h3>
                <button type="button" class="modal-close" onclick="closeModal('delete-competition-password-modal')">&times;</button>
            </div>
            <p style="margin-bottom: 20px;">
                Для подтверждения удаления введите пароль от вашей учётной записи:
            </p>
            <form id="delete-competition-form" novalidate>
                <input type="hidden" name="action" value="delete_competition">
                <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">
                
                <div class="form-control">
                    <label for="admin-password">Пароль</label>
                    <input id="admin-password" name="password" type="password" required autocomplete="current-password">
                </div>
                
                <div class="button-group">
                    <button type="submit" class="button button--danger">Удалить</button>
                    <button type="button" class="button button--secondary" onclick="closeModal('delete-competition-password-modal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно: Добавление категории -->
    <div id="add-category-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Добавить категорию</h3>
                <button type="button" class="modal-close" onclick="closeModal('add-category-modal')">&times;</button>
            </div>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="create_category">
                <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">

                <?php if (isAdmin() && count($service->getStandardRuleTemplates()) > 0): ?>
                <div class="form-control">
                    <label for="template_select">Использовать стандартный шаблон (необязательно)</label>
                    <select id="template_select" onchange="applyTemplate()">
                        <option value="">-- Выбрать шаблон --</option>
                        <?php foreach ($service->getStandardRuleTemplates() as $template): ?>
                            <option value="<?= $template->getId() ?>" 
                                    data-time-limit="<?= $template->getTimeLimit() ?>"
                                    data-hides-count="<?= $template->getHidesCount() ?>"
                                    data-max-score="<?= $template->getMaxScore() ?>"
                                    data-penalty-rules='<?= json_encode(array_map(fn($rule) => [
                                        'name' => $rule->getName(),
                                        'type' => $rule->getType(),
                                        'points' => implode(', ', $rule->getPoints())
                                    ], $template->getPenaltyRules()), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                <?= escape($template->getName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-control">
                    <label for="new_category_name">Название</label>
                    <input id="new_category_name" name="category_name" type="text" placeholder="Название категории" required>
                </div>
                <div class="form-control">
                    <label for="new_time_limit">Время (сек)</label>
                    <input id="new_time_limit" name="time_limit" type="number" step="0.1" min="0" placeholder="120" required>
                </div>
                <div class="form-control">
                    <label for="new_hides_count">Закладки</label>
                    <input id="new_hides_count" name="hides_count" type="number" min="0" placeholder="5" required>
                </div>
                <div class="form-control">
                    <label for="new_max_score">Максимальный балл</label>
                    <input id="new_max_score" name="max_score" type="number" min="0" placeholder="100" required>
                </div>
                <div class="form-control">
                    <label>Правила штрафов</label>
                    <div id="penalty-rule-list" style="display: grid; gap: 12px;">
                        <div class="penalty-rule-row" style="display: grid; grid-template-columns: 1.6fr 1fr 1fr auto; gap: 10px; align-items: center;">
                            <input type="text" name="penalty_name[0]" placeholder="Название штрафа" required>
                            <select name="penalty_type[0]">
                                <option value="flat">flat</option>
                                <option value="progressive">progressive</option>
                            </select>
                            <input type="text" name="penalty_points[0]" placeholder="Баллы через запятую" required>
                            <button type="button" class="button button--secondary btn-small remove-penalty-rule-button">Удалить</button>
                        </div>
                    </div>
                    <div class="button-group" style="margin-top: 10px;">
                        <button type="button" class="button button--secondary btn-small" id="add-penalty-rule-button">Добавить правило</button>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="button">Добавить категорию</button>
                    <button type="button" class="button button--secondary" onclick="closeModal('add-category-modal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно: Редактирование категории -->
    <div id="edit-category-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Редактировать категорию</h3>
                <button type="button" class="modal-close" onclick="closeModal('edit-category-modal')">&times;</button>
            </div>
            <form method="post" novalidate>
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">
                <input type="hidden" name="category_id" id="edit-category-id">

                <div class="form-control">
                    <label for="edit-category-name">Название</label>
                    <input id="edit-category-name" name="category_name" type="text" required>
                </div>
                <div class="form-control">
                    <label for="edit-time-limit">Время (сек)</label>
                    <input id="edit-time-limit" name="time_limit" type="number" step="0.1" min="0" required>
                </div>
                <div class="form-control">
                    <label for="edit-hides-count">Закладки</label>
                    <input id="edit-hides-count" name="hides_count" type="number" min="0" required>
                </div>
                <div class="form-control">
                    <label for="edit-max-score">Максимальный балл</label>
                    <input id="edit-max-score" name="max_score" type="number" min="0" required>
                </div>
                <div class="form-control">
                    <label>Штрафы</label>
                    <div id="edit-penalty-rule-list" style="display: grid; gap: 12px;"></div>
                    <div class="button-group" style="margin-top: 10px;">
                        <button type="button" class="button button--secondary btn-small" id="add-edit-penalty-rule-button">Добавить правило</button>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="button">Сохранить</button>
                    <button type="button" class="button button--secondary" onclick="closeModal('edit-category-modal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно: Добавление участников -->
    <div id="add-participant-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Добавить участников</h3>
                <button type="button" class="modal-close" onclick="closeModal('add-participant-modal')">&times;</button>
            </div>
            <?php if ($canManageParticipants && count($availableParticipants) > 0): ?>
                <form method="post" novalidate>
                    <input type="hidden" name="action" value="assign_participant">
                    <input type="hidden" name="competition_id" value="<?= $selectedCompetition->getId() ?>">

                    <div class="form-control">
                        <label>Выберите участников</label>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; padding: 12px;">
                            <?php foreach ($availableParticipants as $participant): ?>
                                <label style="display: flex; align-items: center; gap: 10px; padding: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="participant_ids[]" value="<?= $participant->getId() ?>" style="width: 18px; height: 18px;">
                                    <span><?= escape($participant->getName()) ?><?= $participant->getNickname() ? ' (' . escape($participant->getNickname()) . ')' : '' ?><?php if ($participant->getBreed()): ?> — <?= escape($participant->getBreed()) ?><?php endif; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="button">Назначить выбранных</button>
                        <button type="button" class="button button--secondary" onclick="closeModal('add-participant-modal')">Отмена</button>
                    </div>
                </form>
            <?php elseif (!$canManageParticipants): ?>
                <div class="empty-state">У вас нет прав для добавления участников.</div>
                <div class="button-group" style="margin-top: 18px;">
                    <button type="button" class="button button--secondary" onclick="closeModal('add-participant-modal')">Закрыть</button>
                </div>
            <?php else: ?>
                <div class="empty-state">Нет доступных участников для добавления. Сначала создайте участников в разделе "Участники".</div>
                <div class="button-group" style="margin-top: 18px;">
                    <button type="button" class="button button--secondary" onclick="closeModal('add-participant-modal')">Закрыть</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
// Функции управления модальными окнами
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Закрытие по клику на overlay
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(event) {
        if (event.target === overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// Функция открытия модального окна редактирования категории
function openEditCategoryModal(categoryData) {
    document.getElementById('edit-category-id').value = categoryData.id;
    document.getElementById('edit-category-name').value = categoryData.name;
    document.getElementById('edit-time-limit').value = categoryData.timeLimit;
    document.getElementById('edit-hides-count').value = categoryData.hidesCount;
    document.getElementById('edit-max-score').value = categoryData.maxScore;
    
    const penaltyList = document.getElementById('edit-penalty-rule-list');
    penaltyList.innerHTML = '';
    
    if (categoryData.penaltyRules && categoryData.penaltyRules.length > 0) {
        categoryData.penaltyRules.forEach(function(rule, index) {
            const row = document.createElement('div');
            row.className = 'penalty-rule-row';
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1.6fr 1fr 1fr auto';
            row.style.gap = '10px';
            row.style.alignItems = 'center';
            row.innerHTML = `
                <input type="text" name="penalty_name[${index}]" value="${escapeHtml(rule.name)}" placeholder="Название штрафа" required>
                <select name="penalty_type[${index}]">
                    <option value="flat"${rule.type === 'flat' ? ' selected' : ''}>flat</option>
                    <option value="progressive"${rule.type === 'progressive' ? ' selected' : ''}>progressive</option>
                </select>
                <input type="text" name="penalty_points[${index}]" value="${escapeHtml(rule.points)}" placeholder="Баллы через запятую" required>
                <button type="button" class="button button--secondary btn-small remove-penalty-rule-button">Удалить</button>
            `;
            penaltyList.appendChild(row);
        });
    } else {
        const row = document.createElement('div');
        row.className = 'penalty-rule-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '1.6fr 1fr 1fr auto';
        row.style.gap = '10px';
        row.style.alignItems = 'center';
        row.innerHTML = `
            <input type="text" name="penalty_name[0]" placeholder="Название штрафа" required>
            <select name="penalty_type[0]">
                <option value="flat">flat</option>
                <option value="progressive">progressive</option>
            </select>
            <input type="text" name="penalty_points[0]" placeholder="Баллы через запятую" required>
            <button type="button" class="button button--secondary btn-small remove-penalty-rule-button">Удалить</button>
        `;
        penaltyList.appendChild(row);
    }
    
    openModal('edit-category-modal');
}

// Экранирование HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Управление правилами штрафов в модалке добавления категории
document.addEventListener('DOMContentLoaded', function () {
    // Глобальные функции для модальных окон удаления соревнования
    window.openDeleteCompetitionModal = function() {
        document.getElementById('delete-competition-name').textContent = '<?= escape($selectedCompetition->getName()) ?>';
        openModal('delete-competition-modal');
    };
    
    window.confirmDeleteWithPassword = function() {
        closeModal('delete-competition-modal');
        openModal('delete-competition-password-modal');
    };
    
    const ruleList = document.getElementById('penalty-rule-list');
    const addButton = document.getElementById('add-penalty-rule-button');
    let ruleIndex = 1;

    if (addButton && ruleList) {
        addButton.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'penalty-rule-row';
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1.6fr 1fr 1fr auto';
            row.style.gap = '10px';
            row.style.alignItems = 'center';
            row.innerHTML = `
                <input type="text" name="penalty_name[${ruleIndex}]" placeholder="Название штрафа" required>
                <select name="penalty_type[${ruleIndex}]">
                    <option value="flat">flat</option>
                    <option value="progressive">progressive</option>
                </select>
                <input type="text" name="penalty_points[${ruleIndex}]" placeholder="Баллы через запятую" required>
                <button type="button" class="button button--secondary btn-small remove-penalty-rule-button">Удалить</button>
            `;

            ruleList.appendChild(row);
            ruleIndex += 1;
        });
    }

    // Управление удалением правил штрафов
    document.addEventListener('click', function (event) {
        const target = event.target;
        if (target.classList.contains('remove-penalty-rule-button')) {
            const row = target.closest('.penalty-rule-row');
            if (row) {
                row.remove();
            }
        }
    });

    // Управление правилами штрафов в модалке редактирования категории
    const editRuleList = document.getElementById('edit-penalty-rule-list');
    const editAddButton = document.getElementById('add-edit-penalty-rule-button');
    let editRuleIndex = 1;

    if (editAddButton && editRuleList) {
        editAddButton.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'penalty-rule-row';
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1.6fr 1fr 1fr auto';
            row.style.gap = '10px';
            row.style.alignItems = 'center';
            row.innerHTML = `
                <input type="text" name="penalty_name[${editRuleIndex}]" placeholder="Название штрафа" required>
                <select name="penalty_type[${editRuleIndex}]">
                    <option value="flat">flat</option>
                    <option value="progressive">progressive</option>
                </select>
                <input type="text" name="penalty_points[${editRuleIndex}]" placeholder="Баллы через запятую" required>
                <button type="button" class="button button--secondary btn-small remove-penalty-rule-button">Удалить</button>
            `;

            editRuleList.appendChild(row);
            editRuleIndex += 1;
        });
    }
});

// Функция применения стандартного шаблона
function applyTemplate() {
    const select = document.getElementById('template_select');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!selectedOption.value) {
        return;
    }
    
    const timeLimit = selectedOption.getAttribute('data-time-limit');
    const hidesCount = selectedOption.getAttribute('data-hides-count');
    const maxScore = selectedOption.getAttribute('data-max-score');
    const penaltyRulesJson = selectedOption.getAttribute('data-penalty-rules');
    
    // Заполняем основные поля
    document.getElementById('new_time_limit').value = timeLimit;
    document.getElementById('new_hides_count').value = hidesCount;
    document.getElementById('new_max_score').value = maxScore;
    
    // Заполняем правила штрафов
    const ruleList = document.getElementById('penalty-rule-list');
    ruleList.innerHTML = '';
    
    if (penaltyRulesJson) {
        const penaltyRules = JSON.parse(penaltyRulesJson);
        penaltyRules.forEach(function(rule, index) {
            const row = document.createElement('div');
            row.className = 'penalty-rule-row';
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1.6fr 1fr 1fr auto';
            row.style.gap = '10px';
            row.style.alignItems = 'center';
            row.innerHTML = `
                <input type="text" name="penalty_name[${index}]" value="${escapeHtml(rule.name)}" placeholder="Название штрафа" required>
                <select name="penalty_type[${index}]">
                    <option value="flat"${rule.type === 'flat' ? ' selected' : ''}>flat</option>
                    <option value="progressive"${rule.type === 'progressive' ? ' selected' : ''}>progressive</option>
                </select>
                <input type="text" name="penalty_points[${index}]" value="${escapeHtml(rule.points)}" placeholder="Баллы через запятую" required>
                <button type="button" class="button button--secondary btn-small remove-penalty-rule-button">Удалить</button>
            `;
            ruleList.appendChild(row);
        });
    }
}

// Drag and Drop для сортировки участников
(function() {
    const tbody = document.getElementById('participants-tbody');
    if (!tbody) return;
    
    let draggedRow = null;
    let saveTimeout = null;
    
    // Добавляем обработчики событий на строки таблицы
    tbody.addEventListener('dragstart', function(e) {
        const row = e.target.closest('tr.draggable-row');
        if (!row) return;
        
        draggedRow = row;
        setTimeout(function() {
            row.style.visibility = 'hidden';
        }, 0);
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.participantId);
    });
    
    tbody.addEventListener('dragend', function(e) {
        const row = e.target.closest('tr.draggable-row');
        if (!row) return;
        
        row.style.visibility = '';
        row.classList.remove('dragging');
        draggedRow = null;
        
        // Сохраняем порядок после завершения перетаскивания
        saveParticipantOrder();
    });
    
    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const targetRow = e.target.closest('tr.draggable-row');
        if (!targetRow || !draggedRow || targetRow === draggedRow) return;
        
        const rect = targetRow.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;
        
        if (e.clientY < midpoint) {
            targetRow.parentNode.insertBefore(draggedRow, targetRow);
        } else {
            targetRow.parentNode.insertBefore(draggedRow, targetRow.nextSibling);
        }
    });
    
    tbody.addEventListener('drop', function(e) {
        e.preventDefault();
    });
    
    // Функция сохранения порядка участников
    function saveParticipantOrder() {
        // Очищаем предыдущий таймер
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }
        
        // Устанавливаем новый таймер
        saveTimeout = setTimeout(function() {
            const rows = tbody.querySelectorAll('tr.draggable-row');
            const participantIds = Array.from(rows).map(row => row.dataset.participantId);
            
            const formData = new FormData();
            formData.append('action', 'update_participant_order');
            formData.append('competition_id', <?= $selectedCompetition->getId() ?>);
            participantIds.forEach(id => formData.append('participant_ids[]', id));
            
            fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем номера строк
                    updateRowNumbers();
                } else {
                    console.error('Ошибка при сохранении порядка:', data.message);
                    alert('Не удалось сохранить порядок участников: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка сети:', error);
                alert('Не удалось сохранить порядок участников.');
            });
        }, 300); // Задержка 300мс после окончания перетаскивания
    }
    
    // Функция обновления номеров строк
    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.draggable-row');
        rows.forEach((row, index) => {
            const handleCell = row.querySelector('.drag-handle');
            if (handleCell) {
                handleCell.textContent = (index + 1);
            }
        });
    }
})();

// Drag and Drop для сортировки категорий
(function() {
    const tbody = document.getElementById('categories-tbody');
    if (!tbody) return;

    let draggedRow = null;
    let saveTimeout = null;

    // Добавляем обработчики событий на строки таблицы
    tbody.addEventListener('dragstart', function(e) {
        const row = e.target.closest('tr.draggable-row');
        if (!row) return;

        draggedRow = row;
        setTimeout(function() {
            row.style.visibility = 'hidden';
        }, 0);
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.categoryId);
    });

    tbody.addEventListener('dragend', function(e) {
        const row = e.target.closest('tr.draggable-row');
        if (!row) return;

        row.style.visibility = '';
        row.classList.remove('dragging');
        draggedRow = null;

        // Сохраняем порядок после завершения перетаскивания
        saveCategoryOrder();
    });

    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const targetRow = e.target.closest('tr.draggable-row');
        if (!targetRow || !draggedRow || targetRow === draggedRow) return;

        const rect = targetRow.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;

        if (e.clientY < midpoint) {
            targetRow.parentNode.insertBefore(draggedRow, targetRow);
        } else {
            targetRow.parentNode.insertBefore(draggedRow, targetRow.nextSibling);
        }
    });

    tbody.addEventListener('drop', function(e) {
        e.preventDefault();
    });

    // Функция сохранения порядка категорий
    function saveCategoryOrder() {
        // Очищаем предыдущий таймер
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }

        // Устанавливаем новый таймер
        saveTimeout = setTimeout(function() {
            const rows = tbody.querySelectorAll('tr.draggable-row');
            const categoryIds = Array.from(rows).map(row => row.dataset.categoryId);

            const formData = new FormData();
            formData.append('action', 'update_category_order');
            formData.append('competition_id', <?= $selectedCompetition->getId() ?>);
            categoryIds.forEach(id => formData.append('category_ids[]', id));

            fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем номера строк
                    updateRowNumbers();
                } else {
                    console.error('Ошибка при сохранении порядка:', data.message);
                    alert('Не удалось сохранить порядок категорий: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка сети:', error);
                alert('Не удалось сохранить порядок категорий.');
            });
        }, 300); // Задержка 300мс после окончания перетаскивания
    }

    // Функция обновления номеров строк
    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.draggable-row');
        rows.forEach((row, index) => {
            const handleCell = row.querySelector('.drag-handle');
            if (handleCell) {
                handleCell.textContent = (index + 1);
            }
        });
    }

    // Глобальная функция для обработки удаления соревнования
    window.submitDeleteCompetitionForm = function() {
        const password = document.getElementById('admin-password').value;
        const competitionId = <?= $selectedCompetition->getId() ?>;
        
        fetch('manage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'delete_competition',
                competition_id: competitionId,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.php';
            } else {
                alert(data.message || 'Ошибка при удалении соревнования');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            alert('Произошла ошибка при удалении соревнования');
        });
    };
    
    // Обработчик отправки формы удаления соревнования
    const deleteForm = document.getElementById('delete-competition-form');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            window.submitDeleteCompetitionForm();
        });
    }
})();
</script>
</body>
</html>
