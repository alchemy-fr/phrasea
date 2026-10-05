<?php

namespace Alchemy\NotifierBundle\Model;

final readonly class NotifyOptions
{
    public function __construct(
        public ?string $excludeUserId = null,
    ) {
    }
}
