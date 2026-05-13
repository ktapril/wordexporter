<?php

namespace NoseworkV2;

class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_JUDGE = 'judge';
    public const ROLE_SECRETARY = 'secretary';

    private int $id;
    private string $username;
    private string $passwordHash;
    private string $role;
    private ?int $competitionId;
    private ?string $displayName;
    private array $competitionIds;

    public function __construct(
        string $username,
        string $passwordHash,
        string $role,
        ?int $competitionId = null,
        ?int $id = null,
        ?string $displayName = null,
        array $competitionIds = []
    ) {
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->competitionId = $competitionId;
        $this->id = $id ?? 0;
        $this->displayName = $displayName;
        $this->competitionIds = $competitionIds;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getCompetitionId(): ?int
    {
        return $this->competitionId;
    }

    public function setCompetitionId(?int $competitionId): void
    {
        $this->competitionId = $competitionId;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getCompetitionIds(): array
    {
        return $this->competitionIds;
    }

    public function setCompetitionIds(array $competitionIds): void
    {
        $this->competitionIds = $competitionIds;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isJudge(): bool
    {
        return $this->role === self::ROLE_JUDGE;
    }

    public function isSecretary(): bool
    {
        return $this->role === self::ROLE_SECRETARY;
    }

    public static function getAvailableRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_JUDGE => 'Судья',
            self::ROLE_SECRETARY => 'Секретарь',
        ];
    }

    public static function getRoleLabel(string $role): string
    {
        $roles = self::getAvailableRoles();
        return $roles[$role] ?? $role;
    }
}
