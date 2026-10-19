<?php

declare(strict_types=1);

namespace App\Entity\Automation;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Model\Output\Automation\AutomationRunOutput;
use App\Api\Provider\AutomationRunCollectionProvider;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationTriggerEnum;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'automation-run',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new GetCollection(
            parameters: [
                'automation' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'automation',
                    schema: ['type' => 'string'],
                    description: 'The automation ID',
                    required: true,
                    castToArray: false,
                ),
            ],
            provider: AutomationRunCollectionProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST, self::GROUP_READ],
    ],
    output: AutomationRunOutput::class,
    order: ['createdAt' => 'DESC'],
)]
#[ORM\Entity]
#[ORM\Index(columns: ['automation_id', 'created_at'], name: 'automation_run_list_idx')]
class AutomationRun extends AbstractUuidEntity
{
    use CreatedAtTrait;

    final public const string GROUP_READ = 'arun:r';
    final public const string GROUP_LIST = 'arun:i';

    // Errors kept per action
    final public const int MAX_ERRORS = 100;

    #[ORM\ManyToOne(targetEntity: SavedSearchAutomation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SavedSearchAutomation $automation = null;

    #[ORM\Column(name: 'trigger_type', type: Types::STRING, length: 20, nullable: false, enumType: AutomationTriggerEnum::class)]
    private AutomationTriggerEnum $trigger = AutomationTriggerEnum::Manual;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $dryRun = false;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $initiatorId = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, enumType: AutomationRunStatusEnum::class)]
    private AutomationRunStatusEnum $status = AutomationRunStatusEnum::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    // Results of the saved search the owner may read
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $matchedCount = 0;

    // Results not handled by a previous run
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $newCount = 0;

    // Assets every action succeeded on
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $processedCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $failedCount = 0;

    /**
     * Per action: {type, status, processed, failed, skipped, errors: [{assetId?, message}]}.
     */
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $actionResults = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    public function getAutomation(): ?SavedSearchAutomation
    {
        return $this->automation;
    }

    public function setAutomation(?SavedSearchAutomation $automation): void
    {
        $this->automation = $automation;
    }

    public function getTrigger(): AutomationTriggerEnum
    {
        return $this->trigger;
    }

    public function setTrigger(AutomationTriggerEnum $trigger): void
    {
        $this->trigger = $trigger;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function getInitiatorId(): ?string
    {
        return $this->initiatorId;
    }

    public function setInitiatorId(?string $initiatorId): void
    {
        $this->initiatorId = $initiatorId;
    }

    public function getStatus(): AutomationRunStatusEnum
    {
        return $this->status;
    }

    public function setStatus(AutomationRunStatusEnum $status): void
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

    public function getMatchedCount(): int
    {
        return $this->matchedCount;
    }

    public function setMatchedCount(int $matchedCount): void
    {
        $this->matchedCount = $matchedCount;
    }

    public function getNewCount(): int
    {
        return $this->newCount;
    }

    public function setNewCount(int $newCount): void
    {
        $this->newCount = $newCount;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function setProcessedCount(int $processedCount): void
    {
        $this->processedCount = $processedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): void
    {
        $this->failedCount = $failedCount;
    }

    public function getActionResults(): array
    {
        return $this->actionResults;
    }

    public function setActionResults(array $actionResults): void
    {
        $this->actionResults = $actionResults;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }
}
