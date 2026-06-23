<?php

declare(strict_types=1);

namespace App\Entity\Admin;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Api\Model\Input\OperationTaskInput;
use App\Api\Model\Output\UserOutput;
use App\Api\Processor\RunOperationTaskProcessor;
use App\Entity\Traits\OwnerIdTrait;
use App\Util\Time;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'operation-task',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            input: OperationTaskInput::class,
            processor: RunOperationTaskProcessor::class
        ),
    ],
    order: ['createdAt' => 'DESC'],
    security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")',
)]
#[ORM\Table]
#[ORM\Entity]
#[ORM\Index(columns: ['task'], name: 'operation_task_idx')]
#[ORM\Index(columns: ['status'], name: 'operation_status_idx')]
class OperationTask extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use OwnerIdTrait;

    final public const int STATUS_PENDING = 0;
    final public const int STATUS_IN_PROGRESS = 1;
    final public const int STATUS_COMPLETED = 2;
    final public const int STATUS_FAILED = 3;
    final public const int STATUS_CANCELLED = 4;

    public const array STATUS_CHOICES = [
        'Pending' => self::STATUS_PENDING,
        'In Progress' => self::STATUS_IN_PROGRESS,
        'Completed' => self::STATUS_COMPLETED,
        'Failed' => self::STATUS_FAILED,
        'Cancelled' => self::STATUS_CANCELLED,
    ];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $task = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $payload = [];

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private ?int $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

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

    public ?UserOutput $owner = null;

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
        if (null === $this->startedAt) {
            return null;
        }

        if (null === $this->endedAt) {
            return new \DateTimeImmutable()->getTimestamp() - $this->startedAt->getTimestamp();
        }

        return $this->endedAt->getTimestamp() - $this->startedAt->getTimestamp();
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

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
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

    public function getProgress(): string
    {
        return $this->progress;
    }

    public function setProgress(string $progress): void
    {
        $this->progress = $progress;
    }

    #[ApiProperty(
        description: 'Progression of the task in percentage',
        readable: true,
        writable: false,
    )]
    public function getProgression(): ?int
    {
        if (null === $this->progress || $this->itemTotal <= 0) {
            return null;
        }

        return (int) round($this->progress / $this->itemTotal * 100);
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

    public function appendOutput(string $output): void
    {
        $this->output ??= '';
        $this->output .= $output;
    }
}
