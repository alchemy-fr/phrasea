<?php

declare(strict_types=1);

namespace App\Entity\Admin;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Util\Time;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class PopulatePass extends AbstractUuidEntity
{
    use CreatedAtTrait;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected ?DateTimeInterface $endedAt = null;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $documentCount;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private string $indexName;

    /**
     * @ORM\Column(type="json", nullable=false)
     */
    private array $mapping;

    public function getTimeTaken(): ?int
    {
        if (null === $this->endedAt) {
            return null;
        }

        return $this->endedAt->getTimestamp() - $this->createdAt->getTimestamp();
    }

    public function getTimeTakenUnit(): ?string
    {
        $took = $this->getTimeTaken();

        if (null === $took) {
            return null;
        }

        return Time::time2string($took);
    }

    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }

    public function setDocumentCount(int $documentCount): void
    {
        $this->documentCount = $documentCount;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    public function getEndedAt(): ?DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?DateTimeInterface $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }
}
