<?php

declare(strict_types=1);

namespace App\Entity\Core\AssetPolicy;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Table]
#[ORM\Entity]
class AssetPolicyDependency extends AbstractUuidEntity
{
    #[ORM\ManyToOne(targetEntity: AssetPolicy::class, inversedBy: 'dependencies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AssetPolicy $policy = null;

    #[Column(type: UuidType::NAME, nullable: false)]
    #[ObjectReference(keyLength: 15)]
    private \Closure|AbstractUuidEntity|null $object = null;
    private ?string $objectType = null;
    private UuidInterface|string|null $objectId = null;

    public function getPolicy(): ?AssetPolicy
    {
        return $this->policy;
    }

    public function setPolicy(?AssetPolicy $policy): void
    {
        $this->policy = $policy;
    }

    public function getObject(): ?AbstractUuidEntity
    {
        if ($this->object instanceof \Closure) {
            $this->object = $this->object->call($this);
        }

        return $this->object;
    }

    public function setObject(?AbstractUuidEntity $object): void
    {
        $this->object = $object;
    }

    public function getObjectType(): ?string
    {
        return $this->objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }
}
