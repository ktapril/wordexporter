<?php

class DeleteTemplateController
{
    public function handle(): void
    {
        $competitionId = (int)($_POST['competition_id'] ?? 0);

        if ($competitionId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Не указан ID соревнования']);
            return;
        }

        $db = new \NoseworkV2\DatabaseManager();
        $competition = $db->getCompetitionById($competitionId);

        if ($competition === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Соревнование не найдено']);
            return;
        }

        // удаление файла с диска если он есть
        $templatePath = $competition->getTemplatePath();

        if ($templatePath !== null) {
            $absolutePath = __DIR__ . '/../../' . $templatePath;
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }
        }

        // обнуление template_path в БД
        $competition->setTemplatePath(null);
        $db->updateCompetition($competition);

        echo json_encode(['success' => true]);
    }
}