<?php

// принимает загруженный .docx, сохраняет в /templates/, записывает путь в БД

class TemplateController
{
    public function handle(): void
    {
        $competitionId = (int)($_POST['competition_id'] ?? 0);

        if ($competitionId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Не указан ID соревнования']);
            return;
        }

        // проверка прихода файла
        if (empty($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Файл не загружен или произошла ошибка загрузки']);
            return;
        }

        $file = $_FILES['template'];

        // проверка расширения (только .docx)
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'docx') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Разрешены только файлы .docx']);
            return;
        }

        // папка для хранения шаблонов
        $templatesDir = __DIR__ . '/../../templates';

        // создание папки
        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }

        // уникальное имя файла
        $fileName = 'competition_' . $competitionId . '.docx';
        $filePath = $templatesDir . '/' . $fileName;

        // отправка загруженнего файла из временной папки в /templates/
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
            return;
        }

        // путь, который хранится в БД 
        $relativePath = 'templates/' . $fileName;

        // загрузка соревнования из БД, обновление template_path, сохранение
        $appService = new \NoseworkV2\AppService();
        $competition = $appService->getCompetitionById($competitionId);

        if ($competition === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Соревнование не найдено']);
            return;
        }

        $competition->setTemplatePath($relativePath);
        $appService->updateCompetition($competition);

        echo json_encode(['success' => true, 'path' => $relativePath]);
    }
}