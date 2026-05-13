<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;

header('Content-Type: application/json');

// Требуем авторизацию
requireAuth();

// Проверяем право на удаление результатов
if (!canDeleteResults()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit;
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

// Получаем данные из запроса
$input = json_decode(file_get_contents('php://input'), true);
$resultId = $input['result_id'] ?? null;
$competitionId = $input['competition_id'] ?? null;

if ($resultId === null || !is_int($resultId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный ID результата']);
    exit;
}

if ($competitionId === null || !is_int($competitionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный ID соревнования']);
    exit;
}

// Проверяем доступ к соревнованию
if (!hasCompetitionAccess($competitionId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit;
}

try {
    $user = getCurrentUser();
    $deletedByUserId = $user['id'];

    // Удаляем результат
    $success = $service->deleteResult($resultId, $deletedByUserId);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Не удалось удалить результат']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
