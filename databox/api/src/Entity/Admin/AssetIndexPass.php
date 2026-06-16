<?php

declare(strict_types=1);

namespace App\Entity\Admin;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Util\Time;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Entity]
class AssetIndexPass extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::BIGINT, nullable: false)]
    private string $documentCount;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $progress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $estimated = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $remaining = null;

    public function getTimeTaken(): ?int
    {
        if (null === $this->endedAt) {
            return (new \DateTimeImmutable())->getTimestamp() - $this->createdAt->getTimestamp();
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

    public function getEstimated(): ?string
    {
        return $this->estimated;
    }

    public function setEstimated(?string $estimated): void
    {
        $this->estimated = $estimated;
    }

    public function getRemaining(): ?string
    {
        return $this->remaining;
    }

    public function setRemaining(?string $remaining): void
    {
        $this->remaining = $remaining;
    }

    public function getDocumentCount(): int
    {
        return (int) $this->documentCount;
    }

    public function setDocumentCount(int|string $documentCount): void
    {
        $this->documentCount = (string) $documentCount;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getProgress(): ?int
    {
        if (null === $this->progress) {
            return null;
        }

        return (int) $this->progress;
    }

    public function setProgress(int $progress): void
    {
        $this->progress = (string) $progress;
    }

    public function getProgressString(): ?string
    {
        if (null !== $this->progress && $this->documentCount > 0) {
            return sprintf('%d/%d (%d%%)', $this->getProgress(), $this->getDocumentCount(), round($this->getProgress() / $this->getDocumentCount() * 100));
        }

        return null;
    }

    public function isSuccessful(): ?bool
    {
        return null !== $this->endedAt;
    }
}
