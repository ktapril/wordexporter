<?php

namespace NoseworkV2;

class Participant
{
    private ?int $id;
    private string $name;
    private ?string $breed;
    private ?string $nickname;
    private ?string $gender;
    private ?string $birthDate;
    private ?string $microchipNumber;
    private ?string $pedigreeNumber;
    private ?string $qualificationBookNumber;
    private ?string $instructorName;
    private int $sortOrder;

    public function __construct(
        string $name,
        ?string $breed = null,
        ?string $nickname = null,
        ?string $gender = null,
        ?string $birthDate = null,
        ?string $microchipNumber = null,
        ?string $pedigreeNumber = null,
        ?string $qualificationBookNumber = null,
        ?string $instructorName = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->breed = $breed;
        $this->nickname = $nickname;
        $this->gender = $gender;
        $this->birthDate = $birthDate;
        $this->microchipNumber = $microchipNumber;
        $this->pedigreeNumber = $pedigreeNumber;
        $this->qualificationBookNumber = $qualificationBookNumber;
        $this->instructorName = $instructorName;
        $this->sortOrder = 0;
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

    public function getBreed(): ?string
    {
        return $this->breed;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function getMicrochipNumber(): ?string
    {
        return $this->microchipNumber;
    }

    public function getPedigreeNumber(): ?string
    {
        return $this->pedigreeNumber;
    }

    public function getQualificationBookNumber(): ?string
    {
        return $this->qualificationBookNumber;
    }

    public function getInstructorName(): ?string
    {
        return $this->instructorName;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }
}
