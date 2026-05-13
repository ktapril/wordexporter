<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;

header('Content-Type: application/json');

// Требуем авторизацию
requireAuth();

// Проверяем право на публикацию результатов
if (!canPublishResults()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'У вас нет прав для выполнения этого действия.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['competition_id']) || !isset($input['action'])) {
        throw new \InvalidArgumentException('Некорректные входные данные.');
    }
    
    $competitionId = (int)$input['competition_id'];
    $action = $input['action'];
    
    if (!in_array($action, ['publish', 'unpublish'], true)) {
        throw new \InvalidArgumentException('Недопустимое действие.');
    }
    
    $dbManager = new DatabaseManager();
    $service = new CompetitionService($dbManager);
    
    // Проверяем доступ к соревнованию
    $competition = $service->getCompetitionById($competitionId);
    if ($competition === null) {
        throw new \RuntimeException('Соревнование не найдено.');
    }
    
    // Проверяем права доступа к соревнованию
    if (!hasCompetitionAccess($competitionId)) {
        throw new \RuntimeException('У вас нет доступа к этому соревнованию.');
    }
    
    if ($action === 'publish') {
        $service->publishCompetitionResults($competitionId);
        echo json_encode(['success' => true, 'message' => 'Результаты опубликованы.']);
    } else {
        $service->unpublishCompetitionResults($competitionId);
        echo json_encode(['success' => true, 'message' => 'Результаты сняты с публикации.']);
    }
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\RuntimeException $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Произошла непредвиденная ошибка.']);
}
