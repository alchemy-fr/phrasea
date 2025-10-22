<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\WorkspaceIntegrationInput;
use App\Api\Model\Output\WorkspaceIntegrationOutput;
use App\Api\Provider\WorkspaceIntegrationCollectionProvider;
use App\Entity\Core\Workspace;
use App\Entity\Traits\ErrorDisableInterface;
use App\Entity\Traits\ErrorDisableTrait;
use App\Entity\Traits\NullableWorkspaceTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\WithOwnerIdInterface;
use App\Integration\Exception\CircularReferenceException;
use App\Security\Voter\AbstractVoter;
use App\Validator\ValidIntegrationOptionsConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[ApiResource(
    shortName: 'integration',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)',
            validationContext: ['Default', 'create'],
        ),
    ],
    normalizationContext: [
        'groups' => [WorkspaceIntegration::GROUP_LIST],
    ],
    input: WorkspaceIntegrationInput::class,
    output: WorkspaceIntegrationOutput::class,
    provider: WorkspaceIntegrationCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_integration_key', columns: ['workspace_id', 'title', 'integration'])]
#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['workspace' => 'exact'])]
#[ValidIntegrationOptionsConstraint]
class WorkspaceIntegration extends AbstractUuidEntity implements \Stringable, ErrorDisableInterface, WithOwnerIdInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use OwnerIdTrait;
    use NullableWorkspaceTrait;
    use ErrorDisableTrait;

    final public const string GROUP_READ = 'wi:read';
    final public const string GROUP_LIST = 'wi:index';

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Workspace $workspace = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[NotNull]
    private ?string $integration = null;

    #[ORM\ManyToMany(targetEntity: WorkspaceIntegration::class)]
    private ?Collection $needs = null;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $if = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $enabled = true;

    #[ApiProperty(security: 'is_granted("'.AbstractVoter::EDIT.'", object.workspace)')]
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $config = [];

    #[ORM\OneToMany(mappedBy: 'integration', targetEntity: IntegrationData::class, cascade: ['remove'])]
    private ?Collection $data = null;

    private ?string $optionsJson = null;
    private ?string $optionsYaml = null;

    public function __construct()
    {
        parent::__construct();
        $this->needs = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getIntegration(): ?string
    {
        return $this->integration;
    }

    public function setIntegration(?string $integration): void
    {
        $this->integration = $integration;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getConfigYaml(): string
    {
        if (null !== $this->optionsYaml) {
            return $this->optionsYaml;
        }

        return Yaml::dump($this->config, 10);
    }

    public function setConfigYaml(string $options): void
    {
        $this->optionsYaml = $options;
        try {
            $this->config = Yaml::parse($options);
        } catch (ParseException) {
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        if (!$this->enabled && $enabled) {
            $this->clearErrors();
        }

        $this->enabled = $enabled;
    }

    public function getThis(): self
    {
        return $this;
    }

    /**
     * @return WorkspaceIntegration[]
     */
    public function getNeeds(): Collection
    {
        return $this->needs;
    }

    public function addNeed(WorkspaceIntegration $need): void
    {
        $this->needs->add($need);
    }

    public function removeNeed(WorkspaceIntegration $need): void
    {
        if ($this->needs->contains($need)) {
            $this->needs->removeElement($need);
        }
    }

    #[Assert\Callback]
    public function validateNeeds(ExecutionContextInterface $context, $payload): void
    {
        foreach ($this->needs as $need) {
            if ($need === $this) {
                $context
                    ->buildViolation('Cannot reference itself')
                    ->atPath('needs')
                    ->addViolation();

                return;
            }
        }

        try {
            $this->detectCircularNeed([]);
        } catch (CircularReferenceException) {
            $context
                ->buildViolation('Circular Needs detected')
                ->atPath('needs')
                ->addViolation();
        }
    }

    public function detectCircularNeed(array $branch): void
    {
        foreach ($this->needs as $need) {
            if (in_array($need->getId(), $branch, true)) {
                throw new CircularReferenceException();
            }
            $b = $branch;
            $b[] = $need->getId();
            $need->detectCircularNeed($b);
        }
    }

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function setIf(?string $if): void
    {
        $this->if = $if;
    }

    public function disableAfterErrors(): void
    {
        $this->enabled = false;
    }

    public function __toString(): string
    {
        if ($this->workspace) {
            return sprintf('%s - %s', $this->workspace->getName(), $this->getIntegration());
        }

        return $this->getIntegration();
    }
}
