<?php

namespace Alchemy\NotifierBundle\Model;

final readonly class TopicDto
{
    public function __construct(
        public string $topic,
        public array $params = [],
    ) {
    }
}
