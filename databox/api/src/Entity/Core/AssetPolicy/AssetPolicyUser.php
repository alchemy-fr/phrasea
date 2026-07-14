<?php

declare(strict_types=1);

namespace App\Entity\Core\AssetPolicy;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

#[ORM\Table]
#[ORM\Entity]
#[ORM\Index(columns: ['user_type', 'user_id'], name: 'apu_user_idx')]
class AssetPolicyUser extends AbstractUuidEntity
{
    final public const int TYPE_USER = 0;
    final public const int TYPE_GROUP = 1;

    #[Column(type: Types::SMALLINT)]
    protected ?int $userType = null;

    #[Column(type: Types::STRING, length: 36, nullable: true)]
    protected ?string $userId = null;

    #[ORM\ManyToOne(targetEntity: AssetPolicy::class, inversedBy: 'users')]
    private ?AssetPolicy $policy = null;

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

    public function getPolicy(): ?AssetPolicy
    {
        return $this->policy;
    }

    public function setPolicy(?AssetPolicy $policy): void
    {
        $this->policy = $policy;
    }
}
