<?php

namespace Alchemy\CoreBundle\Message\Debug;

final readonly class SentryDebug
{
    public function __construct(
        private string $id,
        private array $extra = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }
}
