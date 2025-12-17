<?php

namespace App\Entity\Log;

use Alchemy\TrackBundle\Entity\AbstractLog;
use App\Model\ActionLogTypeEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ActionLog extends AbstractLog
{
    #[ORM\Column(type: Types::INTEGER, nullable: false, enumType: ActionLogTypeEnum::class)]
    private ?ActionLogTypeEnum $action = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $objectType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $objectId = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $impersonatorId = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    protected array $data = [];

    public function getAction(): ActionLogTypeEnum
    {
        return $this->action;
    }

    public function setAction(ActionLogTypeEnum $action): void
    {
        $this->action = $action;
    }

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

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getImpersonatorId(): ?string
    {
        return $this->impersonatorId;
    }

    public function setImpersonatorId(?string $impersonatorId): void
    {
        $this->impersonatorId = $impersonatorId;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
