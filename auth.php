<?php

require_once 'vendor/autoload.php';

// Запускаем сессию только если она ещё не активна
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверяет, авторизован ли пользователь
 */
function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Проверяет, авторизован ли пользователь
 * Возвращает true если пользователь авторизован, false иначе
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Возвращает текущего авторизованного пользователя или null
 */
function getCurrentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'competition_id' => $_SESSION['competition_id'] ?? null,
        'competition_ids' => $_SESSION['competition_ids'] ?? [],
    ];
}

/**
 * Проверяет, является ли текущий пользователь администратором
 */
function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === \NoseworkV2\User::ROLE_ADMIN;
}

/**
 * Проверяет, является ли текущий пользователь судьёй
 */
function isJudge(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === \NoseworkV2\User::ROLE_JUDGE;
}

/**
 * Проверяет, является ли текущий пользователь секретарём
 */
function isSecretary(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === \NoseworkV2\User::ROLE_SECRETARY;
}

/**
 * Проверяет, имеет ли пользователь доступ к соревнованию
 * Администратор имеет доступ ко всем соревнованиям
 * Судья и секретарь имеют доступ только к своим соревнованиям (поддержка множественных назначений)
 */
function hasCompetitionAccess(?int $competitionId): bool
{
    if (isAdmin()) {
        return true;
    }

    if ($competitionId === null) {
        return false;
    }

    // Проверяем доступ по массиву competition_ids (для поддержки множественных назначений)
    $userCompetitionIds = $_SESSION['competition_ids'] ?? [];
    if (!empty($userCompetitionIds)) {
        return in_array($competitionId, $userCompetitionIds, true);
    }
    
    // Для обратной совместимости проверяем одиночное competition_id
    $userCompetitionId = $_SESSION['competition_id'] ?? null;
    return $userCompetitionId === $competitionId;
}

/**
 * Проверяет, имеет ли пользователь право доступа к функционалу судейства
 * Доступно: администраторам и судьям
 * Также проверяет, что текущая дата находится в диапазоне дат соревнования
 */
function canAccessJudging(?int $competitionId = null): bool
{
    if (!(isAdmin() || isJudge())) {
        return false;
    }

    // Если передан ID соревнования, проверяем его активность
    if ($competitionId !== null) {
        require_once __DIR__ . '/vendor/autoload.php';
        $dbManager = new \NoseworkV2\DatabaseManager();
        $service = new \NoseworkV2\CompetitionService($dbManager);
        $competition = $service->getCompetitionById($competitionId);
        
        if ($competition !== null && !$competition->isActive()) {
            return false;
        }
    }

    return true;
}

/**
 * Проверяет, имеет ли пользователь право доступа к функционалу ручного внесения результатов
 * Доступно: администраторам, судьям и секретарям
 * Также проверяет, что текущая дата находится в диапазоне дат соревнования
 */
function canAccessManualResult(?int $competitionId = null): bool
{
    if (!(isAdmin() || isJudge() || isSecretary())) {
        return false;
    }

    // Если передан ID соревнования, проверяем его активность
    if ($competitionId !== null) {
        require_once __DIR__ . '/vendor/autoload.php';
        $dbManager = new \NoseworkV2\DatabaseManager();
        $service = new \NoseworkV2\CompetitionService($dbManager);
        $competition = $service->getCompetitionById($competitionId);
        
        if ($competition !== null && !$competition->isActive()) {
            return false;
        }
    }

    return true;
}

/**
 * Проверяет, имеет ли пользователь право доступа к функционалу видеосудейства
 * Доступно: администраторам и судьям
 * Также проверяет, что текущая дата находится в диапазоне дат соревнования
 */
function canAccessVideoJudging(?int $competitionId = null): bool
{
    if (!(isAdmin() || isJudge())) {
        return false;
    }

    // Если передан ID соревнования, проверяем его активность
    if ($competitionId !== null) {
        require_once __DIR__ . '/vendor/autoload.php';
        $dbManager = new \NoseworkV2\DatabaseManager();
        $service = new \NoseworkV2\CompetitionService($dbManager);
        $competition = $service->getCompetitionById($competitionId);
        
        if ($competition !== null && !$competition->isActive()) {
            return false;
        }
    }

    return true;
}

/**
 * Проверяет, имеет ли пользователь право управления соревнованием
 * Доступно: администраторам и секретарям
 */
function canManageCompetition(): bool
{
    return isAdmin() || isSecretary();
}

/**
 * Проверяет, имеет ли пользователь право создавать новые соревнования
 * Доступно только администраторам
 */
function canCreateCompetition(): bool
{
    return isAdmin();
}

/**
 * Проверяет, имеет ли пользователь право управлять участниками (добавлять/редактировать)
 * Доступно: администраторам и секретарям
 * Судьи не могут управлять участниками
 */
function canManageParticipants(): bool
{
    return isAdmin() || isSecretary();
}

/**
 * Проверяет, имеет ли пользователь право просмотра результатов
 * Доступно: всем авторизованным пользователям
 */
function canViewResults(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Проверяет, имеет ли пользователь право удалять результаты
 * Доступно: администраторам и судьям
 */
function canDeleteResults(): bool
{
    return isAdmin() || isJudge();
}

/**
 * Проверяет, имеет ли пользователь право публиковать/скрывать результаты соревнований
 * Доступно: администраторам и секретарям
 */
function canPublishResults(): bool
{
    return isAdmin() || isSecretary();
}

/**
 * Проверяет, имеет ли пользователь право экспорта результатов
 * Доступно: администраторам и секретарям
 */
function canExportResults(): bool
{
    return isAdmin() || isSecretary();
}

/**
 * Проверяет, имеет ли пользователь право создавать других пользователей
 * Доступно только администраторам
 */
function canManageUsers(): bool
{
    return isAdmin();
}

/**
 * Выполняет выход из системы
 */
function logout(): void
{
    session_unset();
    session_destroy();
    
    // Удаляем cookie сессии
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    header('Location: login.php');
    exit;
}

/**
 * Рендерит информацию о пользователе в хедере
 */
function renderUserInfo(): void
{
    $user = getCurrentUser();
    if ($user === null) {
        return;
    }

    $roles = \NoseworkV2\User::getAvailableRoles();
    $roleLabel = $roles[$user['role']] ?? $user['role'];
    
    echo '<div style="display: flex; align-items: center; gap: 12px;">';
    echo '<a href="profile.php" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none; transition: color 0.2s ease;" onmouseover="this.style.color=\'var(--text)\'" onmouseout="this.style.color=\'var(--text-muted)\'">';
    echo htmlspecialchars($user['username'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    echo ' <span style="opacity: 0.7;">(' . htmlspecialchars($roleLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ')</span>';
    echo '</a>';
    echo '<a href="logout.php" class="button button--secondary" style="padding: 6px 14px; font-size: 0.85rem;">Выход</a>';
    echo '</div>';
}

// Функция для проверки доступа на странице
function checkCompetitionAccess(?int $competitionId): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    if (!hasCompetitionAccess($competitionId)) {
        http_response_code(403);
        die('<h1>Доступ запрещён</h1><p>У вас нет доступа к этому соревнованию.</p><a href="index.php">На главную</a>');
    }
}
