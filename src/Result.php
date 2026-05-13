<?php

namespace NoseworkV2;

class Result
{
    private ?int $id;
    private int $categoryId;
    private ?int $participantId;
    private string $participantName;
    private float $time;
    private int $foundItems;
    private array $penaltyCounts;
    private int $penaltyScore;
    private int $totalScore;
    private ?string $judgeComment;
    private array $penaltyDetails;

    public function __construct(
        int $categoryId,
        string $participantName,
        ?int $participantId,
        float $time,
        int $foundItems,
        array $penaltyCounts,
        int $penaltyScore,
        int $totalScore,
        ?int $id = null,
        ?string $judgeComment = null,
        array $penaltyDetails = []
    ) {
        $this->id = $id;
        $this->categoryId = $categoryId;
        $this->participantId = $participantId;
        $this->participantName = $participantName;
        $this->time = $time;
        $this->foundItems = $foundItems;
        $this->penaltyCounts = $penaltyCounts;
        $this->penaltyScore = $penaltyScore;
        $this->totalScore = $totalScore;
        $this->judgeComment = $judgeComment;
        $this->penaltyDetails = $penaltyDetails;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getParticipantId(): ?int
    {
        return $this->participantId;
    }

    public function getParticipantName(): string
    {
        return $this->participantName;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    public function getFoundItems(): int
    {
        return $this->foundItems;
    }

    public function getPenaltyCounts(): array
    {
        return $this->penaltyCounts;
    }

    public function getPenaltyScore(): int
    {
        return $this->penaltyScore;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function getJudgeComment(): ?string
    {
        return $this->judgeComment;
    }

    public function getPenaltyDetails(): array
    {
        return $this->penaltyDetails;
    }

    public function setPenaltyDetails(array $penaltyDetails): void
    {
        $this->penaltyDetails = $penaltyDetails;
    }
}
