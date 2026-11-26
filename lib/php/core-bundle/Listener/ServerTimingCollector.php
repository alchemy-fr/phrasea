<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Listener;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Durations spent in a backend (Elasticsearch…) during the current request, sent back in the
 * Server-Timing header and in the slow request log by ServerTimingListener.
 */
final class ServerTimingCollector implements ResetInterface
{
    /**
     * @var array<string, array{dur: float, count: int, desc: ?string}>
     */
    private array $entries = [];

    /**
     * @param string|null $desc what is counted, e.g. "queries" for `desc="3 queries"` (no count without it)
     */
    public function add(string $name, float $ms, ?string $desc = null): void
    {
        $entry = $this->entries[$name] ?? ['dur' => 0.0, 'count' => 0, 'desc' => null];
        $entry['dur'] += $ms;
        ++$entry['count'];
        $entry['desc'] = $desc ?? $entry['desc'];

        $this->entries[$name] = $entry;
    }

    /**
     * @return array<string, array{dur: float, count: int, desc: ?string}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
