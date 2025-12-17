<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle\Entity;

use Alchemy\TrackBundle\Model\TrackActionTypeEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractChangeLog extends AbstractLog
{
    #[ORM\Column(type: Types::SMALLINT, nullable: false, enumType: TrackActionTypeEnum::class)]
    private ?TrackActionTypeEnum $action = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $objectType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $objectId = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    protected array $changes = [];

    public function getObjectType(): ?int
    {
        return $this->objectType;
    }

    public function setObjectType(?int $objectType): void
    {
        $this->objectType = $objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(?string $objectId): void
    {
        $this->objectId = $objectId;
    }

    public function getAction(): ?TrackActionTypeEnum
    {
        return $this->action;
    }

    public function setAction(?TrackActionTypeEnum $action): void
    {
        $this->action = $action;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function setChanges(array $changes): void
    {
        foreach ($changes as $field => $fChanges) {
            foreach ($fChanges as $i => $c) {
                if (is_object($c)) {
                    if ($c instanceof \DateTimeInterface) {
                        $changes[$field][$i] = $c->format(\DateTimeInterface::ATOM);
                    } elseif (method_exists($c, 'getId')) {
                        $changes[$field][$i] = $c->getId();
                    } else {
                        $changes[$field][$i] = $c::class;
                    }
                }
            }
        }
        $this->changes = $changes;
    }
}
