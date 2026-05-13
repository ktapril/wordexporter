<?php

namespace NoseworkV2;

use PDO;
use PDOException;

class DatabaseManager
{
    private PDO $pdo;

    public function __construct(string $dbPath = 'nosework.db')
    {
        try {
            $this->pdo = new PDO("sqlite:$dbPath");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createSchema();
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS competitions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NOT NULL
            );"
        );

        // Добавляем поле is_published, если оно отсутствует
        if (!$this->hasColumn('competitions', 'is_published')) {
            $this->pdo->exec('ALTER TABLE competitions ADD COLUMN is_published INTEGER DEFAULT 0;');
        }

        // Добавляем поля start_date и end_date, если они отсутствуют
        if (!$this->hasColumn('competitions', 'start_date')) {
            $this->pdo->exec('ALTER TABLE competitions ADD COLUMN start_date TEXT;');
        }
        if (!$this->hasColumn('competitions', 'end_date')) {
            $this->pdo->exec('ALTER TABLE competitions ADD COLUMN end_date TEXT;');
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                competition_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                time_limit REAL NOT NULL,
                hides_count INTEGER NOT NULL,
                max_score INTEGER NOT NULL,
                sort_order INTEGER DEFAULT 0,
                FOREIGN KEY (competition_id) REFERENCES competitions(id)
            );"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS penalty_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                points TEXT NOT NULL,
                sequence_index INTEGER NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS standard_rule_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                time_limit REAL NOT NULL,
                hides_count INTEGER NOT NULL,
                max_score INTEGER NOT NULL
            );"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS standard_template_penalty_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                points TEXT NOT NULL,
                sequence_index INTEGER NOT NULL,
                FOREIGN KEY (template_id) REFERENCES standard_rule_templates(id)
            );"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                participant_id INTEGER,
                participant_name TEXT NOT NULL,
                time REAL NOT NULL,
                found_items INTEGER NOT NULL,
                penalty_counts TEXT NOT NULL,
                penalty_score INTEGER NOT NULL,
                total_score INTEGER NOT NULL,
                judge_comment TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id),
                FOREIGN KEY (participant_id) REFERENCES participants(id)
            );"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS deleted_results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                result_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                participant_id INTEGER,
                participant_name TEXT NOT NULL,
                time REAL NOT NULL,
                found_items INTEGER NOT NULL,
                penalty_counts TEXT NOT NULL,
                penalty_score INTEGER NOT NULL,
                total_score INTEGER NOT NULL,
                judge_comment TEXT,
                deleted_at TEXT NOT NULL,
                deleted_by INTEGER NOT NULL,
                FOREIGN KEY (result_id) REFERENCES results(id),
                FOREIGN KEY (category_id) REFERENCES categories(id),
                FOREIGN KEY (participant_id) REFERENCES participants(id),
                FOREIGN KEY (deleted_by) REFERENCES users(id)
            );"
        );

        $expectedParticipantColumns = [
            'id',
            'name',
            'breed',
            'nickname',
            'gender',
            'birth_date',
            'microchip_number',
            'pedigree_number',
            'qualification_book_number',
            'instructor_name',
            'created_by',
        ];

        if ($this->hasTable('participants')) {
            $existingColumns = $this->getTableColumns('participants');
            $missingColumns = array_diff($expectedParticipantColumns, $existingColumns);

            if (!empty($missingColumns)) {
                $backupName = 'participants_old_' . time();
                $this->pdo->exec("ALTER TABLE participants RENAME TO $backupName;");
            }
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                breed TEXT,
                nickname TEXT,
                gender TEXT,
                birth_date TEXT,
                microchip_number TEXT,
                pedigree_number TEXT,
                qualification_book_number TEXT,
                instructor_name TEXT,
                created_by INTEGER,
                FOREIGN KEY (created_by) REFERENCES users(id)
            );"
        );

        $participantColumns = [
            'breed' => 'TEXT',
            'nickname' => 'TEXT',
            'gender' => 'TEXT',
            'birth_date' => 'TEXT',
            'microchip_number' => 'TEXT',
            'pedigree_number' => 'TEXT',
            'qualification_book_number' => 'TEXT',
            'instructor_name' => 'TEXT',
            'created_by' => 'INTEGER',
        ];

        foreach ($participantColumns as $column => $type) {
            if (!$this->hasColumn('participants', $column)) {
                $this->pdo->exec("ALTER TABLE participants ADD COLUMN $column $type;");
            }
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS competition_participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                competition_id INTEGER NOT NULL,
                participant_id INTEGER NOT NULL,
                sort_order INTEGER DEFAULT 0,
                FOREIGN KEY (competition_id) REFERENCES competitions(id),
                FOREIGN KEY (participant_id) REFERENCES participants(id),
                UNIQUE (competition_id, participant_id)
            );"
        );

        // Добавляем поле sort_order, если оно отсутствует
        if (!$this->hasColumn('competition_participants', 'sort_order')) {
            $this->pdo->exec('ALTER TABLE competition_participants ADD COLUMN sort_order INTEGER DEFAULT 0;');
        }

        // Добавляем поле sort_order в таблицу categories, если оно отсутствует
        if (!$this->hasColumn('categories', 'sort_order')) {
            $this->pdo->exec('ALTER TABLE categories ADD COLUMN sort_order INTEGER DEFAULT 0;');
        }

        if (!$this->hasColumn('results', 'participant_id')) {
            $this->pdo->exec('ALTER TABLE results ADD COLUMN participant_id INTEGER;');
        }

        // Создаём таблицу пользователей, если она не существует
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL,
                competition_id INTEGER,
                display_name TEXT,
                FOREIGN KEY (competition_id) REFERENCES competitions(id)
            );"
        );

        // Добавляем поле display_name, если оно отсутствует
        if (!$this->hasColumn('users', 'display_name')) {
            $this->pdo->exec('ALTER TABLE users ADD COLUMN display_name TEXT;');
        }

        // Создаём таблицу связей пользователей с соревнованиями (для поддержки множественных соревнований)
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_competitions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                competition_id INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
                UNIQUE (user_id, competition_id)
            );"
        );

        // Миграция: переносим данные из старой колонки competition_id в новую таблицу
        if ($this->hasColumn('users', 'competition_id')) {
            $this->pdo->exec(
                "INSERT OR IGNORE INTO user_competitions (user_id, competition_id)
                 SELECT id, competition_id FROM users WHERE competition_id IS NOT NULL AND competition_id > 0"
            );
        }

        // Создаём администратора по умолчанию, если таблица пуста
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users');
        if ((int)$stmt->fetchColumn() === 0) {
            $defaultAdminPassword = 'admin';
            $passwordHash = password_hash($defaultAdminPassword, PASSWORD_DEFAULT);
            $this->pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')
                ->execute(['admin', $passwordHash, User::ROLE_ADMIN]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $columnInfo) {
            if ($columnInfo['name'] === $column) {
                return true;
            }
        }

        return false;
    }

    private function hasTable(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private function getTableColumns(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($columns, 'name');
    }

    public function insertCompetition(Competition $competition): int
    {
        $sql = "INSERT INTO competitions (name, description, start_date, end_date) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $competition->getName(),
            $competition->getDescription(),
            $competition->getStartDate(),
            $competition->getEndDate()
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function insertCategory(Category $category): int
    {
        $sql = "INSERT INTO categories (competition_id, name, time_limit, hides_count, max_score) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $category->getCompetitionId(),
            $category->getName(),
            $category->getTimeLimit(),
            $category->getHidesCount(),
            $category->getMaxScore(),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function insertPenaltyRule(PenaltyRule $rule): int
    {
        $sql = "INSERT INTO penalty_rules (category_id, name, type, points, sequence_index) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $rule->getCategoryId(),
            $rule->getName(),
            $rule->getType(),
            json_encode($rule->getPoints(), JSON_THROW_ON_ERROR),
            $rule->getSequence(),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $rule->setId($id);
        return $id;
    }

    public function insertResult(Result $result): int
    {
        $sql = "INSERT INTO results (category_id, participant_id, participant_name, time, found_items, penalty_counts, penalty_score, total_score, judge_comment, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $result->getCategoryId(),
            $result->getParticipantId(),
            $result->getParticipantName(),
            $result->getTime(),
            $result->getFoundItems(),
            json_encode($result->getPenaltyCounts(), JSON_THROW_ON_ERROR),
            $result->getPenaltyScore(),
            $result->getTotalScore(),
            $result->getJudgeComment(),
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getCompetitions(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM competitions ORDER BY id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $competitions = [];
        foreach ($rows as $row) {
            $competitions[] = new Competition(
                $row['name'],
                $row['description'],
                (int)$row['id'],
                isset($row['is_published']) ? (bool)$row['is_published'] : false,
                $row['start_date'] ?? null,
                $row['end_date'] ?? null
            );
        }

        return $competitions;
    }

    public function getCompetitionById(int $competitionId): ?Competition
    {
        $stmt = $this->pdo->prepare('SELECT * FROM competitions WHERE id = ?');
        $stmt->execute([$competitionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Competition(
            $row['name'],
            $row['description'],
            (int)$row['id'],
            isset($row['is_published']) ? (bool)$row['is_published'] : false,
            $row['start_date'] ?? null,
            $row['end_date'] ?? null
        );
    }

    public function updateCompetition(Competition $competition): void
    {
        $sql = 'UPDATE competitions SET name = ?, description = ?, is_published = ?, start_date = ?, end_date = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $competition->getName(),
            $competition->getDescription(),
            $competition->isPublished() ? 1 : 0,
            $competition->getStartDate(),
            $competition->getEndDate(),
            $competition->getId(),
        ]);
    }

    /**
     * Публикует или скрывает результаты соревнования
     */
    public function setCompetitionPublished(int $competitionId, bool $isPublished): void
    {
        $sql = 'UPDATE competitions SET is_published = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$isPublished ? 1 : 0, $competitionId]);
    }

    public function updateCategory(Category $category): void
    {
        $sql = 'UPDATE categories SET name = ?, time_limit = ?, hides_count = ?, max_score = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $category->getName(),
            $category->getTimeLimit(),
            $category->getHidesCount(),
            $category->getMaxScore(),
            $category->getId(),
        ]);
    }

    public function deletePenaltyRulesByCategory(int $categoryId): void
    {
        $sql = 'DELETE FROM penalty_rules WHERE category_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
    }

    public function getCategoriesByCompetition(int $competitionId): array
    {
        $sql = 'SELECT * FROM categories WHERE competition_id = ? ORDER BY sort_order ASC, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);

        $categories = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $category = new Category(
                (int)$row['competition_id'],
                $row['name'],
                (float)$row['time_limit'],
                (int)$row['hides_count'],
                (int)$row['max_score'],
                [],
                (int)$row['id']
            );
            $category->setPenaltyRules($this->getPenaltyRulesByCategory($category->getId()));
            $categories[] = $category;
        }

        return $categories;
    }

    public function getCategoryById(int $categoryId): ?Category
    {
        $sql = 'SELECT * FROM categories WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $category = new Category(
            (int)$row['competition_id'],
            $row['name'],
            (float)$row['time_limit'],
            (int)$row['hides_count'],
            (int)$row['max_score'],
            [],
            (int)$row['id']
        );
        $category->setPenaltyRules($this->getPenaltyRulesByCategory($category->getId()));

        return $category;
    }

    /**
     * Получает категории по их идентификаторам
     */
    public function getCategoriesByIds(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $sql = "SELECT * FROM categories WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($categoryIds);

        $categories = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $category = new Category(
                (int)$row['competition_id'],
                $row['name'],
                (float)$row['time_limit'],
                (int)$row['hides_count'],
                (int)$row['max_score'],
                [],
                (int)$row['id']
            );
            $category->setPenaltyRules($this->getPenaltyRulesByCategory($category->getId()));
            $categories[] = $category;
        }

        return $categories;
    }

    public function insertParticipant(Participant $participant, ?int $createdBy = null): int
    {
        $sql = 'INSERT INTO participants (name, breed, nickname, gender, birth_date, microchip_number, pedigree_number, qualification_book_number, instructor_name, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $participant->getName(),
            $participant->getBreed(),
            $participant->getNickname(),
            $participant->getGender(),
            $participant->getBirthDate(),
            $participant->getMicrochipNumber(),
            $participant->getPedigreeNumber(),
            $participant->getQualificationBookNumber(),
            $participant->getInstructorName(),
            $createdBy,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $participant->setId($id);

        return $id;
    }

    public function getParticipantById(int $participantId): ?Participant
    {
        $stmt = $this->pdo->prepare('SELECT * FROM participants WHERE id = ?');
        $stmt->execute([$participantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Participant(
            $row['name'],
            $row['breed'] ?? null,
            $row['nickname'] ?? null,
            $row['gender'] ?? null,
            $row['birth_date'] ?? null,
            $row['microchip_number'] ?? null,
            $row['pedigree_number'] ?? null,
            $row['qualification_book_number'] ?? null,
            $row['instructor_name'] ?? null,
            (int)$row['id']
        );
    }

    public function getParticipants(?int $userId = null, string $role = 'admin'): array
    {
        if ($role === User::ROLE_ADMIN) {
            // Администратор видит всех участников
            $stmt = $this->pdo->query('SELECT * FROM participants ORDER BY name ASC');
        } else {
            // Секретарь видит только тех участников, которых создал сам
            $stmt = $this->pdo->prepare('SELECT * FROM participants WHERE created_by = ? ORDER BY name ASC');
            $stmt->execute([$userId]);
        }

        $participants = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participants[] = new Participant(
                $row['name'],
                $row['breed'] ?? null,
                $row['nickname'] ?? null,
                $row['gender'] ?? null,
                $row['birth_date'] ?? null,
                $row['microchip_number'] ?? null,
                $row['pedigree_number'] ?? null,
                $row['qualification_book_number'] ?? null,
                $row['instructor_name'] ?? null,
                (int)$row['id']
            );
        }

        return $participants;
    }

    public function updateParticipant(Participant $participant): void
    {
        $sql = 'UPDATE participants SET name = ?, breed = ?, nickname = ?, gender = ?, birth_date = ?, microchip_number = ?, pedigree_number = ?, qualification_book_number = ?, instructor_name = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $participant->getName(),
            $participant->getBreed(),
            $participant->getNickname(),
            $participant->getGender(),
            $participant->getBirthDate(),
            $participant->getMicrochipNumber(),
            $participant->getPedigreeNumber(),
            $participant->getQualificationBookNumber(),
            $participant->getInstructorName(),
            $participant->getId(),
        ]);
    }

    public function insertCompetitionParticipant(int $competitionId, int $participantId): void
    {
        // Получаем максимальный sort_order для этого соревнования
        $sql = 'SELECT COALESCE(MAX(sort_order), 0) as max_order FROM competition_participants WHERE competition_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);
        $maxOrder = (int)$stmt->fetchColumn();
        
        $sql = 'INSERT OR IGNORE INTO competition_participants (competition_id, participant_id, sort_order) VALUES (?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId, $participantId, $maxOrder + 1]);
    }

    public function updateParticipantSortOrder(int $competitionId, array $participantIds): void
    {
        $sql = 'UPDATE competition_participants SET sort_order = ? WHERE competition_id = ? AND participant_id = ?';
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($participantIds as $order => $participantId) {
            $stmt->execute([$order + 1, $competitionId, $participantId]);
        }
    }

    public function updateCategorySortOrder(int $competitionId, array $categoryIds): void
    {
        $sql = 'UPDATE categories SET sort_order = ? WHERE competition_id = ? AND id = ?';
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($categoryIds as $order => $categoryId) {
            $stmt->execute([$order + 1, $competitionId, $categoryId]);
        }
    }

    public function deleteCompetitionParticipant(int $competitionId, int $participantId): void
    {
        $sql = 'DELETE FROM competition_participants WHERE competition_id = ? AND participant_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId, $participantId]);
    }

    public function getParticipantsByCompetition(int $competitionId): array
    {
        $sql = 'SELECT p.*, cp.sort_order FROM participants p JOIN competition_participants cp ON cp.participant_id = p.id WHERE cp.competition_id = ? ORDER BY cp.sort_order ASC, p.name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);

        $participants = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participant = new Participant(
                $row['name'],
                $row['breed'] ?? null,
                $row['nickname'] ?? null,
                $row['gender'] ?? null,
                $row['birth_date'] ?? null,
                $row['microchip_number'] ?? null,
                $row['pedigree_number'] ?? null,
                $row['qualification_book_number'] ?? null,
                $row['instructor_name'] ?? null,
                (int)$row['id']
            );
            $participant->setSortOrder((int)($row['sort_order'] ?? 0));
            $participants[] = $participant;
        }

        return $participants;
    }

    public function deleteCategoryById(int $categoryId): void
    {
        $this->clearResultsByCategory($categoryId);
        $this->deletePenaltyRulesByCategory($categoryId);

        $sql = 'DELETE FROM categories WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
    }

    public function getPenaltyRulesByCategory(int $categoryId): array
    {
        $sql = 'SELECT * FROM penalty_rules WHERE category_id = ? ORDER BY sequence_index ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);

        $rules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rules[] = new PenaltyRule(
                (int)$row['category_id'],
                $row['name'],
                $row['type'],
                json_decode($row['points'], true, 512, JSON_THROW_ON_ERROR),
                (int)$row['sequence_index'],
                (int)$row['id']
            );
        }

        return $rules;
    }

    public function getResultsByCategory(int $categoryId): array
    {
        $sql = 'SELECT * FROM results WHERE category_id = ? ORDER BY total_score DESC, time ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participantId = $row['participant_id'] !== null && $row['participant_id'] !== '' ? (int)$row['participant_id'] : null;
            $penaltyDetails = isset($row['penalty_details']) && $row['penalty_details'] !== null && $row['penalty_details'] !== '' 
                ? json_decode($row['penalty_details'], true, 512, JSON_THROW_ON_ERROR) 
                : [];
            $results[] = new Result(
                (int)$row['category_id'],
                $row['participant_name'],
                $participantId,
                (float)$row['time'],
                (int)$row['found_items'],
                json_decode($row['penalty_counts'], true, 512, JSON_THROW_ON_ERROR),
                (int)$row['penalty_score'],
                (int)$row['total_score'],
                (int)$row['id'],
                $row['judge_comment'] ?? null,
                $penaltyDetails
            );
        }

        return $results;
    }

    public function clearResultsByCategory(int $categoryId): void
    {
        $sql = 'DELETE FROM results WHERE category_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
    }

    /**
     * Перемещает результат в таблицу удалённых результатов и удаляет из основной таблицы
     */
    public function deleteResult(int $resultId, int $deletedByUserId): bool
    {
        // Получаем результат для переноса в таблицу удалённых
        $result = $this->getResultById($resultId);
        if ($result === null) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            // Вставляем запись в таблицу deleted_results
            $sql = "INSERT INTO deleted_results 
                    (result_id, category_id, participant_id, participant_name, time, found_items, penalty_counts, penalty_score, total_score, judge_comment, deleted_at, deleted_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $resultId,
                $result->getCategoryId(),
                $result->getParticipantId(),
                $result->getParticipantName(),
                $result->getTime(),
                $result->getFoundItems(),
                json_encode($result->getPenaltyCounts(), JSON_THROW_ON_ERROR),
                $result->getPenaltyScore(),
                $result->getTotalScore(),
                $result->getJudgeComment(),
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                $deletedByUserId,
            ]);

            // Удаляем результат из основной таблицы
            $sql = 'DELETE FROM results WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$resultId]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Получает результат по ID
     */
    public function getResultById(int $resultId): ?Result
    {
        $sql = 'SELECT * FROM results WHERE id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$resultId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $participantId = $row['participant_id'] !== null && $row['participant_id'] !== '' ? (int)$row['participant_id'] : null;
        return new Result(
            (int)$row['category_id'],
            $row['participant_name'],
            $participantId,
            (float)$row['time'],
            (int)$row['found_items'],
            json_decode($row['penalty_counts'], true, 512, JSON_THROW_ON_ERROR),
            (int)$row['penalty_score'],
            (int)$row['total_score'],
            (int)$row['id'],
            $row['judge_comment'] ?? null
        );
    }

    /**
     * Получает все удалённые результаты для соревнования
     */
    public function getDeletedResults(int $competitionId): array
    {
        $sql = '
            SELECT dr.*, u.username as deleted_by_username
            FROM deleted_results dr
            INNER JOIN categories c ON dr.category_id = c.id
            INNER JOIN users u ON dr.deleted_by = u.id
            WHERE c.competition_id = ?
            ORDER BY dr.deleted_at DESC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participantId = $row['participant_id'] !== null && $row['participant_id'] !== '' ? (int)$row['participant_id'] : null;
            $results[] = [
                'id' => (int)$row['id'],
                'result_id' => (int)$row['result_id'],
                'category_id' => (int)$row['category_id'],
                'participant_id' => $participantId,
                'participant_name' => $row['participant_name'],
                'time' => (float)$row['time'],
                'found_items' => (int)$row['found_items'],
                'penalty_counts' => json_decode($row['penalty_counts'], true, 512, JSON_THROW_ON_ERROR),
                'penalty_score' => (int)$row['penalty_score'],
                'total_score' => (int)$row['total_score'],
                'judge_comment' => $row['judge_comment'] ?? null,
                'deleted_at' => $row['deleted_at'],
                'deleted_by_username' => $row['deleted_by_username'],
            ];
        }

        return $results;
    }

    /**
     * Проверяет, существует ли результат участника в категории
     */
    public function hasResultForParticipantInCategory(int $categoryId, int $participantId): bool
    {
        $sql = 'SELECT COUNT(*) FROM results WHERE category_id = ? AND participant_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId, $participantId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Восстанавливает результат из таблицы удалённых результатов
     * Возвращает: 'success' - успешно, 'exists' - результат уже существует, 'error' - ошибка
     */
    public function restoreResult(int $deletedResultId, int $restoredByUserId): array
    {
        // Получаем удалённый результат
        $sql = 'SELECT * FROM deleted_results WHERE id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$deletedResultId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['status' => 'error', 'message' => 'Удалённый результат не найден'];
        }

        $categoryId = (int)$row['category_id'];
        $participantId = $row['participant_id'] !== null && $row['participant_id'] !== '' ? (int)$row['participant_id'] : null;

        // Проверяем, существует ли уже результат этого участника в этой категории
        if ($participantId !== null && $this->hasResultForParticipantInCategory($categoryId, $participantId)) {
            return ['status' => 'exists', 'message' => 'Невозможно восстановить результат: у этого участника уже есть результат в данной категории'];
        }

        try {
            $this->pdo->beginTransaction();

            // Вставляем результат обратно в таблицу results
            $sql = "INSERT INTO results 
                    (category_id, participant_id, participant_name, time, found_items, penalty_counts, penalty_score, total_score, judge_comment, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $categoryId,
                $participantId,
                $row['participant_name'],
                $row['time'],
                $row['found_items'],
                $row['penalty_counts'],
                $row['penalty_score'],
                $row['total_score'],
                $row['judge_comment'],
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            // Удаляем из таблицы deleted_results
            $sql = 'DELETE FROM deleted_results WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$deletedResultId]);

            $this->pdo->commit();
            return ['status' => 'success', 'message' => 'Результат восстановлен'];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['status' => 'error', 'message' => 'Ошибка при восстановлении: ' . $e->getMessage()];
        }
    }

    public function getOverallResults(int $competitionId): array
    {
        $sql = '
            SELECT 
                r.participant_id,
                r.participant_name,
                SUM(r.time) AS total_time,
                SUM(r.total_score) AS total_score
            FROM results r
            INNER JOIN categories c ON r.category_id = c.id
            WHERE c.competition_id = ?
            GROUP BY r.participant_id, r.participant_name
            ORDER BY total_score DESC, total_time ASC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'participant_id' => (int)$row['participant_id'],
                'participant_name' => $row['participant_name'],
                'total_time' => (float)$row['total_time'],
                'total_score' => (int)$row['total_score'],
            ];
        }

        return $results;
    }

    /**
     * Получает данные для таблицы квалификации
     * Возвращает массив с данными об участниках: имя, количество категорий с баллом >= 81,
     * сумма баллов в квалифицированных категориях
     */
    public function getQualificationResults(int $competitionId): array
    {
        $sql = '
            SELECT 
                r.participant_id,
                r.participant_name,
                r.category_id,
                r.total_score
            FROM results r
            INNER JOIN categories c ON r.category_id = c.id
            WHERE c.competition_id = ?
            ORDER BY r.participant_id, r.category_id
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$competitionId]);

        $participantData = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participantId = (int)$row['participant_id'];
            if (!isset($participantData[$participantId])) {
                $participantData[$participantId] = [
                    'participant_name' => $row['participant_name'],
                    'qualified_categories' => [],
                ];
            }
            $score = (int)$row['total_score'];
            if ($score >= 81) {
                $participantData[$participantId]['qualified_categories'][] = $score;
            }
        }

        $results = [];
        foreach ($participantData as $data) {
            $qualifiedCount = count($data['qualified_categories']);
            $isQualified = $qualifiedCount >= 3;
            $qualifiedSum = array_sum($data['qualified_categories']);

            $results[] = [
                'participant_name' => $data['participant_name'],
                'is_qualified' => $isQualified,
                'qualified_count' => $qualifiedCount,
                'qualified_sum' => $qualifiedSum,
            ];
        }

        // Сортируем по сумме баллов в квалифицированных категориях (убывание)
        usort($results, function($a, $b) {
            return $b['qualified_sum'] - $a['qualified_sum'];
        });

        return $results;
    }

    public function getResultByParticipantAndCategory(int $categoryId, int $participantId): ?Result
    {
        $sql = 'SELECT * FROM results WHERE category_id = ? AND participant_id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId, $participantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $penaltyDetails = isset($row['penalty_details']) && $row['penalty_details'] !== null && $row['penalty_details'] !== '' 
            ? json_decode($row['penalty_details'], true, 512, JSON_THROW_ON_ERROR) 
            : [];

        return new Result(
            (int)$row['category_id'],
            $row['participant_name'],
            (int)$row['participant_id'] ?: null,
            (float)$row['time'],
            (int)$row['found_items'],
            json_decode($row['penalty_counts'], true, 512, JSON_THROW_ON_ERROR),
            (int)$row['penalty_score'],
            (int)$row['total_score'],
            (int)$row['id'],
            $row['judge_comment'] ?? null,
            $penaltyDetails
        );
    }

    public function insertUser(User $user): int
    {
        $sql = "INSERT INTO users (username, password_hash, role, competition_id, display_name) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $user->getUsername(),
            $user->getPasswordHash(),
            $user->getRole(),
            $user->getCompetitionId(),
            $user->getDisplayName(),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $user->setId($id);

        // Сохраняем связи с соревнованиями из массива competitionIds
        if (!empty($user->getCompetitionIds())) {
            $this->saveUserCompetitions($id, $user->getCompetitionIds());
        }

        return $id;
    }

    public function getUserByUsername(string $username): ?User
    {
        $sql = 'SELECT * FROM users WHERE username = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Получаем список соревнований пользователя
        $competitionIds = $this->getUserCompetitionIds((int)$row['id']);

        return new User(
            $row['username'],
            $row['password_hash'],
            $row['role'],
            $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
            (int)$row['id'],
            $row['display_name'] ?? null,
            $competitionIds
        );
    }

    public function getUserById(int $userId): ?User
    {
        $sql = 'SELECT * FROM users WHERE id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Получаем список соревнований пользователя
        $competitionIds = $this->getUserCompetitionIds((int)$row['id']);

        return new User(
            $row['username'],
            $row['password_hash'],
            $row['role'],
            $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
            (int)$row['id'],
            $row['display_name'] ?? null,
            $competitionIds
        );
    }

    public function getAllUsers(): array
    {
        $sql = 'SELECT * FROM users ORDER BY id ASC';
        $stmt = $this->pdo->query($sql);

        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Получаем список соревнований пользователя
            $competitionIds = $this->getUserCompetitionIds((int)$row['id']);
            
            $users[] = new User(
                $row['username'],
                $row['password_hash'],
                $row['role'],
                $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
                (int)$row['id'],
                $row['display_name'] ?? null,
                $competitionIds
            );
        }

        return $users;
    }

    public function updateUser(User $user): void
    {
        $sql = 'UPDATE users SET username = ?, password_hash = ?, role = ?, competition_id = ?, display_name = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $user->getUsername(),
            $user->getPasswordHash(),
            $user->getRole(),
            $user->getCompetitionId(),
            $user->getDisplayName(),
            $user->getId(),
        ]);

        // Обновляем связи с соревнованиями из массива competitionIds
        $this->saveUserCompetitions($user->getId(), $user->getCompetitionIds());
    }

    public function deleteUser(int $userId): void
    {
        $sql = 'DELETE FROM users WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
    }

    public function getJudgesByCompetition(int $competitionId): array
    {
        $sql = 'SELECT DISTINCT u.* FROM users u 
                LEFT JOIN user_competitions uc ON u.id = uc.user_id
                WHERE u.role = ? AND (u.competition_id = ? OR uc.competition_id = ?) ORDER BY username ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([User::ROLE_JUDGE, $competitionId, $competitionId]);

        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $competitionIds = $this->getUserCompetitionIds((int)$row['id']);
            $users[] = new User(
                $row['username'],
                $row['password_hash'],
                $row['role'],
                $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
                (int)$row['id'],
                $row['display_name'] ?? null,
                $competitionIds
            );
        }

        return $users;
    }

    /**
     * Получить всех судей для выбора при редактировании соревнования
     */
    public function getAllJudges(): array
    {
        $sql = 'SELECT * FROM users WHERE role = ? ORDER BY username ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([User::ROLE_JUDGE]);

        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $competitionIds = $this->getUserCompetitionIds((int)$row['id']);
            $users[] = new User(
                $row['username'],
                $row['password_hash'],
                $row['role'],
                $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
                (int)$row['id'],
                $row['display_name'] ?? null,
                $competitionIds
            );
        }

        return $users;
    }

    /**
     * Получить всех секретарей для выбора при редактировании соревнования
     */
    public function getAllSecretaries(): array
    {
        $sql = 'SELECT * FROM users WHERE role = ? ORDER BY username ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([User::ROLE_SECRETARY]);

        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $competitionIds = $this->getUserCompetitionIds((int)$row['id']);
            $users[] = new User(
                $row['username'],
                $row['password_hash'],
                $row['role'],
                $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
                (int)$row['id'],
                $row['display_name'] ?? null,
                $competitionIds
            );
        }

        return $users;
    }

    public function getSecretaryByCompetition(int $competitionId): ?User
    {
        // Ищем секретаря по старой колонке competition_id или по новой таблице user_competitions
        $sql = 'SELECT DISTINCT u.* FROM users u 
                LEFT JOIN user_competitions uc ON u.id = uc.user_id
                WHERE u.role = ? AND (u.competition_id = ? OR uc.competition_id = ?) LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([User::ROLE_SECRETARY, $competitionId, $competitionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Получаем список соревнований пользователя
        $competitionIds = $this->getUserCompetitionIds((int)$row['id']);

        return new User(
            $row['username'],
            $row['password_hash'],
            $row['role'],
            $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
            (int)$row['id'],
            $row['display_name'] ?? null,
            $competitionIds
        );
    }

    /**
     * Обновить судей и секретарей для соревнования
     */
    public function updateCompetitionStaff(int $competitionId, array $judgeIds, ?int $secretaryId): void
    {
        // Получаем текущие связи для этого соревнования
        $currentJudges = $this->getJudgesByCompetition($competitionId);
        $currentSecretary = $this->getSecretaryByCompetition($competitionId);
        
        $currentJudgeIds = array_map(fn($j) => $j->getId(), $currentJudges);
        $currentSecretaryId = $currentSecretary ? $currentSecretary->getId() : null;
        
        // Определяем судей, которых нужно удалить из этого соревнования
        $judgesToRemove = array_diff($currentJudgeIds, $judgeIds);
        // Определяем судей, которых нужно добавить к этому соревнованию
        $judgesToAdd = array_diff($judgeIds, $currentJudgeIds);
        
        // Удаляем связи для судей, которые больше не назначены на это соревнование
        if (!empty($judgesToRemove)) {
            $placeholders = implode(',', array_fill(0, count($judgesToRemove), '?'));
            $this->pdo->prepare("DELETE FROM user_competitions WHERE competition_id = ? AND user_id IN ($placeholders)")
                ->execute(array_merge([$competitionId], $judgesToRemove));
        }
        
        // Добавляем связи для новых судей
        if (!empty($judgesToAdd)) {
            $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO user_competitions (user_id, competition_id) VALUES (?, ?)');
            foreach ($judgesToAdd as $judgeId) {
                $stmt->execute([(int)$judgeId, $competitionId]);
            }
        }
        
        // Обрабатываем секретаря
        if ($secretaryId !== $currentSecretaryId) {
            // Если был старый секретарь, удаляем его связь с этим соревнованием
            if ($currentSecretaryId !== null) {
                $this->pdo->prepare('DELETE FROM user_competitions WHERE competition_id = ? AND user_id = ?')
                    ->execute([$competitionId, $currentSecretaryId]);
            }
            // Если назначен новый секретарь, добавляем связь
            if ($secretaryId !== null) {
                $this->pdo->prepare('INSERT OR IGNORE INTO user_competitions (user_id, competition_id) VALUES (?, ?)')
                    ->execute([$secretaryId, $competitionId]);
            }
        }
    }

    /**
     * Получить IDs соревнований для пользователя
     */
    public function getUserCompetitionIds(int $userId): array
    {
        $sql = 'SELECT competition_id FROM user_competitions WHERE user_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (int)$id;
        }
        
        return $ids;
    }

    /**
     * Сохранить связи пользователя с соревнованиями
     */
    public function saveUserCompetitions(int $userId, array $competitionIds): void
    {
        // Удаляем старые связи
        $this->pdo->prepare('DELETE FROM user_competitions WHERE user_id = ?')->execute([$userId]);
        
        // Добавляем новые связи
        if (!empty($competitionIds)) {
            $stmt = $this->pdo->prepare('INSERT INTO user_competitions (user_id, competition_id) VALUES (?, ?)');
            foreach ($competitionIds as $competitionId) {
                $stmt->execute([$userId, $competitionId]);
            }
        }
    }

    /**
     * Получить пользователей по соревнованию
     */
    public function getUsersByCompetition(int $competitionId, ?string $role = null): array
    {
        if ($role !== null) {
            $sql = 'SELECT DISTINCT u.* FROM users u 
                    LEFT JOIN user_competitions uc ON u.id = uc.user_id
                    WHERE u.role = ? AND (u.competition_id = ? OR uc.competition_id = ?)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$role, $competitionId, $competitionId]);
        } else {
            $sql = 'SELECT DISTINCT u.* FROM users u 
                    LEFT JOIN user_competitions uc ON u.id = uc.user_id
                    WHERE u.competition_id = ? OR uc.competition_id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId, $competitionId]);
        }
        
        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $competitionIds = $this->getUserCompetitionIds((int)$row['id']);
            $users[] = new User(
                $row['username'],
                $row['password_hash'],
                $row['role'],
                $row['competition_id'] !== null ? (int)$row['competition_id'] : null,
                (int)$row['id'],
                $row['display_name'] ?? null,
                $competitionIds
            );
        }
        
        return $users;
    }

    public function insertStandardRuleTemplate(StandardRuleTemplate $template): int
    {
        $sql = "INSERT INTO standard_rule_templates (name, time_limit, hides_count, max_score) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $template->getName(),
            $template->getTimeLimit(),
            $template->getHidesCount(),
            $template->getMaxScore(),
        ]);

        $templateId = (int)$this->pdo->lastInsertId();

        foreach ($template->getPenaltyRules() as $sequence => $rule) {
            if (is_array($rule)) {
                $rule = new PenaltyRule(
                    $templateId,
                    $rule['name'],
                    $rule['type'],
                    $rule['points'],
                    $sequence + 1
                );
            } else {
                $rule->setCategoryId($templateId);
                $rule->setSequence($sequence + 1);
            }
            $this->insertStandardTemplatePenaltyRule($rule);
        }

        return $templateId;
    }

    public function insertStandardTemplatePenaltyRule(PenaltyRule $rule): int
    {
        $sql = "INSERT INTO standard_template_penalty_rules (template_id, name, type, points, sequence_index) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $rule->getCategoryId(),
            $rule->getName(),
            $rule->getType(),
            json_encode($rule->getPoints(), JSON_THROW_ON_ERROR),
            $rule->getSequence(),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $rule->setId($id);
        return $id;
    }

    public function getStandardRuleTemplates(): array
    {
        $sql = 'SELECT * FROM standard_rule_templates ORDER BY id ASC';
        $stmt = $this->pdo->query($sql);

        $templates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $template = new StandardRuleTemplate(
                $row['name'],
                (float)$row['time_limit'],
                (int)$row['hides_count'],
                (int)$row['max_score'],
                [],
                (int)$row['id']
            );
            $template->setPenaltyRules($this->getStandardTemplatePenaltyRules($template->getId()));
            $templates[] = $template;
        }

        return $templates;
    }

    public function getStandardTemplatePenaltyRules(int $templateId): array
    {
        $sql = 'SELECT * FROM standard_template_penalty_rules WHERE template_id = ? ORDER BY sequence_index ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$templateId]);

        $rules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rules[] = new PenaltyRule(
                (int)$row['template_id'],
                $row['name'],
                $row['type'],
                json_decode($row['points'], true, 512, JSON_THROW_ON_ERROR),
                (int)$row['sequence_index'],
                (int)$row['id']
            );
        }

        return $rules;
    }

    public function deleteStandardRuleTemplate(int $templateId): void
    {
        $sql = 'DELETE FROM standard_template_penalty_rules WHERE template_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$templateId]);

        $sql = 'DELETE FROM standard_rule_templates WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$templateId]);
    }

    public function getStandardRuleTemplateById(int $templateId): ?StandardRuleTemplate
    {
        $sql = 'SELECT * FROM standard_rule_templates WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $template = new StandardRuleTemplate(
            $row['name'],
            (float)$row['time_limit'],
            (int)$row['hides_count'],
            (int)$row['max_score'],
            [],
            (int)$row['id']
        );
        $template->setPenaltyRules($this->getStandardTemplatePenaltyRules($template->getId()));

        return $template;
    }

    public function updateStandardRuleTemplate(StandardRuleTemplate $template): void
    {
        $sql = 'UPDATE standard_rule_templates SET name = ?, time_limit = ?, hides_count = ?, max_score = ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $template->getName(),
            $template->getTimeLimit(),
            $template->getHidesCount(),
            $template->getMaxScore(),
            $template->getId()
        ]);

        // Удаляем старые правила штрафов
        $sql = 'DELETE FROM standard_template_penalty_rules WHERE template_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$template->getId()]);

        // Вставляем новые правила штрафов
        foreach ($template->getPenaltyRules() as $sequence => $rule) {
            if (is_array($rule)) {
                $ruleObj = new PenaltyRule(
                    $template->getId(),
                    $rule['name'],
                    $rule['type'],
                    $rule['points'],
                    $sequence + 1
                );
            } else {
                $ruleObj = $rule;
                $ruleObj->setCategoryId($template->getId());
                $ruleObj->setSequence($sequence + 1);
            }
            $sql = 'INSERT INTO standard_template_penalty_rules (template_id, name, type, points, sequence_index) VALUES (?, ?, ?, ?, ?)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $template->getId(),
                $ruleObj->getName(),
                $ruleObj->getType(),
                json_encode($ruleObj->getPoints(), JSON_THROW_ON_ERROR),
                $sequence + 1
            ]);
        }
    }

    /**
     * Полностью удаляет соревнование и все связанные данные
     * @param int $competitionId ID соревнования
     * @return bool true если успешно, false иначе
     */
    public function deleteCompetition(int $competitionId): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Получаем ID всех категорий соревнования (без загрузки правил штрафов)
            $sql = 'SELECT id FROM categories WHERE competition_id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId]);
            $categoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Для каждой категории удаляем результаты и правила штрафов
            foreach ($categoryIds as $categoryId) {
                $this->clearResultsByCategory($categoryId);
                $this->deletePenaltyRulesByCategory($categoryId);
            }

            // Удаляем удалённые результаты (deleted_results), связанные с категориями этого соревнования
            if (!empty($categoryIds)) {
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $sql = "DELETE FROM deleted_results WHERE category_id IN ($placeholders)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($categoryIds);
            }

            // Удаляем связи участников с соревнованием
            $sql = 'DELETE FROM competition_participants WHERE competition_id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId]);

            // Удаляем связи пользователей с соревнованием (user_competitions)
            $sql = 'DELETE FROM user_competitions WHERE competition_id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId]);

            // Обновляем пользователей, у которых competition_id ссылается на это соревнование
            $sql = 'UPDATE users SET competition_id = NULL WHERE competition_id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId]);

            // Удаляем все категории (правила штрафов уже удалены)
            $sql = 'DELETE FROM categories WHERE competition_id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId]);

            // Удаляем само соревнование
            $sql = 'DELETE FROM competitions WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$competitionId]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}