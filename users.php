<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/src/functions.php';

use NoseworkV2\CompetitionService;
use NoseworkV2\DatabaseManager;
use NoseworkV2\User;

// Требуем авторизацию и права администратора
requireAuth();

$dbManager = new DatabaseManager();
$service = new CompetitionService($dbManager);

$message = '';
$messageType = 'info';

// Проверяем права администратора
if (!canManageUsers()) {
    http_response_code(403);
    die('<h1>Доступ запрещён</h1><p>У вас нет прав для управления пользователями.</p><a href="index.php">На главную</a>');
}

$competitions = $service->getCompetitions();
$users = $service->getAllUsers();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        
        // Получаем массив ID соревнований (поддержка множественного выбора)
        $competitionIds = [];
        if (isset($_POST['competition_ids']) && is_array($_POST['competition_ids'])) {
            $competitionIds = array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $_POST['competition_ids']);
            $competitionIds = array_filter($competitionIds);
        }

        if ($username === '' || $password === '') {
            $message = 'Логин и пароль обязательны.';
            $messageType = 'error';
        } elseif (!in_array($role, [User::ROLE_ADMIN, User::ROLE_JUDGE, User::ROLE_SECRETARY], true)) {
            $message = 'Недопустимая роль.';
            $messageType = 'error';
        } elseif ($role !== User::ROLE_ADMIN && empty($competitionIds)) {
            $message = 'Для судьи и секретаря необходимо выбрать хотя бы одно соревнование.';
            $messageType = 'error';
        } else {
            try {
                // Создаём пользователя с первым соревнованием (для обратной совместимости)
                $firstCompetitionId = !empty($competitionIds) ? reset($competitionIds) : null;
                $userId = $service->createUser($username, $password, $role, $firstCompetitionId, $displayName !== '' ? $displayName : null);
                
                // Сохраняем связи с соревнованиями через таблицу user_competitions
                if (!empty($competitionIds)) {
                    $dbManager->saveUserCompetitions($userId, $competitionIds);
                }
                
                $message = 'Пользователь успешно создан.';
                $messageType = 'success';
                $users = $service->getAllUsers();
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    if ($action === 'update_user') {
        $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
        $displayName = trim((string)($_POST['edit_display_name'] ?? ''));
        $password = $_POST['edit_password'] ?? '';
        $role = $_POST['edit_role'] ?? '';
        
        // Получаем массив ID соревнований (поддержка множественного выбора)
        $competitionIds = [];
        if (isset($_POST['edit_competition_ids']) && is_array($_POST['edit_competition_ids'])) {
            $competitionIds = array_map(fn($id) => filter_var($id, FILTER_VALIDATE_INT), $_POST['edit_competition_ids']);
            $competitionIds = array_filter($competitionIds);
        }

        if ($userId === false || $userId === null) {
            $message = 'Неверный ID пользователя.';
            $messageType = 'error';
        } elseif (!in_array($role, [User::ROLE_ADMIN, User::ROLE_JUDGE, User::ROLE_SECRETARY], true)) {
            $message = 'Недопустимая роль.';
            $messageType = 'error';
        } elseif ($role !== User::ROLE_ADMIN && empty($competitionIds)) {
            $message = 'Для судьи и секретаря необходимо выбрать хотя бы одно соревнование.';
            $messageType = 'error';
        } else {
            try {
                $userToEdit = $service->getUserById($userId);
                if ($userToEdit === null) {
                    throw new \RuntimeException('Пользователь не найден.');
                }
                
                // Пароль обновляем только если он указан
                $newPassword = $password !== '' ? $password : null;
                
                $service->updateUser(
                    $userId,
                    $userToEdit->getUsername(),
                    $newPassword,
                    $role,
                    !empty($competitionIds) ? reset($competitionIds) : null, // Для обратной совместимости сохраняем первый ID в competition_id
                    $displayName !== '' ? $displayName : null
                );
                
                // Сохраняем связи с соревнованиями через таблицу user_competitions
                $dbManager->saveUserCompetitions($userId, $competitionIds);
                
                $message = 'Данные пользователя успешно обновлены.';
                $messageType = 'success';
                $users = $service->getAllUsers();
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    if ($action === 'delete_user') {
        $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);

        if ($userId === false || $userId === null) {
            $message = 'Неверный ID пользователя.';
            $messageType = 'error';
        } elseif ($userId === $currentUser['id']) {
            $message = 'Нельзя удалить самого себя.';
            $messageType = 'error';
        } else {
            try {
                $service->deleteUser($userId);
                $message = 'Пользователь удалён.';
                $messageType = 'success';
                $users = $service->getAllUsers();
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями — Nosework</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .manage-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .section-panel {
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 28px;
            margin-bottom: 24px;
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            max-width: 500px;
            width: min(90%, 500px);
            box-shadow: var(--shadow);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
        }
        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.5rem;
            padding: 4px;
            line-height: 1;
        }
        .modal-close:hover {
            color: var(--text);
        }
    </style>
</head>
<body>
<?php
$currentPage = 'manage_users';
require_once __DIR__ . '/header.php';
renderHeader($currentPage);
?>
<main class="page-shell">
    <div class="manage-container">
        <a href="index.php" class="back-link">← Вернуться на главную</a>
        <?php if ($message !== ''): ?>
            <div class="alert alert--<?= escape($messageType) ?>"><?= escape($message) ?></div>
        <?php endif; ?>

        <div class="section-panel">
            <h2 style="display: flex; justify-content: space-between; align-items: center;">
                Пользователи
                <button type="button" class="button" onclick="openModal('add-user-modal')">Добавить пользователя</button>
            </h2>
            
            <?php if (count($users) === 0): ?>
                <div class="empty-state">Пользователи не созданы.</div>
            <?php else: ?>
                <div class="table-wrapper" style="margin-top: 16px;">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Имя</th>
                            <th>Роль</th>
                            <th>Соревнование</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user->getId() ?></td>
                                <td><?= escape($user->getUsername()) ?></td>
                                <td><?= escape($user->getDisplayName() ?? '—') ?></td>
                                <td><?= escape(User::getRoleLabel($user->getRole())) ?></td>
                                <td>
                                    <?php
                                    // Получаем список соревнований пользователя из таблицы user_competitions
                                    $competitionIds = $user->getCompetitionIds();
                                    if (!empty($competitionIds)) {
                                        $compNames = [];
                                        foreach ($competitionIds as $compId) {
                                            $comp = $service->getCompetitionById($compId);
                                            if ($comp) {
                                                $compNames[] = escape($comp->getName());
                                            }
                                        }
                                        echo implode(', ', $compNames);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    // Кнопка "Редактировать" доступна для судей и секретарей (но не для администраторов, кроме случая редактирования самого себя)
                                    $canEdit = in_array($user->getRole(), [User::ROLE_JUDGE, User::ROLE_SECRETARY], true);
                                    if ($canEdit && $user->getId() !== $currentUser['id']): 
                                    ?>
                                        <button type="button" class="button button--secondary" style="padding: 6px 14px; font-size: 0.85rem;" onclick="openEditModal(<?= (int)$user->getId() ?>, <?= htmlspecialchars(json_encode([
                                            'id' => $user->getId(),
                                            'username' => $user->getUsername(),
                                            'displayName' => $user->getDisplayName(),
                                            'role' => $user->getRole(),
                                            'competitionIds' => $user->getCompetitionIds()
                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">Редактировать</button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Вы уверены, что хотите удалить пользователя?')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user->getId() ?>">
                                            <button type="submit" class="button button--secondary" style="padding: 6px 14px; font-size: 0.85rem;">Удалить</button>
                                        </form>
                                    <?php elseif ($canEdit && $user->getId() === $currentUser['id']): ?>
                                        <button type="button" class="button button--secondary" style="padding: 6px 14px; font-size: 0.85rem;" onclick="openEditModal(<?= (int)$user->getId() ?>, <?= htmlspecialchars(json_encode([
                                            'id' => $user->getId(),
                                            'username' => $user->getUsername(),
                                            'displayName' => $user->getDisplayName(),
                                            'role' => $user->getRole(),
                                            'competitionIds' => $user->getCompetitionIds()
                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">Редактировать</button>
                                        <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 8px;">Текущий пользователь</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Модальное окно: Добавление пользователя -->
<div id="add-user-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Добавить пользователя</h3>
            <button type="button" class="modal-close" onclick="closeModal('add-user-modal')">&times;</button>
        </div>
        <form method="post" novalidate>
            <input type="hidden" name="action" value="create_user">
            
            <div class="form-control">
                <label for="username">Логин</label>
                <input id="username" name="username" type="text" placeholder="Введите логин" required>
            </div>
            
            <div class="form-control">
                <label for="display_name">Имя (необязательно)</label>
                <input id="display_name" name="display_name" type="text" placeholder="Введите имя пользователя">
            </div>
            
            <div class="form-control">
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" placeholder="Введите пароль" required>
            </div>
            
            <div class="form-control">
                <label for="role">Роль</label>
                <select id="role" name="role" required onchange="toggleCompetitionSelect()">
                    <?php foreach (User::getAvailableRoles() as $roleValue => $roleLabel): ?>
                        <option value="<?= escape($roleValue) ?>"><?= escape($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control" id="competition-select-container" style="display: none;">
                <label for="competition_id">Соревнования</label>
                <select id="competition_id" name="competition_ids[]" multiple style="min-height: 120px;">
                    <?php foreach ($competitions as $competition): ?>
                        <option value="<?= $competition->getId() ?>"><?= escape($competition->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); font-size: 0.8rem;">Удерживайте Ctrl/Cmd для выбора нескольких соревнований</small>
            </div>
            
            <div class="button-group">
                <button type="submit" class="button">Создать</button>
                <button type="button" class="button button--secondary" onclick="closeModal('add-user-modal')">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: Редактирование пользователя -->
<div id="edit-user-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать пользователя</h3>
            <button type="button" class="modal-close" onclick="closeModal('edit-user-modal')">&times;</button>
        </div>
        <form method="post" novalidate>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-control">
                <label for="edit_username">Логин</label>
                <input id="edit_username" type="text" disabled style="background: rgba(255,255,255,0.05); cursor: not-allowed;">
                <small style="color: var(--text-muted); font-size: 0.8rem;">Логин нельзя изменить</small>
            </div>
            
            <div class="form-control">
                <label for="edit_display_name">Имя</label>
                <input id="edit_display_name" name="edit_display_name" type="text" placeholder="Введите имя пользователя">
            </div>
            
            <div class="form-control">
                <label for="edit_password">Новый пароль (оставьте пустым, чтобы не менять)</label>
                <input id="edit_password" name="edit_password" type="password" placeholder="Введите новый пароль">
            </div>
            
            <div class="form-control">
                <label for="edit_role">Роль</label>
                <select id="edit_role" name="edit_role" required onchange="toggleEditCompetitionSelect()">
                    <?php foreach (User::getAvailableRoles() as $roleValue => $roleLabel): ?>
                        <option value="<?= escape($roleValue) ?>"><?= escape($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control" id="edit-competition-select-container" style="display: none;">
                <label for="edit_competition_id">Соревнования</label>
                <select id="edit_competition_id" name="edit_competition_ids[]" multiple style="min-height: 120px;">
                    <?php foreach ($competitions as $competition): ?>
                        <option value="<?= $competition->getId() ?>"><?= escape($competition->getName()) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); font-size: 0.8rem;">Удерживайте Ctrl/Cmd для выбора нескольких соревнований</small>
            </div>
            
            <div class="button-group">
                <button type="submit" class="button">Сохранить</button>
                <button type="button" class="button button--secondary" onclick="closeModal('edit-user-modal')">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(event) {
        if (event.target === overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

function toggleCompetitionSelect() {
    const roleSelect = document.getElementById('role');
    const competitionContainer = document.getElementById('competition-select-container');
    const competitionSelect = document.getElementById('competition_id');
    
    if (roleSelect.value === 'judge' || roleSelect.value === 'secretary') {
        competitionContainer.style.display = 'block';
    } else {
        competitionContainer.style.display = 'none';
        // Сбрасываем выбор
        for (let i = 0; i < competitionSelect.options.length; i++) {
            competitionSelect.options[i].selected = false;
        }
    }
}

function toggleEditCompetitionSelect() {
    const roleSelect = document.getElementById('edit_role');
    const competitionContainer = document.getElementById('edit-competition-select-container');
    const competitionSelect = document.getElementById('edit_competition_id');
    
    if (roleSelect.value === 'judge' || roleSelect.value === 'secretary') {
        competitionContainer.style.display = 'block';
        // Для множественного выбора required не применяется, но мы можем проверить на стороне сервера
    } else {
        competitionContainer.style.display = 'none';
        // Сбрасываем выбор
        for (let i = 0; i < competitionSelect.options.length; i++) {
            competitionSelect.options[i].selected = false;
        }
    }
}

function openEditModal(userId, userData) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = userData.username;
    document.getElementById('edit_display_name').value = userData.displayName || '';
    document.getElementById('edit_role').value = userData.role;
    
    // Устанавливаем соревнования (поддержка множественного выбора)
    const competitionSelect = document.getElementById('edit_competition_id');
    const competitionIds = userData.competitionIds || [];
    
    // Сбрасываем все выбранные значения
    for (let i = 0; i < competitionSelect.options.length; i++) {
        competitionSelect.options[i].selected = false;
    }
    
    // Выбираем нужные соревнования
    if (competitionIds.length > 0) {
        for (let i = 0; i < competitionSelect.options.length; i++) {
            if (competitionIds.includes(parseInt(competitionSelect.options[i].value))) {
                competitionSelect.options[i].selected = true;
            }
        }
    }
    
    toggleEditCompetitionSelect();
    openModal('edit-user-modal');
}

// Инициализация при загрузке
toggleCompetitionSelect();
</script>
</body>
</html>
