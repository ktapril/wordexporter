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
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

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

        // уникальное имя файла по ID соревнования
        $fileName = 'competition_' . $competitionId . '.docx';
        $filePath = $templatesDir . '/' . $fileName;

        // отправка загруженнего файла из временной папки в /templates/
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл на диск']);
            return;
        }

        // путь, который хранится в БД 
        $relativePath = 'templates/' . $fileName;

        // загрузка соревнования из БД, обновление template_path, сохранение
        try {
            $db = new \NoseworkV2\DatabaseManager();
            $competition = $db->getCompetitionById($competitionId);

            if ($competition === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Соревнование не найдено']);
                return;
            }

            $competition->setTemplatePath($relativePath);
            $db->updateCompetition($competition);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
            return;
        }

        echo json_encode(['success' => true, 'path' => $relativePath]);
    }
}