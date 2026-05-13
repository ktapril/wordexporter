<?php

namespace NoseworkV2;

class CompetitionService
{
    private DatabaseManager $dbManager;

    public function __construct(DatabaseManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    public function createCompetition(string $name, string $description = '', ?string $startDate = null, ?string $endDate = null): int
    {
        $competition = new Competition($name, $description, null, false, $startDate, $endDate);
        return $this->dbManager->insertCompetition($competition);
    }

    public function getCompetitionById(int $competitionId): ?Competition
    {
        return $this->dbManager->getCompetitionById($competitionId);
    }

    public function updateCompetition(int $competitionId, string $name, string $description, ?string $startDate = null, ?string $endDate = null): void
    {
        $competition = new Competition($name, $description, $competitionId, false, $startDate, $endDate);
        $this->dbManager->updateCompetition($competition);
    }

    public function createUser(string $username, string $password, string $role, ?int $competitionId = null, ?string $displayName = null): int
    {
        if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_JUDGE, User::ROLE_SECRETARY], true)) {
            throw new \InvalidArgumentException('Недопустимая роль пользователя.');
        }

        if ($role !== User::ROLE_ADMIN && $competitionId === null) {
            throw new \InvalidArgumentException('Для судьи и секретаря необходимо указать соревнование.');
        }

        $existingUser = $this->dbManager->getUserByUsername($username);
        if ($existingUser !== null) {
            throw new \RuntimeException('Пользователь с таким именем уже существует.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = new User($username, $passwordHash, $role, $competitionId, null, $displayName);

        return $this->dbManager->insertUser($user);
    }

    public function updateUser(int $userId, string $username, ?string $password, string $role, ?int $competitionId = null, ?string $displayName = null): void
    {
        if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_JUDGE, User::ROLE_SECRETARY], true)) {
            throw new \InvalidArgumentException('Недопустимая роль пользователя.');
        }

        $user = $this->dbManager->getUserById($userId);
        if ($user === null) {
            throw new \RuntimeException('Пользователь не найден.');
        }

        // Проверяем уникальность имени пользователя (если оно изменилось)
        if ($user->getUsername() !== $username) {
            $existingUser = $this->dbManager->getUserByUsername($username);
            if ($existingUser !== null && $existingUser->getId() !== $userId) {
                throw new \RuntimeException('Пользователь с таким именем уже существует.');
            }
        }

        $passwordHash = $password !== null && $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $user->getPasswordHash();

        $user->setUsername($username);
        $user->setPasswordHash($passwordHash);
        $user->setRole($role);
        $user->setCompetitionId($competitionId);
        if ($displayName !== null) {
            $user->setDisplayName($displayName);
        }

        $this->dbManager->updateUser($user);
    }

    public function deleteUser(int $userId): void
    {
        $user = $this->dbManager->getUserById($userId);
        if ($user === null) {
            throw new \RuntimeException('Пользователь не найден.');
        }

        // Нельзя удалить самого себя (проверка выполняется на уровне вызова)
        $this->dbManager->deleteUser($userId);
    }

    public function getUserById(int $userId): ?User
    {
        return $this->dbManager->getUserById($userId);
    }

    public function getUserByUsername(string $username): ?User
    {
        return $this->dbManager->getUserByUsername($username);
    }

    public function getAllUsers(): array
    {
        return $this->dbManager->getAllUsers();
    }

    public function authenticate(string $username, string $password): ?User
    {
        $user = $this->dbManager->getUserByUsername($username);
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user->getPasswordHash())) {
            return null;
        }

        return $user;
    }

    public function getJudgesByCompetition(int $competitionId): array
    {
        return $this->dbManager->getJudgesByCompetition($competitionId);
    }

    /**
     * Получить судей соревнования с указанным именем (display_name)
     */
    public function getJudgesWithDisplayName(int $competitionId): array
    {
        $judges = $this->dbManager->getJudgesByCompetition($competitionId);
        return array_filter($judges, function($judge) {
            return $judge->getDisplayName() !== null && $judge->getDisplayName() !== '';
        });
    }

    public function getSecretaryByCompetition(int $competitionId): ?User
    {
        return $this->dbManager->getSecretaryByCompetition($competitionId);
    }

    public function getAllJudges(): array
    {
        return $this->dbManager->getAllJudges();
    }

    public function getAllSecretaries(): array
    {
        return $this->dbManager->getAllSecretaries();
    }

    public function updateCompetitionStaff(int $competitionId, array $judgeIds, ?int $secretaryId): void
    {
        $this->dbManager->updateCompetitionStaff($competitionId, $judgeIds, $secretaryId);
    }

    public function createCategory(int $competitionId, string $name, float $timeLimit, int $hidesCount, float $maxScore, array $penaltyRules): int
    {
        $category = new Category($competitionId, $name, $timeLimit, $hidesCount, (int)$maxScore, $penaltyRules);
        $categoryId = $this->dbManager->insertCategory($category);

        foreach ($penaltyRules as $sequence => $ruleData) {
            $rule = new PenaltyRule(
                $categoryId,
                $ruleData['name'],
                $ruleData['type'],
                $ruleData['points'],
                $sequence + 1
            );
            $this->dbManager->insertPenaltyRule($rule);
        }

        return $categoryId;
    }

    public function updateCategory(int $categoryId, string $name, float $timeLimit, int $hidesCount, int $maxScore, array $penaltyRules): void
    {
        $category = $this->dbManager->getCategoryById($categoryId);
        if ($category === null) {
            throw new \RuntimeException('Категория не найдена.');
        }

        $updatedCategory = new Category(
            $category->getCompetitionId(),
            $name,
            $timeLimit,
            $hidesCount,
            $maxScore,
            [],
            $categoryId
        );

        $this->dbManager->updateCategory($updatedCategory);
        $this->dbManager->deletePenaltyRulesByCategory($categoryId);

        foreach ($penaltyRules as $sequence => $ruleData) {
            $rule = new PenaltyRule(
                $categoryId,
                $ruleData['name'],
                $ruleData['type'],
                $ruleData['points'],
                $sequence + 1
            );
            $this->dbManager->insertPenaltyRule($rule);
        }
    }

    public function createParticipant(string $name, ?string $breed = null, ?string $nickname = null, ?string $gender = null, ?string $birthDate = null, ?string $microchipNumber = null, ?string $pedigreeNumber = null, ?string $qualificationBookNumber = null, ?string $instructorName = null, ?int $createdBy = null): int
    {
        $participant = new Participant(
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

        return $this->dbManager->insertParticipant($participant, $createdBy);
    }

    public function getParticipantById(int $participantId): ?Participant
    {
        return $this->dbManager->getParticipantById($participantId);
    }

    public function getParticipants(?int $userId = null, string $role = 'admin'): array
    {
        return $this->dbManager->getParticipants($userId, $role);
    }

    public function assignParticipantToCompetition(int $competitionId, int $participantId): void
    {
        $this->dbManager->insertCompetitionParticipant($competitionId, $participantId);
    }

    public function removeParticipantFromCompetition(int $competitionId, int $participantId): void
    {
        $this->dbManager->deleteCompetitionParticipant($competitionId, $participantId);
    }

    public function updateParticipant(int $participantId, string $name, ?string $breed = null, ?string $nickname = null, ?string $gender = null, ?string $birthDate = null, ?string $microchipNumber = null, ?string $pedigreeNumber = null, ?string $qualificationBookNumber = null, ?string $instructorName = null): void
    {
        $participant = $this->dbManager->getParticipantById($participantId);
        if ($participant === null) {
            throw new \RuntimeException('Участник не найден.');
        }

        $updatedParticipant = new Participant(
            $name,
            $breed,
            $nickname,
            $gender,
            $birthDate,
            $microchipNumber,
            $pedigreeNumber,
            $qualificationBookNumber,
            $instructorName,
            $participantId
        );

        $this->dbManager->updateParticipant($updatedParticipant);
    }

    public function getParticipantsByCompetition(int $competitionId): array
    {
        return $this->dbManager->getParticipantsByCompetition($competitionId);
    }

    public function deleteCategory(int $categoryId): void
    {
        $this->dbManager->deleteCategoryById($categoryId);
    }

    public function addResult(int $categoryId, int $participantId, float $time, int $foundItems, array $penaltyCounts, ?string $judgeComment = null): void
    {
        $category = $this->dbManager->getCategoryById($categoryId);
        if ($category === null) {
            throw new \RuntimeException('Категория не найдена.');
        }

        $participant = $this->dbManager->getParticipantById($participantId);
        if ($participant === null) {
            throw new \RuntimeException('Участник не найден.');
        }

        $penaltyScore = $category->calculatePenaltyScore($penaltyCounts);
        $totalScore = $category->calculateTotalScore($time, $foundItems, $penaltyCounts);

        $result = new Result(
            $categoryId,
            $participant->getName(),
            $participantId,
            $time,
            $foundItems,
            $penaltyCounts,
            $penaltyScore,
            $totalScore,
            null,
            $judgeComment
        );

        $this->dbManager->insertResult($result);
    }

    public function getCompetitions(): array
    {
        return $this->dbManager->getCompetitions();
    }

    public function getCategoriesByCompetition(int $competitionId): array
    {
        return $this->dbManager->getCategoriesByCompetition($competitionId);
    }

    public function getCategoryById(int $categoryId): ?Category
    {
        return $this->dbManager->getCategoryById($categoryId);
    }

    public function getResultsByCategory(int $categoryId): array
    {
        return $this->dbManager->getResultsByCategory($categoryId);
    }

    public function clearResultsForCategory(int $categoryId): void
    {
        $this->dbManager->clearResultsByCategory($categoryId);
    }

    public function getOverallResults(int $competitionId): array
    {
        return $this->dbManager->getOverallResults($competitionId);
    }

    public function getQualificationResults(int $competitionId): array
    {
        return $this->dbManager->getQualificationResults($competitionId);
    }

    public function getResultByParticipantAndCategory(int $categoryId, int $participantId): ?Result
    {
        return $this->dbManager->getResultByParticipantAndCategory($categoryId, $participantId);
    }

    /**
     * Удаляет результат и перемещает его в таблицу удалённых результатов
     */
    public function deleteResult(int $resultId, int $deletedByUserId): bool
    {
        return $this->dbManager->deleteResult($resultId, $deletedByUserId);
    }

    /**
     * Получает удалённые результаты для соревнования
     */
    public function getDeletedResults(int $competitionId): array
    {
        return $this->dbManager->getDeletedResults($competitionId);
    }

    /**
     * Восстанавливает результат из таблицы удалённых результатов
     */
    public function restoreResult(int $deletedResultId, int $restoredByUserId): array
    {
        return $this->dbManager->restoreResult($deletedResultId, $restoredByUserId);
    }

    /**
     * Получает категории по их идентификаторам
     */
    public function getCategoriesByIds(array $categoryIds): array
    {
        return $this->dbManager->getCategoriesByIds($categoryIds);
    }

    public function createStandardRuleTemplate(string $name, float $timeLimit, int $hidesCount, int $maxScore, array $penaltyRules): int
    {
        $penaltyRuleObjects = [];
        foreach ($penaltyRules as $index => $ruleData) {
            if (is_array($ruleData)) {
                $penaltyRuleObjects[] = new PenaltyRule(
                    0,
                    $ruleData['name'],
                    $ruleData['type'],
                    $ruleData['points'],
                    $index + 1
                );
            } else {
                $penaltyRuleObjects[] = $ruleData;
            }
        }
        $template = new StandardRuleTemplate($name, $timeLimit, $hidesCount, $maxScore, $penaltyRuleObjects);
        return $this->dbManager->insertStandardRuleTemplate($template);
    }

    public function getStandardRuleTemplates(): array
    {
        return $this->dbManager->getStandardRuleTemplates();
    }

    public function deleteStandardRuleTemplate(int $templateId): void
    {
        $this->dbManager->deleteStandardRuleTemplate($templateId);
    }

    public function getStandardRuleTemplateById(int $templateId): ?StandardRuleTemplate
    {
        return $this->dbManager->getStandardRuleTemplateById($templateId);
    }

    public function updateStandardRuleTemplate(int $templateId, string $name, float $timeLimit, int $hidesCount, int $maxScore, array $penaltyRules): void
    {
        $penaltyRuleObjects = [];
        foreach ($penaltyRules as $index => $ruleData) {
            if (is_array($ruleData)) {
                $penaltyRuleObjects[] = new PenaltyRule(
                    0,
                    $ruleData['name'],
                    $ruleData['type'],
                    $ruleData['points'],
                    $index + 1
                );
            } else {
                $penaltyRuleObjects[] = $ruleData;
            }
        }
        $template = new StandardRuleTemplate($name, $timeLimit, $hidesCount, $maxScore, $penaltyRuleObjects, $templateId);
        $this->dbManager->updateStandardRuleTemplate($template);
    }

    /**
     * Публикует результаты соревнования
     */
    public function publishCompetitionResults(int $competitionId): void
    {
        $this->dbManager->setCompetitionPublished($competitionId, true);
    }

    /**
     * Скрывает результаты соревнования из публичного доступа
     */
    public function unpublishCompetitionResults(int $competitionId): void
    {
        $this->dbManager->setCompetitionPublished($competitionId, false);
    }

    public function updateParticipantSortOrder(int $competitionId, array $participantIds): void
    {
        $this->dbManager->updateParticipantSortOrder($competitionId, $participantIds);
    }

    public function updateCategorySortOrder(int $competitionId, array $categoryIds): void
    {
        $this->dbManager->updateCategorySortOrder($competitionId, $categoryIds);
    }

    /**
     * Полностью удаляет соревнование и все связанные данные
     */
    public function deleteCompetition(int $competitionId): bool
    {
        return $this->dbManager->deleteCompetition($competitionId);
    }
}