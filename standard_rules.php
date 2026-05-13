<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\PenaltyRule;

// Требуем авторизацию и права администратора
requireAuth();

if (!isAdmin()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>Только администраторы могут управлять стандартными правилами.</p><a href="index.php">На главную</a>');
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

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

$templates = $service->getStandardRuleTemplates();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_template') {
        $name = trim((string)($_POST['template_name'] ?? ''));
        $timeLimit = filter_var($_POST['time_limit'] ?? '', FILTER_VALIDATE_FLOAT);
        $hidesCount = filter_var($_POST['hides_count'] ?? '', FILTER_VALIDATE_INT);
        $maxScore = filter_var($_POST['max_score'] ?? '', FILTER_VALIDATE_INT);
        $penaltyRules = parsePenaltyRules(
            $_POST['penalty_name'] ?? [],
            $_POST['penalty_type'] ?? [],
            $_POST['penalty_points'] ?? []
        );

        if ($name === '' || $timeLimit === false || $timeLimit < 0 || $hidesCount === false || $hidesCount < 0 || $maxScore === false || $maxScore < 0 || empty($penaltyRules)) {
            $message = 'Пожалуйста, заполните все поля шаблона и правила штрафов.';
            $messageType = 'error';
        } else {
            $service->createStandardRuleTemplate($name, $timeLimit, $hidesCount, $maxScore, $penaltyRules);
            header('Location: standard_rules.php?success=created');
            exit;
        }
    }

    if ($action === 'delete_template') {
        $templateId = filter_var($_POST['template_id'] ?? '', FILTER_VALIDATE_INT);

        if ($templateId === false) {
            $message = 'Не удалось удалить шаблон.';
            $messageType = 'error';
        } else {
            $service->deleteStandardRuleTemplate($templateId);
            header('Location: standard_rules.php?success=deleted');
            exit;
        }
    }

    if ($action === 'update_template') {
        $templateId = filter_var($_POST['template_id'] ?? '', FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['template_name'] ?? ''));
        $timeLimit = filter_var($_POST['time_limit'] ?? '', FILTER_VALIDATE_FLOAT);
        $hidesCount = filter_var($_POST['hides_count'] ?? '', FILTER_VALIDATE_INT);
        $maxScore = filter_var($_POST['max_score'] ?? '', FILTER_VALIDATE_INT);
        $penaltyRules = parsePenaltyRules(
            $_POST['penalty_name'] ?? [],
            $_POST['penalty_type'] ?? [],
            $_POST['penalty_points'] ?? []
        );

        if ($templateId === false || $name === '' || $timeLimit === false || $timeLimit < 0 || $hidesCount === false || $hidesCount < 0 || $maxScore === false || $maxScore < 0 || empty($penaltyRules)) {
            $message = 'Пожалуйста, заполните все поля шаблона и правила штрафов.';
            $messageType = 'error';
        } else {
            $service->updateStandardRuleTemplate($templateId, $name, $timeLimit, $hidesCount, $maxScore, $penaltyRules);
            header('Location: standard_rules.php?success=updated');
            exit;
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'created') {
    $message = 'Стандартный шаблон правил успешно создан.';
    $messageType = 'success';
} elseif (isset($_GET['success']) && $_GET['success'] === 'deleted') {
    $message = 'Стандартный шаблон правил успешно удалён.';
    $messageType = 'success';
} elseif (isset($_GET['success']) && $_GET['success'] === 'updated') {
    $message = 'Стандартный шаблон правил успешно обновлён.';
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стандартные правила — Nosework</title>
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
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-muted);
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 1rem;
        }
        .penalty-rule-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .btn-small {
            padding: 8px 14px;
            font-size: 0.85rem;
            border-radius: 12px;
        }
        .alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        .alert--success {
            background: rgba(76, 175, 80, 0.15);
            border-color: rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }
        .alert--error {
            background: rgba(244, 67, 54, 0.15);
            border-color: rgba(244, 67, 54, 0.3);
            color: #f44336;
        }
        .alert--info {
            background: rgba(33, 150, 243, 0.15);
            border-color: rgba(33, 150, 243, 0.3);
            color: #2196f3;
        }
        .template-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 14px;
        }
        .template-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .template-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }
        .template-card-info {
            display: flex;
            gap: 16px;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
        .template-card-penalties {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .template-card-penalties strong {
            color: var(--text);
        }
    </style>
</head>
<body>
<?php
$currentPage = 'standard_rules';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell">
    <div class="manage-container">
        <a href="index.php" class="back-link">← Вернуться на главную</a>
        <?php if ($message !== ''): ?>
            <div class="alert alert--<?= escape($messageType) ?>"><?= escape($message) ?></div>
        <?php endif; ?>

        <div class="manage-header">
            <h1 class="manage-title">Стандартные шаблоны правил</h1>
            <button type="button" class="button" onclick="openModal('add-template-modal')">Добавить шаблон</button>
        </div>

        <div class="section-panel">
            <h2>Список шаблонов</h2>
            <?php if (count($templates) === 0): ?>
                <div class="empty-state">Пока нет созданных стандартных шаблонов правил.</div>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <div class="template-card">
                        <div class="template-card-header">
                            <h3 class="template-card-title"><?= escape($template->getName()) ?></h3>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="button button--secondary btn-small" onclick="openEditModal(<?= $template->getId() ?>, <?= htmlspecialchars(json_encode($template->getName()), ENT_QUOTES) ?>, <?= $template->getTimeLimit() ?>, <?= $template->getHidesCount() ?>, <?= $template->getMaxScore() ?>, <?= htmlspecialchars(json_encode(array_map(fn($r) => ['name' => $r->getName(), 'type' => $r->getType(), 'points' => $r->getPoints()], $template->getPenaltyRules())), ENT_QUOTES) ?>)">Редактировать</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этот шаблон?');">
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" value="<?= $template->getId() ?>">
                                    <button type="submit" class="button button--secondary btn-small">Удалить</button>
                                </form>
                            </div>
                        </div>
                        <div class="template-card-info">
                            <span>⏱️ Время: <strong><?= number_format($template->getTimeLimit(), 1) ?> сек</strong></span>
                            <span>🎯 Закладки: <strong><?= $template->getHidesCount() ?></strong></span>
                            <span>📊 Макс. балл: <strong><?= $template->getMaxScore() ?></strong></span>
                        </div>
                        <div class="template-card-penalties">
                            <strong>Правила штрафов:</strong>
                            <ul style="margin: 8px 0 0 20px; padding: 0;">
                                <?php foreach ($template->getPenaltyRules() as $rule): ?>
                                    <li>
                                        <?= escape($rule->getName()) ?> 
                                        (<?= $rule->getType() === PenaltyRule::TYPE_FLAT ? 'плоский' : 'прогрессивный' ?>): 
                                        <?= escape(implode(', ', $rule->getPoints())) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Модальное окно: Добавить шаблон -->
<div id="add-template-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Добавить стандартный шаблон правил</h2>
            <button type="button" class="modal-close" onclick="closeModal('add-template-modal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create_template">
            
            <div class="form-group">
                <label for="template_name">Название шаблона *</label>
                <input type="text" id="template_name" name="template_name" required placeholder="Например: Начальный уровень">
            </div>
            
            <div class="form-group">
                <label for="time_limit">Время (секунды) *</label>
                <input type="number" id="time_limit" name="time_limit" step="0.1" min="0" required placeholder="Например: 60">
            </div>
            
            <div class="form-group">
                <label for="hides_count">Количество закладок *</label>
                <input type="number" id="hides_count" name="hides_count" min="1" required placeholder="Например: 3">
            </div>
            
            <div class="form-group">
                <label for="max_score">Максимальный балл *</label>
                <input type="number" id="max_score" name="max_score" min="0" required placeholder="Например: 100">
            </div>
            
            <div class="form-group">
                <label>Правила штрафов *</label>
                <div id="penalty-rules-container">
                    <div class="penalty-rule-row">
                        <input type="text" name="penalty_name[]" placeholder="Название штрафа" required>
                        <select name="penalty_type[]" required>
                            <option value="flat">Плоский</option>
                            <option value="progressive">Прогрессивный</option>
                        </select>
                        <input type="text" name="penalty_points[]" placeholder="Очки (через запятую)" required>
                        <button type="button" class="button button--secondary btn-small" onclick="removePenaltyRule(this)">✕</button>
                    </div>
                </div>
                <button type="button" class="button button--secondary btn-small" onclick="addPenaltyRule()" style="margin-top: 8px;">+ Добавить правило</button>
            </div>
            
            <div class="modal-footer">
                <button type="submit" class="button" style="flex: 1;">Сохранить</button>
                <button type="button" class="button button--secondary" onclick="closeModal('add-template-modal')" style="flex: 1;">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: Редактировать шаблон -->
<div id="edit-template-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Редактировать стандартный шаблон правил</h2>
            <button type="button" class="modal-close" onclick="closeModal('edit-template-modal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update_template">
            <input type="hidden" id="edit_template_id" name="template_id">
            
            <div class="form-group">
                <label for="edit_template_name">Название шаблона *</label>
                <input type="text" id="edit_template_name" name="template_name" required placeholder="Например: Начальный уровень">
            </div>
            
            <div class="form-group">
                <label for="edit_time_limit">Время (секунды) *</label>
                <input type="number" id="edit_time_limit" name="time_limit" step="0.1" min="0" required placeholder="Например: 60">
            </div>
            
            <div class="form-group">
                <label for="edit_hides_count">Количество закладок *</label>
                <input type="number" id="edit_hides_count" name="hides_count" min="1" required placeholder="Например: 3">
            </div>
            
            <div class="form-group">
                <label for="edit_max_score">Максимальный балл *</label>
                <input type="number" id="edit_max_score" name="max_score" min="0" required placeholder="Например: 100">
            </div>
            
            <div class="form-group">
                <label>Правила штрафов *</label>
                <div id="edit-penalty-rules-container">
                </div>
                <button type="button" class="button button--secondary btn-small" onclick="addEditPenaltyRule()" style="margin-top: 8px;">+ Добавить правило</button>
            </div>
            
            <div class="modal-footer">
                <button type="submit" class="button" style="flex: 1;">Сохранить</button>
                <button type="button" class="button button--secondary" onclick="closeModal('edit-template-modal')" style="flex: 1;">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    function addPenaltyRule() {
        const container = document.getElementById('penalty-rules-container');
        const newRow = document.createElement('div');
        newRow.className = 'penalty-rule-row';
        newRow.innerHTML = `
            <input type="text" name="penalty_name[]" placeholder="Название штрафа" required>
            <select name="penalty_type[]" required>
                <option value="flat">Плоский</option>
                <option value="progressive">Прогрессивный</option>
            </select>
            <input type="text" name="penalty_points[]" placeholder="Очки (через запятую)" required>
            <button type="button" class="button button--secondary btn-small" onclick="removePenaltyRule(this)">✕</button>
        `;
        container.appendChild(newRow);
    }

    function removePenaltyRule(button) {
        const container = document.getElementById('penalty-rules-container');
        if (container.children.length > 1) {
            button.parentElement.remove();
        } else {
            alert('Должно быть хотя бы одно правило штрафа.');
        }
    }

    function addEditPenaltyRule(name = '', type = 'flat', points = '') {
        const container = document.getElementById('edit-penalty-rules-container');
        const newRow = document.createElement('div');
        newRow.className = 'penalty-rule-row';
        newRow.innerHTML = `
            <input type="text" name="penalty_name[]" placeholder="Название штрафа" required value="${escapeHtml(name)}">
            <select name="penalty_type[]" required>
                <option value="flat" ${type === 'flat' ? 'selected' : ''}>Плоский</option>
                <option value="progressive" ${type === 'progressive' ? 'selected' : ''}>Прогрессивный</option>
            </select>
            <input type="text" name="penalty_points[]" placeholder="Очки (через запятую)" required value="${escapeHtml(points)}">
            <button type="button" class="button button--secondary btn-small" onclick="removeEditPenaltyRule(this)">✕</button>
        `;
        container.appendChild(newRow);
    }

    function removeEditPenaltyRule(button) {
        const container = document.getElementById('edit-penalty-rules-container');
        if (container.children.length > 1) {
            button.parentElement.remove();
        } else {
            alert('Должно быть хотя бы одно правило штрафа.');
        }
    }

    function openEditModal(id, name, timeLimit, hidesCount, maxScore, penaltyRules) {
        document.getElementById('edit_template_id').value = id;
        document.getElementById('edit_template_name').value = name;
        document.getElementById('edit_time_limit').value = timeLimit;
        document.getElementById('edit_hides_count').value = hidesCount;
        document.getElementById('edit_max_score').value = maxScore;

        // Очищаем контейнер правил штрафов
        const container = document.getElementById('edit-penalty-rules-container');
        container.innerHTML = '';

        // Заполняем правила штрафов
        if (penaltyRules && penaltyRules.length > 0) {
            penaltyRules.forEach(function(rule) {
                const pointsStr = Array.isArray(rule.points) ? rule.points.join(', ') : rule.points;
                addEditPenaltyRule(rule.name, rule.type, pointsStr);
            });
        } else {
            addEditPenaltyRule();
        }

        openModal('edit-template-modal');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Закрытие модального окна по клику на оверлей
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
</script>
</body>
</html>
