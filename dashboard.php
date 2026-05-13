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

// Получаем текущего пользователя
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? User::ROLE_ADMIN;
$userId = $currentUser['id'] ?? null;

// Получаем все соревнования и фильтруем по доступу
$competitions = $service->getCompetitions();
$accessibleCompetitions = array_filter($competitions, function($competition) {
    return hasCompetitionAccess($competition->getId());
});

// Выбираем соревнование из GET-параметра или первое доступное
$selectedCompetitionId = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);

if ($selectedCompetitionId === false || $selectedCompetitionId === null || !hasCompetitionAccess($selectedCompetitionId)) {
    $selectedCompetitionId = !empty($accessibleCompetitions) ? reset($accessibleCompetitions)->getId() : null;
}

$selectedCompetition = $selectedCompetitionId ? $service->getCompetitionById($selectedCompetitionId) : null;

if (!$selectedCompetition) {
    http_response_code(404);
    die('<h1>Соревнование не найдено</h1><p>Выбранное соревнование не существует или у вас нет доступа к нему.</p><a href="index.php">На главную</a>');
}

// Получаем категории и участников соревнования
$categories = $service->getCategoriesByCompetition($selectedCompetitionId);
$participants = $service->getParticipantsByCompetition($selectedCompetitionId);

// Определяем текущую категорию (первая, где не у всех участников есть результат)
$currentCategory = null;
$currentCategoryIndex = -1;

foreach ($categories as $index => $category) {
    $allHaveResults = true;
    foreach ($participants as $participant) {
        $result = $service->getResultByParticipantAndCategory($category->getId(), $participant->getId());
        if ($result === null) {
            $allHaveResults = false;
            break;
        }
    }
    
    if (!$allHaveResults) {
        $currentCategory = $category;
        $currentCategoryIndex = $index;
        break;
    }
}

// Если все категории завершены, текущей считается последняя
if ($currentCategory === null && !empty($categories)) {
    $currentCategory = end($categories);
    $currentCategoryIndex = count($categories) - 1;
}

// Определяем статусы участников для текущей категории
$participantStatuses = [];

