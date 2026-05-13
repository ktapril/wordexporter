<?php

require_once 'vendor/autoload.php';

use NoseworkV2\DatabaseManager;
use NoseworkV2\CompetitionService;

header('Content-Type: application/json');

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

if ($categoryId === false || $categoryId === null) {
    echo json_encode(['participants' => []]);
    exit;
}

$category = $service->getCategoryById($categoryId);
if (!$category) {
    echo json_encode(['participants' => []]);
    exit;
}

// Получаем соревнование по категории
$competitionId = $category->getCompetitionId();
$competitionParticipants = $service->getParticipantsByCompetition($competitionId);

// Получаем результаты для данной категории
$existingResults = $service->getResultsByCategory($categoryId);
$participantsWithResults = [];
foreach ($existingResults as $result) {
    if ($result->getParticipantId() !== null) {
        $participantsWithResults[] = $result->getParticipantId();
    }
}

// Фильтруем участников - оставляем только тех, у кого нет результата в этой категории
$availableParticipants = [];
foreach ($competitionParticipants as $participant) {
    if (!in_array($participant->getId(), $participantsWithResults, true)) {
        $availableParticipants[] = [
            'id' => $participant->getId(),
            'name' => $participant->getName(),
            'nickname' => $participant->getNickname()
        ];
    }
}

echo json_encode([
    'participants' => $availableParticipants,
    'hidesCount' => $category->getHidesCount(),
    'penaltyRules' => array_map(function ($rule) {
        return [
            'id' => $rule->getId(),
            'name' => $rule->getName(),
            'type' => $rule->getType(),
            'points' => $rule->getPoints()
        ];
    }, $category->getPenaltyRules())
]);
