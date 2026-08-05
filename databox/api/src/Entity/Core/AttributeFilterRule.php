<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AttributeFilterRuleInput;
use App\Api\Model\Output\AttributeFilterRuleOutput;
use App\Api\Provider\AttributeFilterRuleCollectionProvider;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeFilterRuleRepository;
use App\Validator\ValidAQLConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'attribute-filter-rule',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: ['groups' => ['_',
        AttributeFilterRule::GROUP_READ],
    ],
    input: AttributeFilterRuleInput::class,
    output: AttributeFilterRuleOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: AttributeFilterRuleCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\Entity(repositoryClass: AttributeFilterRuleRepository::class)]
class AttributeFilterRule extends AbstractUuidEntity implements LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const int OBJECT_INDEX = 21;

    final public const string GROUP_READ = 'afr:read';
    final public const string GROUP_LIST = 'afr:index';

    /**
     * A rule without any target applies to everyone.
     *
     * @var DoctrineCollection<int, AttributeFilterRuleTarget>
     */
    #[ORM\OneToMany(mappedBy: 'rule', targetEntity: AttributeFilterRuleTarget::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected ?DoctrineCollection $targets = null;

    #[ORM\Column(type: Types::TEXT)]
    #[ValidAQLConstraint]
    protected ?string $condition = null;

    public function __construct()
    {
        parent::__construct();

        $this->targets = new ArrayCollection();
    }

    /**
     * @return DoctrineCollection<int, AttributeFilterRuleTarget>
     */
    public function getTargets(): DoctrineCollection
    {
        return $this->targets;
    }

    public function addTarget(int $userType, string $userId): void
    {
        foreach ($this->targets as $target) {
            if ($target->getUserType() === $userType && $target->getUserId() === $userId) {
                return;
            }
        }

        $target = new AttributeFilterRuleTarget();
        $target->setRule($this);
        $target->setUserType($userType);
        $target->setUserId($userId);
        $this->targets->add($target);
    }

    public function setTargets(array $userIds, array $groupIds): void
    {
        foreach ($this->targets as $target) {
            $list = AttributeFilterRuleTarget::TYPE_GROUP === $target->getUserType() ? $groupIds : $userIds;
            if (!in_array($target->getUserId(), $list, true)) {
                $this->targets->removeElement($target);
            }
        }

        foreach ($userIds as $userId) {
            $this->addTarget(AttributeFilterRuleTarget::TYPE_USER, $userId);
        }
        foreach ($groupIds as $groupId) {
            $this->addTarget(AttributeFilterRuleTarget::TYPE_GROUP, $groupId);
        }
    }

    /**
     * @return string[]
     */
    public function getUserIds(): array
    {
        return $this->getTargetIds(AttributeFilterRuleTarget::TYPE_USER);
    }

    /**
     * @return string[]
     */
    public function getGroupIds(): array
    {
        return $this->getTargetIds(AttributeFilterRuleTarget::TYPE_GROUP);
    }

    private function getTargetIds(int $userType): array
    {
        return $this->targets
            ->filter(fn (AttributeFilterRuleTarget $t): bool => $t->getUserType() === $userType)
            ->map(fn (AttributeFilterRuleTarget $t): string => $t->getUserId())
            ->getValues();
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): void
    {
        $this->condition = $condition;
    }
}
