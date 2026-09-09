<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Index(columns: ['user_type', 'user_id'], name: 'afrt_user_idx')]
#[ORM\UniqueConstraint(name: 'afrt_uniq_target', columns: ['rule_id', 'user_type', 'user_id'])]
#[ORM\Entity]
class AttributeFilterRuleTarget extends AbstractUuidEntity
{
    final public const int TYPE_USER = 0;
    final public const int TYPE_GROUP = 1;

    #[ORM\ManyToOne(targetEntity: AttributeFilterRule::class, inversedBy: 'targets')]
    #[ORM\JoinColumn(name: 'rule_id', nullable: false, onDelete: 'CASCADE')]
    protected ?AttributeFilterRule $rule = null;

    #[ORM\Column(type: Types::SMALLINT)]
    protected ?int $userType = null;

    #[ORM\Column(type: Types::STRING, length: 36)]
    protected ?string $userId = null;

    public function getRule(): ?AttributeFilterRule
    {
        return $this->rule;
    }

    public function setRule(?AttributeFilterRule $rule): void
    {
        $this->rule = $rule;
    }

    public function getUserType(): ?int
    {
        return $this->userType;
    }

    public function setUserType(?int $userType): void
    {
        $this->userType = $userType;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function __toString(): string
    {
        return sprintf('%s:%s', self::TYPE_GROUP === $this->userType ? 'group' : 'user', $this->userId);
    }
}
