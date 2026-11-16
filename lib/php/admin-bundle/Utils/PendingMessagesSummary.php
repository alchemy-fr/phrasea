<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Utils;

final class PendingMessagesSummary
{
    /**
     * Kept as a string: the admin bundle does not depend on the workflow library.
     */
    public const string WORKFLOW_JOB_CONSUMER_TYPE = 'Alchemy\Workflow\Message\JobConsumer';

    private const string UNKNOWN_TYPE_LABEL = '(unknown)';

    /**
     * @var array<string, array{type: string|null, shortType: string, detail: string|null, count: int}>
     */
    private array $groups = [];
    private int $count = 0;

    public function add(?string $type, string $body): void
    {
        ++$this->count;

        $detail = self::WORKFLOW_JOB_CONSUMER_TYPE === $type ? $this->extractJobId($body) : null;
        $key = ($type ?? '').'|'.($detail ?? '');

        if (isset($this->groups[$key])) {
            ++$this->groups[$key]['count'];

            return;
        }

        $this->groups[$key] = [
            'type' => $type,
            'shortType' => null !== $type ? self::getShortType($type) : self::UNKNOWN_TYPE_LABEL,
            'detail' => $detail,
            'count' => 1,
        ];
    }

    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return list<array{type: string|null, shortType: string, detail: string|null, count: int}>
     */
    public function getGroups(): array
    {
        $groups = array_values($this->groups);
        usort($groups, static fn (array $a, array $b): int => [$b['count'], $a['type'] ?? '', $a['detail'] ?? '']
            <=> [$a['count'], $b['type'] ?? '', $b['detail'] ?? '']);

        return $groups;
    }

    private static function getShortType(string $type): string
    {
        $position = strrpos($type, '\\');

        return false === $position ? $type : substr($type, $position + 1);
    }

    private function extractJobId(string $body): ?string
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $jobId = is_array($data) ? ($data['jobId'] ?? null) : null;

        return is_string($jobId) || is_int($jobId) ? (string) $jobId : null;
    }
}
