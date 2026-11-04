<?php

declare(strict_types=1);

namespace App\Entity\Automation;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Input\Automation\ResetCursorInput;
use App\Api\Model\Input\Automation\SavedSearchAutomationInput;
use App\Api\Model\Input\Automation\TriggerAutomationInput;
use App\Api\Model\Output\Automation\AutomationRunOutput;
use App\Api\Model\Output\Automation\SavedSearchAutomationOutput;
use App\Api\Processor\InputMapperProcessor;
use App\Api\Processor\ResetAutomationCursorProcessor;
use App\Api\Processor\TriggerAutomationProcessor;
use App\Api\Provider\SavedSearchAutomationCollectionProvider;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationScopeEnum;
use App\Repository\Automation\SavedSearchAutomationRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An ordered list of actions applied to the results of a saved search, on demand
 * or on a cron schedule, as the owner of the saved search.
 */
#[ApiResource(
    shortName: 'saved-search-automation',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            parameters: [
                'savedSearch' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'The saved search ID',
                    required: true,
                    castToArray: false,
                ),
            ],
            provider: SavedSearchAutomationCollectionProvider::class,
        ),
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new Post(
            extraProperties: [InputMapperProcessor::ENTITY_SECURITY => 'is_granted("'.AbstractVoter::CREATE.'", object)'],
            processor: InputMapperProcessor::class,
        ),
        new Patch(
            extraProperties: [InputMapperProcessor::ENTITY_SECURITY => 'is_granted("'.AbstractVoter::EDIT.'", object)'],
            processor: InputMapperProcessor::class,
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Post(
            uriTemplate: '/saved-search-automations/{id}/run',
            status: 201,
            throwOnNotFound: true,
            normalizationContext: [
                'groups' => [AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ],
            ],
            input: TriggerAutomationInput::class,
            output: AutomationRunOutput::class,
            name: 'run',
            processor: TriggerAutomationProcessor::class,
        ),
        new Post(
            uriTemplate: '/saved-search-automations/{id}/reset-cursor',
            status: 204,
            throwOnNotFound: true,
            input: ResetCursorInput::class,
            output: false,
            name: 'reset_cursor',
            processor: ResetAutomationCursorProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST, self::GROUP_READ],
    ],
    input: SavedSearchAutomationInput::class,
    output: SavedSearchAutomationOutput::class,
)]
#[ORM\Entity(repositoryClass: SavedSearchAutomationRepository::class)]
#[ORM\Index(columns: ['enabled', 'next_run_at'], name: 'ssa_due_idx')]
class SavedSearchAutomation extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const string GROUP_READ = 'ssa:r';
    final public const string GROUP_LIST = 'ssa:i';

    // Scheduled runs failing in a row before the automation is disabled
    final public const int MAX_CONSECUTIVE_FAILURES = 5;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: SavedSearch::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SavedSearch $savedSearch = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $enabled = true;

    /**
     * Cron expression, null for an automation run on demand only.
     */
    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $schedule = null;

    #[Assert\NotBlank]
    #[Assert\Timezone]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, options: ['default' => 'UTC'])]
    private string $timezone = 'UTC';

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false, enumType: AutomationScopeEnum::class)]
    private AutomationScopeEnum $scope = AutomationScopeEnum::New;

    /**
     * Ordered list of {type, config}, secrets of the config being encrypted.
     */
    #[Assert\Count(min: 1, minMessage: 'An automation needs at least one action.')]
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $actions = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextRunAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: AutomationRunStatusEnum::class)]
    private ?AutomationRunStatusEnum $lastRunStatus = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, options: ['default' => 0])]
    private int $consecutiveFailures = 0;

    /**
     * The user who created the automation (audit only: runs act as the saved search owner).
     */
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $createdBy = null;

    public function getSavedSearch(): ?SavedSearch
    {
        return $this->savedSearch;
    }

    public function setSavedSearch(?SavedSearch $savedSearch): void
    {
        $this->savedSearch = $savedSearch;
    }

    /**
     * The user the runs act as.
     */
    public function getOwnerId(): ?string
    {
        return $this->savedSearch?->getOwnerId();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(?string $schedule): void
    {
        $this->schedule = $schedule;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getScope(): AutomationScopeEnum
    {
        return $this->scope;
    }

    public function setScope(AutomationScopeEnum $scope): void
    {
        $this->scope = $scope;
    }

    /**
     * @return list<array{type: string, config: array}>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @param list<array{type: string, config: array}> $actions
     */
    public function setActions(array $actions): void
    {
        $this->actions = $actions;
    }

    public function getNextRunAt(): ?\DateTimeImmutable
    {
        return $this->nextRunAt;
    }

    public function setNextRunAt(?\DateTimeImmutable $nextRunAt): void
    {
        $this->nextRunAt = $nextRunAt;
    }

    public function getLastRunAt(): ?\DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): void
    {
        $this->lastRunAt = $lastRunAt;
    }

    public function getLastRunStatus(): ?AutomationRunStatusEnum
    {
        return $this->lastRunStatus;
    }

    public function setLastRunStatus(?AutomationRunStatusEnum $lastRunStatus): void
    {
        $this->lastRunStatus = $lastRunStatus;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function setConsecutiveFailures(int $consecutiveFailures): void
    {
        $this->consecutiveFailures = $consecutiveFailures;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->getId();
    }
}
