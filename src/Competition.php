<?php

namespace NoseworkV2;

class Competition
{
    private ?int $id;
    private string $name;
    private string $description;
    private bool $isPublished;
    private ?string $startDate;
    private ?string $endDate;

    public function __construct(
        string $name,
        string $description = '',
        ?int $id = null,
        bool $isPublished = false,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->isPublished = $isPublished;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): void
    {
        $this->isPublished = $isPublished;
    }

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function setStartDate(?string $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    public function setEndDate(?string $endDate): void
    {
        $this->endDate = $endDate;
    }

    /**
     * Проверяет, находится ли текущая дата в диапазоне дат соревнования (включая границы)
     */
    public function isActive(): bool
    {
        if ($this->startDate === null || $this->endDate === null) {
            return false;
        }

        $today = date('Y-m-d');
        return $today >= $this->startDate && $today <= $this->endDate;
    }
}