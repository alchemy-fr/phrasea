<?php

declare(strict_types=1);

namespace App\Entity\Core\AssetPolicy;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AssetPolicyInput;
use App\Api\Model\Output\AssetPolicyOutput;
use App\Api\Provider\AssetPolicyCollectionProvider;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Listener\OwnerPersistableInterface;
use App\Repository\Core\AssetPolicyRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Ramsey\Uuid\UuidInterface;

#[ApiResource(
    shortName: 'asset-policy',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new Post(securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)', validationContext: [
            'groups' => ['Default', 'create'],
        ]),
        new Put(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new GetCollection(
            normalizationContext: [
                'groups' => [
                    AssetPolicy::GROUP_LIST,
                ],
            ], ),
    ],
    normalizationContext: [
        'groups' => [
            AssetPolicy::GROUP_READ,
            AssetPolicy::GROUP_LIST,
        ],
    ],
    input: AssetPolicyInput::class,
    output: AssetPolicyOutput::class,
    provider: AssetPolicyCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\Entity(repositoryClass: AssetPolicyRepository::class)]
class AssetPolicy extends AbstractUuidEntity implements OwnerPersistableInterface
{
    use CreatedAtTrait;
    use WorkspaceTrait;
    use OwnerIdTrait;

    private const string GROUP_PREFIX = 'ap:';
    final public const string GROUP_READ = self::GROUP_PREFIX.'r';
    final public const string GROUP_WRITE = self::GROUP_PREFIX.'w';
    final public const string GROUP_LIST = self::GROUP_PREFIX.'i';

    #[Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $name = null;

    #[Column(type: Types::BOOLEAN, nullable: false)]
    private bool $enabled = true;

    #[Column(type: Types::INTEGER, nullable: false)]
    private int $priority = 0;

    #[Column(type: Types::JSON, nullable: false)]
    private array $conditions = [];

    #[Column(type: Types::JSON, nullable: false)]
    private array $actions = [];

    #[ORM\OneToMany(mappedBy: 'policy', targetEntity: AssetPolicyUser::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Collection $users = null;

    #[ORM\OneToMany(mappedBy: 'policy', targetEntity: AssetPolicyDependency::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Collection $dependencies = null;

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->users = new ArrayCollection();
        $this->dependencies = new ArrayCollection();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function setActions(array $actions): void
    {
        $this->actions = $actions;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getUserIdsOfType(int $type): array
    {
        return $this->users
            ->filter(static fn (AssetPolicyUser $policyUser): bool => $policyUser->getUserType() === $type)
            ->map(fn (AssetPolicyUser $policyUser) => $policyUser->getUserId())
            ->getValues();
    }

    public function getDependencies(): ?Collection
    {
        return $this->dependencies;
    }

    public function setDependencies(?Collection $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getUserIds(): array
    {
        return $this->getUserIdsOfType(AssetPolicyUser::TYPE_USER);
    }

    public function getGroupIds(): array
    {
        return $this->getUserIdsOfType(AssetPolicyUser::TYPE_GROUP);
    }
}
