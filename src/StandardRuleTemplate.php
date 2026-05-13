<?php

namespace NoseworkV2;

class StandardRuleTemplate
{
    private ?int $id;
    private string $name;
    private float $timeLimit;
    private int $hidesCount;
    private int $maxScore;
    private array $penaltyRules = [];

    public function __construct(string $name, float $timeLimit, int $hidesCount, int $maxScore, array $penaltyRules = [], ?int $id = null)
    {
        $this->id = $id;
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
}