if ($currentCategory !== null && !empty($participants)) {
    // Собираем информацию о результатах для всех участников в текущей категории
    $resultsMap = [];
    foreach ($participants as $participant) {
        $result = $service->getResultByParticipantAndCategory($currentCategory->getId(), $participant->getId());
        $resultsMap[$participant->getId()] = $result;
    }
    
    // Логика определения статусов:
    // 1. Если у участника есть результат - статус "Категория пройдена"
    // 2. Иначе, если это первый участник без результата И у предыдущего есть результат (или это самый первый) - статус "Прохождение"
    // 3. Иначе, если это следующий участник после того кто проходит (первый без результата) - статус "Приготовиться"
    // 4. Иначе - статус "Ожидает"
    
    // Находим индексы участников без результата
    $withoutResultIndices = [];
    for ($i = 0; $i < count($participants); $i++) {
        if ($resultsMap[$participants[$i]->getId()] === null) {
            $withoutResultIndices[] = $i;
        }
    }
    
    // Определяем статусы для каждого участника
    foreach ($participants as $index => $participant) {
        $participantId = $participant->getId();
        $hasResult = $resultsMap[$participantId] !== null;
        
        if ($hasResult) {
            // Результат записан - категория пройдена
            $status = 'Категория пройдена';
        } elseif (empty($withoutResultIndices)) {
            // Не должно произойти, но на всякий случай
            $status = 'Ожидает';
        } elseif ($withoutResultIndices[0] === $index) {
            // Это первый участник без результата
            // Если это самый первый участник в списке (индекс 0) или у предыдущего уже есть результат
            if ($index === 0 || ($index > 0 && $resultsMap[$participants[$index - 1]->getId()] !== null)) {
                // Самый первый участник или предыдущий только что завершил - этот проходит
                $status = 'Прохождение';
            } else {
                // Предыдущий ещё не завершил - этот готовится
                $status = 'Приготовиться';
            }
        } elseif (count($withoutResultIndices) > 1 && $withoutResultIndices[1] === $index) {
            // Это второй участник без результата - готовится
            $status = 'Приготовиться';
        } else {
            // Все остальные ждут
            $status = 'Ожидает';
        }
        
        $participantStatuses[$participantId] = [
            'participant' => $participant,
            'status' => $status,
            'hasResult' => $hasResult,
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд соревнования - <?= escape($selectedCompetition->getName()) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        
        .dashboard-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .dashboard-header-right {
            text-align: right;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .dashboard-header h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        
        .dashboard-logo {
            flex-shrink: 0;
        }
        
        .dashboard-logo img {
            max-width: 150px;
            height: auto;
        }
        
        .current-category-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .participants-table {
            width: 100%;
            border-collapse: collapse;
            background: #2d3748;
            color: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .participants-table th,
        .participants-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #4a5568;
        }
        
        .participants-table th {
            background: #1a202c;
            font-weight: 600;
            color: #f7fafc;
        }
        
        .participants-table tr:last-child td {
            border-bottom: none;
        }
        
        .participants-table tr:hover {
            background: #4a5568;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-waiting {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .status-ready {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background: #cce5ff;
            color: #004085;
            position: relative;
            padding-left: 28px;
        }
        
        .status-in-progress::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            background-color: #28a745;
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 50%, 100% {
                opacity: 1;
            }
            25%, 75% {
                opacity: 0.3;
            }
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .no-participants {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-header-left">
                <div class="dashboard-logo">
                    <img src="nosebrain_logo.png" alt="Nosebrain Logo">
                </div>
                <div class="dashboard-info">
                    <h1><?= escape($selectedCompetition->getName()) ?></h1>
                    <?php if ($currentCategory): ?>
                        <div class="current-category-badge">
                            Текущая категория: <?= escape($currentCategory->getName()) ?>
                        </div>
                    <?php else: ?>
                        <div class="current-category-badge">
                            Категории не определены
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-header-right">
                <span id="current-time"><?= date('H:i:s') ?></span>
            </div>
        </div>
        
        <?php if (empty($participants)): ?>
            <div class="no-participants">
                <h2>Участники не добавлены</h2>
                <p>В этом соревновании ещё нет участников.</p>
            </div>
        <?php else: ?>
            <table class="participants-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Участник</th>
                        <th>Собака</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $position = 1;
                    foreach ($participants as $participant): 
                        $participantId = $participant->getId();
                        $statusInfo = $participantStatuses[$participantId] ?? ['status' => 'Ожидает', 'hasResult' => false];
                        $status = $statusInfo['status'];
                        
                        $statusClass = 'status-waiting';
                        if ($status === 'Приготовиться') {
                            $statusClass = 'status-ready';
                        } elseif ($status === 'Прохождение') {
                            $statusClass = 'status-in-progress';
                        } elseif ($status === 'Категория пройдена') {
                            $statusClass = 'status-completed';
                        }
                        
                        $dogName = $participant->getNickname() ?: $participant->getBreed() ?: '—';
                        $dogBreed = $participant->getBreed();
                        
                        // Формируем строку: Кличка (порода)
                        if ($participant->getNickname() && $dogBreed) {
                            $dogDisplay = escape($participant->getNickname()) . ' (' . escape($dogBreed) . ')';
                        } elseif ($participant->getNickname()) {
                            $dogDisplay = escape($participant->getNickname());
                        } elseif ($dogBreed) {
                            $dogDisplay = escape($dogBreed);
                        } else {
                            $dogDisplay = '—';
                        }
                    ?>
                        <tr>
                            <td><?= $position++ ?></td>
                            <td><?= escape($participant->getName()) ?></td>
                            <td><?= $dogDisplay ?></td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= escape($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
        // Автоматическое обновление страницы каждые 30 секунд
        setTimeout(function() {
            location.reload();
        }, 30000);
        
        // Обновление времени каждую секунду
        function updateTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('current-time').textContent = `${hours}:${minutes}:${seconds}`;
        }
        
        setInterval(updateTime, 1000);
    </script>
</body>
</html>
