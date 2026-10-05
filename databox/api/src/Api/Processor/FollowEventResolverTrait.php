<?php

declare(strict_types=1);

namespace App\Api\Processor;

use App\Entity\FollowableInterface;

trait FollowEventResolverTrait
{
    /**
     * Resolves the events to (un)follow: all of them, or the single one
     * requested through the input `key`.
     *
     * @return array<int, string>
     */
    private function resolveEvents(FollowableInterface $object, ?string $key): array
    {
        $events = $object->getFollowEvents();

        if (null === $key) {
            return $events;
        }

        if (!in_array($key, $events, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid follow event "%s"', $key));
        }

        return [$key];
    }
}
