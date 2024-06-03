<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\MappedSuperclass]
abstract class AbstractIntegrationData extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const GROUP_READ = 'int-data:read';
    final public const GROUP_LIST = 'int-data:index';
    final public const GROUP_WRITE = 'int-data:w';

    #[ORM\ManyToOne(targetEntity: WorkspaceIntegration::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkspaceIntegration $integration = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $keyId = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $userId = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private $value;

    public function getIntegration(): ?WorkspaceIntegration
    {
        return $this->integration;
    }

    public function setIntegration(?WorkspaceIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getKeyId(): ?string
    {
        return $this->keyId;
    }

    public function setKeyId(?string $keyId): void
    {
        $this->keyId = $keyId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }


    abstract public function setObject(object $object): void;
    abstract public function getObject(): ?object;
}
