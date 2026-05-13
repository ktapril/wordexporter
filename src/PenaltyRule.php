<?php

namespace NoseworkV2;

class PenaltyRule
{
    public const TYPE_FLAT = 'flat';
    public const TYPE_PROGRESSIVE = 'progressive';

    private ?int $id;
    private int $categoryId;
    private string $name;
    private string $type;
    private array $points;
    private int $sequence;

    public function __construct(int $categoryId, string $name, string $type, array $points, int $sequence = 1, ?int $id = null)
    {
        $this->id = $id;
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->type = $type;
        $this->points = $points;
        $this->sequence = $sequence;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPoints(): array
    {
        return $this->points;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function setSequence(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function calculateScore(float $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        if ($this->type === self::TYPE_FLAT) {
            return (int)$count;
        }

        $score = 0;
        $totalPoints = count($this->points);
        $count = (int)$count;

        for ($i = 0; $i < $count; $i++) {
            $score += (int)($this->points[min($i, $totalPoints - 1)] ?? 0);
        }

        return $score;
    }
}