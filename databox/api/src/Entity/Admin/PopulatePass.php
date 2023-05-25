<?php

declare(strict_types=1);

namespace App\Entity\Admin;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Util\Time;
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
    protected ?\DateTimeInterface $endedAt = null;

    /**
     * @ORM\Column(type="bigint", nullable=false)
     */
    private int $documentCount;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private ?int $progress = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private string $indexName;

    /**
     * @ORM\Column(type="json", nullable=false)
     */
    private array $mapping;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $error = null;

    public function getTimeTaken(): ?int
    {
        if (null === $this->endedAt) {
            if (null === $this->error) {
                return (new \DateTimeImmutable())->getTimestamp() - $this->createdAt->getTimestamp();
            }

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

    public function getEndedAt(): ?\DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeInterface $endedAt): void
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

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): void
    {
        $this->progress = $progress;
    }

    public function getProgressString(): ?string
    {
        if (null !== $this->progress && $this->documentCount > 0) {
            return sprintf('%d/%d (%d%%)', $this->progress, $this->documentCount, round($this->progress / $this->documentCount * 100));
        }

        return null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    public function isSuccessful(): ?bool
    {
        if (null === $this->endedAt) {
            return null;
        }

        return null === $this->error;
    }
}
