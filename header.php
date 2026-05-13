<?php
function renderHeader(string $currentPage): void
{
    require_once __DIR__ . '/auth.php';
    
    $links = [
        ['key' => 'home', 'href' => 'index.php', 'label' => 'Главная'],
    ];

    // Добавляем ссылки в зависимости от роли пользователя
    if (canCreateCompetition()) {
        $links[] = ['key' => 'create_competition', 'href' => 'add_competition.php', 'label' => 'Создать соревнование'];
    }
    
    if (canManageUsers()) {
        $links[] = ['key' => 'manage_users', 'href' => 'users.php', 'label' => 'Пользователи'];
    }
    
    // Для администраторов и секретарей добавляем ссылку на создание участника
    if (canManageParticipants()) {
        $links[] = ['key' => 'create_participant', 'href' => 'participants.php', 'label' => 'Участники'];
    }
    
    // Для администраторов добавляем ссылку на управление стандартными правилами
    if (isAdmin()) {
        $links[] = ['key' => 'standard_rules', 'href' => 'standard_rules.php', 'label' => 'Стандартные правила'];
    }
    ?>
    <header class="page-header">
        <div class="page-header__brand">
            <a href="index.php">
                <img src="nosebrain_logo.png" alt="Nosebrain Logo" class="page-header__logo">
            </a>
        </div>
        <nav class="page-header__nav">
            <?php foreach ($links as $link): ?>
                <a class="button button--secondary<?= $currentPage === $link['key'] ? ' button--active' : '' ?>" href="<?= escape($link['href']) ?>"><?= escape($link['label']) ?></a>
            <?php endforeach; ?>
            <?php if (isLoggedIn()): ?>
                <?php renderUserInfo(); ?>
            <?php else: ?>
                <a class="button button--primary" href="login.php">Войти</a>
            <?php endif; ?>
        </nav>
    </header>
    <?php
}
