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
#[ORM\Index(columns: ['task'], name: 'admin_task_task_idx')]
class AdminTask extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $task = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $payload = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $progress = null;

    // Number of item to process
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $itemTotal = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $estimated = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $remaining = null;

    public function getTask(): ?string
    {
        return $this->task;
    }

    public function setTask(?string $task): void
    {
        $this->task = $task;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getTimeTaken(): ?int
    {
        if (null === $this->endedAt) {
            return new \DateTimeImmutable()->getTimestamp() - $this->createdAt->getTimestamp();
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

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getProgressString(): ?string
    {
        if (null !== $this->progress && $this->itemTotal > 0) {
            return sprintf('%d/%d (%d%%)', $this->progress, $this->itemTotal, round($this->progress / $this->itemTotal * 100));
        }

        return null;
    }

    public function isSuccessful(): ?bool
    {
        return null !== $this->endedAt;
    }

    public function getProgress(): string
    {
        return $this->progress;
    }

    public function setProgress(string $progress): void
    {
        $this->progress = $progress;
    }

    public function getItemTotal(): string
    {
        return $this->itemTotal;
    }

    public function setItemTotal(string $itemTotal): void
    {
        $this->itemTotal = $itemTotal;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(?string $output): void
    {
        $this->output = $output;
    }
}
