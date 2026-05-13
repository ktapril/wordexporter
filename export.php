<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;

// Требуем авторизацию и права на экспорт результатов (админ или секретарь)
requireAuth();

if (!canExportResults()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для экспорта результатов.</p><a href="index.php">На главную</a>');
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

/**
 * Форматирует время из секунд в формат минуты:секунды.десятые
 * @param float $seconds Время в секундах
 * @return string Время в формате ММ:СС.д
 */
function formatTime(float $seconds): string
{
    $minutes = (int) floor($seconds / 60);
    $remainingSeconds = fmod($seconds, 60);
    $wholeSeconds = (int) floor($remainingSeconds);
    $tenths = (int) round(($remainingSeconds - $wholeSeconds) * 10);
    
    return sprintf('%02d:%02d.%d', $minutes, $wholeSeconds, $tenths);
}

$selectedCompetitionId = filter_input(INPUT_GET, 'competition_id', FILTER_VALIDATE_INT);
$selectedCompetition = $selectedCompetitionId ? $service->getCompetitionById($selectedCompetitionId) : null;
$categories = $selectedCompetitionId !== null ? $service->getCategoriesByCompetition($selectedCompetitionId) : [];
$overallResults = $selectedCompetitionId !== null ? $service->getOverallResults($selectedCompetitionId) : [];
$participants = $selectedCompetitionId !== null ? $service->getParticipantsByCompetition($selectedCompetitionId) : [];

// Обработка формы экспорта
$exportedTable = null;
$participantOrder = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем порядок участников из POST-данных
    $participantOrderRaw = $_POST['participant_order'] ?? '';
    
    // Декодируем JSON-строку в массив
    if (!empty($participantOrderRaw)) {
        $decoded = json_decode($participantOrderRaw, true);
        if (is_array($decoded)) {
            $participantOrder = $decoded;
        }
    }
    
    if ($selectedCompetitionId !== null && count($categories) > 0 && !empty($participantOrder)) {
        // Собираем данные для таблицы
        $tableData = [];
        
        foreach ($participants as $participant) {
            $participantId = $participant->getId();
            $participantName = $participant->getName();
            
            $categoryResults = [];
            $totalScore = 0;
            $totalTime = 0;
            
            foreach ($categories as $category) {
                $result = $service->getResultByParticipantAndCategory($category->getId(), $participantId);
                if ($result !== null) {
                    $categoryResults[$category->getId()] = [
                        'score' => $result->getTotalScore(),
                        'time' => $result->getTime()
                    ];
                    $totalScore += $result->getTotalScore();
                    $totalTime += $result->getTime();
                } else {
                    $categoryResults[$category->getId()] = null;
                }
            }
            
            $tableData[] = [
                'participant_id' => $participantId,
                'participant_name' => $participantName,
                'breed' => $participant->getBreed() ?? '',
                'nickname' => $participant->getNickname() ?? '',
                'gender' => $participant->getGender() ?? '',
                'birth_date' => $participant->getBirthDate() ?? '',
                'microchip_number' => $participant->getMicrochipNumber() ?? '',
                'pedigree_number' => $participant->getPedigreeNumber() ?? '',
                'qualification_book_number' => $participant->getQualificationBookNumber() ?? '',
                'instructor_name' => $participant->getInstructorName() ?? '',
                'category_results' => $categoryResults,
                'total_score' => $totalScore,
                'total_time' => $totalTime
            ];
        }
        
        // Создаем карту мест участников на основе их реальных результатов
        // Сортировка: по убыванию баллов, затем по возрастанию времени
        $sortedByResults = $tableData;
        usort($sortedByResults, function($a, $b) {
            // Сначала сравниваем по баллам (по убыванию)
            if ($a['total_score'] !== $b['total_score']) {
                return $b['total_score'] - $a['total_score'];
            }
            // Если баллы равны, сравниваем по времени (по возрастанию)
            return $a['total_time'] <=> $b['total_time'];
        });
        
        // Присваиваем места участникам
        $placesMap = [];
        $currentPlace = 1;
        foreach ($sortedByResults as $index => $row) {
            $pid = $row['participant_id'];
            // Если это не первый элемент и результаты такие же как у предыдущего, место то же
            if ($index > 0) {
                $prevRow = $sortedByResults[$index - 1];
                if ($row['total_score'] === $prevRow['total_score'] && $row['total_time'] === $prevRow['total_time']) {
                    // Место такое же как у предыдущего
                    $placesMap[$pid] = $currentPlace;
                    continue;
                }
            }
            $currentPlace = $index + 1;
            $placesMap[$pid] = $currentPlace;
        }
        
        // Сортировка данных согласно порядку в participantOrder
        $orderedData = [];
        foreach ($participantOrder as $pid) {
            foreach ($tableData as $row) {
                if ($row['participant_id'] == $pid) {
                    // Добавляем место к данным строки
                    $row['place'] = $placesMap[$pid] ?? 0;
                    $orderedData[] = $row;
                    break;
                }
            }
        }
        
        $exportedTable = $orderedData;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Экспорт результатов — Nosework</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .export-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .export-form {
            margin-bottom: 24px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 16px;
            border: 1px solid var(--border);
        }
        .export-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
        }
        .participant-list-draggable {
            list-style: none;
            padding: 0;
            margin: 0 0 16px 0;
        }
        .participant-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 8px;
            cursor: grab;
            user-select: none;
            color: var(--text);
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .participant-item:active {
            cursor: grabbing;
        }
        .participant-item.dragging {
            opacity: 0.5;
            background: rgba(255, 255, 255, 0.08);
        }
        .participant-item .drag-handle {
            cursor: grab;
            color: var(--text-muted);
            font-size: 18px;
            user-select: none;
            flex-shrink: 0;
        }
        .export-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            background: white;
            color: black;
            font-family: 'Times New Roman', Times, serif;
            font-size: 9pt;
        }
        .export-table th,
        .export-table td {
            border: 1px solid black;
            padding: 10px;
            text-align: center;
            color: black;
            font-weight: normal;
        }
        .export-table th.header-bold {
            font-weight: bold;
        }
        .export-table th.vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
        }
        .export-table td.vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
        }
        .export-table tr:hover {
            background: white;
        }
        .result-cell {
            display: block;
            font-size: 9pt;
            line-height: 1.4;
            color: black;
            font-weight: normal;
        }
        .place-cell {
            text-align: center;
            font-weight: normal;
            color: black;
        }
        .no-data {
            color: #999;
            font-style: italic;
        }
        .copy-button {
            margin-top: 16px;
            padding: 12px 24px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/docx/8.5.0/docx.umd.min.js"></script>
</head>
<body>
<?php
$currentPage = 'view_results';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Экспорт результатов</div>
            <?php if ($selectedCompetition === null): ?>
                <h1 class="hero__title">Соревнование не найдено</h1>
                <p class="hero__subtitle">Выбранное соревнование недоступно или было удалено.</p>
            <?php else: ?>
                <h1 class="hero__title"><?= escape($selectedCompetition->getName()) ?></h1>
                <p class="hero__subtitle">Формирование итоговой таблицы для экспорта</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="content-grid content-grid--single">
        <?php if ($selectedCompetition !== null): ?>
            <div class="export-container">
                <a href="results.php?competition_id=<?= $selectedCompetitionId ?>" class="back-link">← Вернуться к результатам</a>
                
                <?php if (count($categories) === 0): ?>
                    <div class="empty-state">У этого соревнования нет категорий.</div>
                <?php else: ?>
                    <div class="export-form">
                        <form method="post" id="exportForm">
                            <label>Порядок следования участников в итоговой таблице:</label>
                            <p style="margin-bottom: 12px; font-size: 14px; color: #ccc;">Перетаскивайте участников мышью, чтобы изменить порядок.</p>
                            <ul class="participant-list-draggable" id="participantList">
                                <?php foreach ($participants as $participant): ?>
                                    <li class="participant-item" data-id="<?= $participant->getId() ?>" draggable="true">
                                        <span class="drag-handle">☰</span>
                                        <span><?= escape($participant->getName()) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <input type="hidden" name="participant_order" id="participantOrderInput" value="">
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
    <button type="submit" name="export_action" value="view" class="button">
        Сформировать для копирования
    </button>

    <button type="submit" name="export_action" value="download" class="button" style="background-color: #2b5797;">
        Скачать .docx
    </button>
</div>
                    </div>

                    <?php if ($exportedTable !== null): ?>
                        <article class="panel">
                            <h2>Итоговая таблица результатов</h2>
                            <div class="table-wrapper" style="margin-top: 12px;">
                                <table class="export-table">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="header-bold">№ п/п</th>
                                            <th rowspan="2" class="header-bold">Порода</th>
                                            <th rowspan="2" class="header-bold">Кличка</th>
                                            <th rowspan="2" class="vertical-text">Пол</th>
                                            <th rowspan="2" class="vertical-text">Дата рождения</th>
                                            <th rowspan="2" class="header-bold">№ клейма или микрочипа</th>
                                            <th rowspan="2" class="vertical-text">№ родословной</th>
                                            <th rowspan="2" class="vertical-text">№ квал. книжки</th>
                                            <th rowspan="2" class="vertical-text">Владелец, проводник</th>
                                            <th colspan="<?= count($categories) ?>" class="header-bold"><span style="font-weight: bold;">Результаты по категориям</span><br><span style="font-weight: normal;">баллы, время</span></th>
                                            <th colspan="2" class="header-bold">Итоговый результат</th>
                                            <th rowspan="2" class="header-bold">Ф.И.О. инструктора</th>
                                        </tr>
                                        <tr>
                                            <?php foreach ($categories as $category): ?>
                                                <th class="vertical-text"><?= escape($category->getName()) ?></th>
                                            <?php endforeach; ?>
                                            <th>Баллы, время</th>
                                            <th>Место</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rowNumber = 0;
                                        foreach ($exportedTable as $row): 
                                            $rowNumber++;
                                        ?>
                                            <tr>
                                                <td><?= $rowNumber ?></td>
                                                <td><?= escape($row['breed']) ?></td>
                                                <td><?= escape($row['nickname']) ?></td>
                                                <td class="vertical-text"><?= escape($row['gender']) ?></td>
                                                <td class="vertical-text"><?= escape($row['birth_date']) ?></td>
                                                <td><?= escape($row['microchip_number']) ?></td>
                                                <td class="vertical-text"><?= escape($row['pedigree_number']) ?></td>
                                                <td class="vertical-text"><?= escape($row['qualification_book_number']) ?></td>
                                                <td><?= escape($row['participant_name']) ?></td>
                                                <?php foreach ($categories as $category): ?>
                                                    <td>
                                                        <?php if ($row['category_results'][$category->getId()] !== null): ?>
                                                            <span class="result-cell">
                                                                <?= $row['category_results'][$category->getId()]['score'] ?> б.<br>
                                                                <?= formatTime($row['category_results'][$category->getId()]['time']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="no-data">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td>
                                                    <span class="result-cell">
                                                        <?= $row['total_score'] ?> б.<br>
                                                        <?= formatTime($row['total_time']) ?>
                                                    </span>
                                                </td>
                                                <td class="place-cell"><?= $row['place'] ?></td>
                                                <td><?= escape($row['instructor_name']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" class="copy-button button" id="copyTableButton">Скопировать</button>
                            </div>
                        </article>
                    <?php else: ?>
                        <div class="empty-state">Перетащите участников, чтобы задать порядок, и нажмите кнопку "Сформировать таблицу", чтобы увидеть результаты.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
(function() {
    const list = document.getElementById('participantList');
    const orderInput = document.getElementById('participantOrderInput');
    const form = document.getElementById('exportForm');
    let draggedItem = null;

    function updateOrderInput() {
        const items = list.querySelectorAll('.participant-item');
        const order = Array.from(items).map(item => item.dataset.id);
        orderInput.value = JSON.stringify(order);
    }

    list.addEventListener('dragstart', function(e) {
        const item = e.target.closest('.participant-item');
        if (!item) return;
        draggedItem = item;
        setTimeout(() => item.classList.add('dragging'), 0);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.dataset.id);
    });

    list.addEventListener('dragend', function(e) {
        const item = e.target.closest('.participant-item');
        if (!item) return;
        item.classList.remove('dragging');
        draggedItem = null;
        updateOrderInput();
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const afterElement = getDragAfterElement(list, e.clientY);
        if (draggedItem) {
            if (afterElement == null) {
                list.appendChild(draggedItem);
            } else {
                list.insertBefore(draggedItem, afterElement);
            }
        }
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.participant-item:not(.dragging)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    form.addEventListener('submit', function() {
        updateOrderInput();
    });

    // Инициализация порядка
    updateOrderInput();

    // Обработчик кнопки копирования таблицы
    const copyButton = document.getElementById('copyTableButton');
    if (copyButton) {
        copyButton.addEventListener('click', async function() {
            const table = document.querySelector('.export-table');
            if (!table) return;

            // Клонируем таблицу для модификации перед копированием
            const clonedTable = table.cloneNode(true);
            
            // Добавляем инлайн-стили для самой таблицы
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.style.width = '100%';
            clonedTable.style.fontFamily = "'Times New Roman', Times, serif";
            clonedTable.style.fontSize = '9pt';
            clonedTable.style.backgroundColor = 'white';
            clonedTable.style.color = 'black';
            
            // Находим все ячейки th и td и добавляем им инлайн-стили
            const allCells = clonedTable.querySelectorAll('th, td');
            allCells.forEach(cell => {
                cell.style.border = '1px solid black';
                cell.style.padding = '10px';
                cell.style.textAlign = 'center';
                cell.style.color = 'black';
                cell.style.fontWeight = 'normal';
                cell.style.fontSize = '9pt';
                cell.style.fontFamily = "'Times New Roman', Times, serif";
            });
            
            // Находим все ячейки с вертикальным текстом
            const verticalCells = clonedTable.querySelectorAll('.vertical-text');
            verticalCells.forEach(cell => {
                const text = cell.textContent || cell.innerText;
                // Заменяем содержимое на span с inline-стилями для Word
                cell.innerHTML = '<span style="writing-mode: vertical-lr; transform: rotate(180deg); display: inline-block; text-orientation: mixed;">' + text + '</span>';
                cell.style.writingMode = 'vertical-lr';
                cell.style.transform = 'rotate(180deg)';
                cell.style.textOrientation = 'mixed';
                cell.style.msWritingMode = 'tb-rl';
                cell.style.msoWritingMode = 'tb-rl';
                cell.style.WebkitWritingMode = 'vertical-lr';
                cell.style.MozWritingMode = 'vertical-lr';
                cell.style.height = 'auto';
                cell.style.minWidth = '20px';
                cell.setAttribute('valign', 'middle');
            });
            
            // Также обрабатываем заголовки категорий
            const categoryHeaders = clonedTable.querySelectorAll('th.vertical-text');
            categoryHeaders.forEach(header => {
                const text = header.textContent || header.innerText;
                header.innerHTML = '<span style="writing-mode: vertical-lr; transform: rotate(180deg); display: inline-block; text-orientation: mixed;">' + text + '</span>';
                header.style.writingMode = 'vertical-lr';
                header.style.transform = 'rotate(180deg)';
                header.style.textOrientation = 'mixed';
                header.style.msWritingMode = 'tb-rl';
                header.style.msoWritingMode = 'tb-rl';
                header.style.WebkitWritingMode = 'vertical-lr';
                header.style.MozWritingMode = 'vertical-lr';
                header.style.height = 'auto';
                header.style.minWidth = '20px';
                header.setAttribute('valign', 'middle');
            });

            // Создаем временный контейнер для клонированной таблицы
            const tempContainer = document.createElement('div');
            tempContainer.style.position = 'absolute';
            tempContainer.style.left = '-9999px';
            tempContainer.style.top = '-9999px';
            tempContainer.appendChild(clonedTable);
            document.body.appendChild(tempContainer);

            // Пытаемся использовать современный Clipboard API с HTML форматом
            const htmlContent = clonedTable.outerHTML;
            
            try {
                // Пробуем использовать Clipboard API
                const blobHtml = new Blob([htmlContent], { type: 'text/html' });
                const blobText = new Blob([clonedTable.innerText], { type: 'text/plain' });
                
                const data = [new ClipboardItem({
                    'text/html': blobHtml,
                    'text/plain': blobText
                })];
                
                await navigator.clipboard.write(data);
                alert('Таблица скопирована в буфер обмена!');
            } catch (err) {
                // Fallback для старых браузеров
                const range = document.createRange();
                range.selectNode(clonedTable);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);

                try {
                    document.execCommand('copy');
                    alert('Таблица скопирована в буфер обмена!');
                } catch (execErr) {
                    alert('Не удалось скопировать таблицу. Попробуйте вручную.');
                }

                window.getSelection().removeAllRanges();
            }

            document.body.removeChild(tempContainer);
        });
    }
})();
</script>
</body>
</html>
