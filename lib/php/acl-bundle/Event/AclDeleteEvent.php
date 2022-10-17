<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class AclDeleteEvent extends Event
{
    const NAME = 'acl.delete';
    private string $objectType;
    private ?string $objectId;

    public function __construct(string $objectType, ?string $objectId)
    {
        $this->objectType = $objectType;
        $this->objectId = $objectId;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function setObjectType(string $objectType): void
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
}
