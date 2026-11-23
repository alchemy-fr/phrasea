<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Outcome of a batch action on quarantined assets, asset by asset.
 */
final readonly class QuarantineBatchOutput
{
    final public const string STATUS_PROCESSED = 'processed';
    final public const string STATUS_SKIPPED = 'skipped';
    final public const string STATUS_FAILED = 'failed';

    final public const string REASON_ERROR = 'error';

    #[Groups(['_'])]
    public int $processed;

    #[Groups(['_'])]
    public int $skipped;

    #[Groups(['_'])]
    public int $failed;

    public function __construct(
        /**
         * A skipped asset was no longer eligible when the action ran (its
         * `reason` is one of the eligibility reasons, or `target_required` /
         * `invalid_target` for a merge); a failed one raised an error.
         *
         * @var list<array{id: string, status: string, reason?: string}>
         */
        #[Groups(['_'])]
        public array $results,
    ) {
        $count = static fn (string $status): int => count(array_filter($results, static fn (array $r): bool => $status === $r['status']));
        $this->processed = $count(self::STATUS_PROCESSED);
        $this->skipped = $count(self::STATUS_SKIPPED);
        $this->failed = $count(self::STATUS_FAILED);
    }
}
