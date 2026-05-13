<?php

namespace NoseworkV2;

class Category
{
    private ?int $id;
    private int $competitionId;
    private string $name;
    private float $timeLimit;
    private int $hidesCount;
    private int $maxScore;
    private array $penaltyRules = [];

    public function __construct(int $competitionId, string $name, float $timeLimit, int $hidesCount, int $maxScore, array $penaltyRules = [], ?int $id = null)
    {
        $this->id = $id;
        $this->competitionId = $competitionId;
        $this->name = $name;
        $this->timeLimit = $timeLimit;
        $this->hidesCount = $hidesCount;
        $this->maxScore = $maxScore;
        $this->penaltyRules = $penaltyRules;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCompetitionId(): int
    {
        return $this->competitionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimeLimit(): float
    {
        return $this->timeLimit;
    }

    public function getHidesCount(): int
    {
        return $this->hidesCount;
    }

    public function getMaxScore(): int
    {
        return $this->maxScore;
    }

    public function getPenaltyRules(): array
    {
        return $this->penaltyRules;
    }

    public function setPenaltyRules(array $penaltyRules): void
    {
        $this->penaltyRules = $penaltyRules;
    }

    public function calculatePenaltyScore(array $penaltyCounts): int
    {
        $score = 0;

        foreach ($this->penaltyRules as $rule) {
            $ruleId = $rule->getId();
            if ($ruleId === null) {
                continue;
            }

            $count = $penaltyCounts[$ruleId] ?? 0;
            $score += $rule->calculateScore($count);
        }

        return $score;
    }

    public function calculateTotalScore(float $time, int $foundItems, array $penaltyCounts): int
    {
        if ($foundItems < $this->hidesCount) {
            return 0;
        }

        $penaltyScore = $this->calculatePenaltyScore($penaltyCounts);
        $rawScore = $this->maxScore - $penaltyScore;

        return max(0, (int)$rawScore);
    }
}