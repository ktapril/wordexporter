<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;

header('Content-Type: application/json');

// Требуем авторизацию
requireAuth();

// Проверяем право на восстановление результатов (только Судья и Администратор)
if (!canDeleteResults()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    exit;
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

// Получаем данные из запроса
$input = json_decode(file_get_contents('php://input'), true);
$deletedResultId = $input['deleted_result_id'] ?? null;
$competitionId = $input['competition_id'] ?? null;

if ($deletedResultId === null || !is_int($deletedResultId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный ID удалённого результата']);
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
    $restoredByUserId = $user['id'];

    // Восстанавливаем результат
    $result = $service->restoreResult($deletedResultId, $restoredByUserId);

    if ($result['status'] === 'success') {
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } elseif ($result['status'] === 'exists') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => $result['message'], 'type' => 'exists']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['message']]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
