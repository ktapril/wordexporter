<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\User;

// Требуем авторизацию и права на создание участников (админ или секретарь)
requireAuth();

if (!canManageParticipants()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для управления участниками.</p><a href="index.php">На главную</a>');
}

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

// Получаем текущего пользователя
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? User::ROLE_ADMIN;
$userId = $currentUser['id'] ?? null;

// Секретарь видит только своих участников, администратор - всех
$participants = $service->getParticipants($userId, $userRole);

// Обработка создания участника
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_participant') {
        $name = trim((string)($_POST['participant_name'] ?? ''));
        $breed = trim((string)($_POST['participant_breed'] ?? '')) ?: null;
        $nickname = trim((string)($_POST['participant_nickname'] ?? '')) ?: null;
        $gender = trim((string)($_POST['participant_gender'] ?? '')) ?: null;
        $birthDate = trim((string)($_POST['participant_birth_date'] ?? '')) ?: null;
        $microchipNumber = trim((string)($_POST['participant_microchip'] ?? '')) ?: null;
        $pedigreeNumber = trim((string)($_POST['participant_pedigree'] ?? '')) ?: null;
        $qualificationBookNumber = trim((string)($_POST['participant_qualification'] ?? '')) ?: null;
        $instructorName = trim((string)($_POST['participant_instructor'] ?? '')) ?: null;

        if ($name === '') {
            $message = 'Имя участника обязательно.';
            $messageType = 'error';
        } else {
            $service->createParticipant(
                $name,
                $breed,
                $nickname,
                $gender,
                $birthDate,
                $microchipNumber,
                $pedigreeNumber,
                $qualificationBookNumber,
                $instructorName,
                $userId
            );
            header('Location: participants.php');
            exit;
        }

        $participants = $service->getParticipants($userId, $userRole);
    }

    // Обработка редактирования участника
    if ($action === 'update_participant') {
        $participantId = (int)($_POST['participant_id'] ?? 0);
        $name = trim((string)($_POST['edit_participant_name'] ?? ''));
        $breed = trim((string)($_POST['edit_participant_breed'] ?? '')) ?: null;
        $nickname = trim((string)($_POST['edit_participant_nickname'] ?? '')) ?: null;
        $gender = trim((string)($_POST['edit_participant_gender'] ?? '')) ?: null;
        $birthDate = trim((string)($_POST['edit_participant_birth_date'] ?? '')) ?: null;
        $microchipNumber = trim((string)($_POST['edit_participant_microchip'] ?? '')) ?: null;
        $pedigreeNumber = trim((string)($_POST['edit_participant_pedigree'] ?? '')) ?: null;
        $qualificationBookNumber = trim((string)($_POST['edit_participant_qualification'] ?? '')) ?: null;
        $instructorName = trim((string)($_POST['edit_participant_instructor'] ?? '')) ?: null;

        if ($participantId <= 0 || $name === '') {
            $message = 'Некорректные данные участника.';
            $messageType = 'error';
        } else {
            try {
                $service->updateParticipant(
                    $participantId,
                    $name,
                    $breed,
                    $nickname,
                    $gender,
                    $birthDate,
                    $microchipNumber,
                    $pedigreeNumber,
                    $qualificationBookNumber,
                    $instructorName
                );
                header('Location: participants.php');
                exit;
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }

        $participants = $service->getParticipants($userId, $userRole);
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Участники — Nosework</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$currentPage = 'create_participant';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell page-shell--single">
    <section class="hero hero--compact">
        <div>
            <div class="hero__eyebrow">Участники</div>
            <h1 class="hero__title">Создание и управление участниками</h1>
            <p class="hero__subtitle">Добавляйте новых участников и просматривайте существующих собак. После создания участника вы можете назначить его на соревнование на странице управления.</p>
        </div>
    </section>

    <section class="content-grid content-grid--single">

    <?php if ($message !== ''): ?>
        <div class="alert alert--<?= escape($messageType) ?>" style="margin-bottom: 1.5rem;"><?= escape($message) ?></div>
    <?php endif; ?>

    <article class="panel">
        <a href="index.php" class="back-link">← Вернуться на главную</a>
        <div class="action-bar" style="margin-bottom: 0;">
            <h2 style="margin: 0; font-size: 1.25rem;">Список участников</h2>
            <button type="button" class="button btn-icon" onclick="openModal('createModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Создать участника
            </button>
        </div>

        <div class="participants-list" style="margin-top: 16px;">
        <?php if (count($participants) === 0): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <p>Пока нет участников. Создайте первого участника.</p>
            </div>
        <?php else: ?>
            <?php foreach ($participants as $participant): ?>
                <?php
                    $nickname = $participant->getNickname();
                    $breed = $participant->getBreed();
                    $displayName = escape($participant->getName());
                    if ($nickname !== null && $nickname !== '') {
                        $displayName .= ' с собакой ' . escape($nickname);
                        if ($breed !== null && $breed !== '') {
                            $displayName .= ' (' . escape($breed) . ')';
                        }
                    } elseif ($breed !== null && $breed !== '') {
                        $displayName .= ' с собакой (' . escape($breed) . ')';
                    }
                ?>
                <div class="participant-card">
                    <div class="participant-info">
                        <span class="participant-name"><?= $displayName ?></span>
                        <div class="participant-details">
                            <?php if ($participant->getGender()): ?>
                                <span class="participant-detail"><strong>Пол:</strong> <?= escape($participant->getGender()) ?></span>
                            <?php endif; ?>
                            <?php if ($participant->getInstructorName()): ?>
                                <span class="participant-detail"><strong>Инструктор:</strong> <?= escape($participant->getInstructorName()) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="button button--secondary btn-small" onclick="openEditModal(<?= $participant->getId() ?>, '<?= escape(addslashes($participant->getName())) ?>', '<?= escape(addslashes($participant->getNickname() ?? '')) ?>', '<?= escape(addslashes($participant->getBreed() ?? '')) ?>', '<?= escape(addslashes($participant->getGender() ?? '')) ?>', '<?= escape(addslashes($participant->getBirthDate() ?? '')) ?>', '<?= escape(addslashes($participant->getMicrochipNumber() ?? '')) ?>', '<?= escape(addslashes($participant->getPedigreeNumber() ?? '')) ?>', '<?= escape(addslashes($participant->getQualificationBookNumber() ?? '')) ?>', '<?= escape(addslashes($participant->getInstructorName() ?? '')) ?>')">
                        Редактировать
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </article>
    </section>
</main>

<!-- Modal: Create Participant -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Создать участника</h2>
            <button type="button" class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create_participant">
            <div class="modal-body">
                <div class="penalty-grid">
                    <div class="form-control">
                        <label for="participant_name">Имя участника *</label>
                        <input id="participant_name" name="participant_name" type="text" placeholder="Имя участника" required>
                    </div>
                    <div class="form-control">
                        <label for="participant_nickname">Кличка</label>
                        <input id="participant_nickname" name="participant_nickname" type="text" placeholder="Кличка">
                    </div>
                    <div class="form-control">
                        <label for="participant_breed">Порода</label>
                        <input id="participant_breed" name="participant_breed" type="text" placeholder="Порода">
                    </div>
                    <div class="form-control">
                        <label for="participant_gender">Пол</label>
                        <input id="participant_gender" name="participant_gender" type="text" placeholder="Пол">
                    </div>
                    <div class="form-control">
                        <label for="participant_birth_date">Дата рождения собаки</label>
                        <input id="participant_birth_date" name="participant_birth_date" type="date" placeholder="Дата рождения собаки">
                    </div>
                    <div class="form-control">
                        <label for="participant_microchip">Номер клейма</label>
                        <input id="participant_microchip" name="participant_microchip" type="text" placeholder="Номер клейма">
                    </div>
                    <div class="form-control">
                        <label for="participant_pedigree">Номер родословной</label>
                        <input id="participant_pedigree" name="participant_pedigree" type="text" placeholder="Номер родословной">
                    </div>
                    <div class="form-control">
                        <label for="participant_qualification">Номер квалификационной книжки</label>
                        <input id="participant_qualification" name="participant_qualification" type="text" placeholder="№ квалификационной книжки">
                    </div>
                    <div class="form-control">
                        <label for="participant_instructor">Имя инструктора</label>
                        <input id="participant_instructor" name="participant_instructor" type="text" placeholder="Имя инструктора">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button--secondary" onclick="closeModal('createModal')">Отмена</button>
                <button type="submit" class="button">Создать участника</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Participant -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Редактировать участника</h2>
            <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="update_participant">
            <input type="hidden" name="participant_id" id="edit_participant_id">
            <div class="modal-body">
                <div class="penalty-grid">
                    <div class="form-control">
                        <label for="edit_participant_name">Имя участника *</label>
                        <input id="edit_participant_name" name="edit_participant_name" type="text" required>
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_nickname">Кличка</label>
                        <input id="edit_participant_nickname" name="edit_participant_nickname" type="text">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_breed">Порода</label>
                        <input id="edit_participant_breed" name="edit_participant_breed" type="text">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_gender">Пол</label>
                        <input id="edit_participant_gender" name="edit_participant_gender" type="text">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_birth_date">Дата рождения собаки</label>
                        <input id="edit_participant_birth_date" name="edit_participant_birth_date" type="date">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_microchip">Номер клейма</label>
                        <input id="edit_participant_microchip" name="edit_participant_microchip" type="text">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_pedigree">Номер родословной</label>
                        <input id="edit_participant_pedigree" name="edit_participant_pedigree" type="text">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_qualification">Номер квалификационной книжки</label>
                        <input id="edit_participant_qualification" name="edit_participant_qualification" type="text">
                    </div>
                    <div class="form-control">
                        <label for="edit_participant_instructor">Имя инструктора</label>
                        <input id="edit_participant_instructor" name="edit_participant_instructor" type="text">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button--secondary" onclick="closeModal('editModal')">Отмена</button>
                <button type="submit" class="button">Сохранить изменения</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.style.overflow = '';
    }

    function openEditModal(id, name, nickname, breed, gender, birthDate, microchip, pedigree, qualification, instructor) {
        document.getElementById('edit_participant_id').value = id;
        document.getElementById('edit_participant_name').value = name;
        document.getElementById('edit_participant_nickname').value = nickname;
        document.getElementById('edit_participant_breed').value = breed;
        document.getElementById('edit_participant_gender').value = gender;
        document.getElementById('edit_participant_birth_date').value = birthDate;
        document.getElementById('edit_participant_microchip').value = microchip;
        document.getElementById('edit_participant_pedigree').value = pedigree;
        document.getElementById('edit_participant_qualification').value = qualification;
        document.getElementById('edit_participant_instructor').value = instructor;
        openModal('editModal');
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
                closeModal(modal.id);
            });
        }
    });
</script>
</body>
</html>
