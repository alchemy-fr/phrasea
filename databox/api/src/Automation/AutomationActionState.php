<?php

declare(strict_types=1);

namespace App\Automation;

use App\Entity\Automation\AutomationRun;
use App\Entity\Core\Asset;

/**
 * The outcome of one action over a whole run.
 *
 * A failed asset stays "new" and is retried at the next run; a skipped asset
 * (e.g. not allowed) is considered handled.
 */
final class AutomationActionState
{
    final public const string STATUS_SUCCESS = 'success';
    final public const string STATUS_PARTIAL = 'partial';
    final public const string STATUS_FAILED = 'failed';

    private int $processed = 0;
    private int $failed = 0;
    private int $skipped = 0;

    /**
     * @var list<array{assetId?: string, message: string}>
     */
    private array $errors = [];

    private ?string $error = null;

    /**
     * Assets which failed in the current batch.
     *
     * @var array<string, true>
     */
    private array $batchFailures = [];

    /**
     * Assets reported (processed, skipped or failed) in the current batch.
     *
     * @var array<string, true>
     */
    private array $batchHandled = [];

    /**
     * Free storage kept in memory for the whole run (e.g. to aggregate a report in finish()).
     */
    public array $data = [];

    public function __construct(
        public readonly string $type,
    ) {
    }

    public function beginBatch(): void
    {
        $this->batchFailures = [];
        $this->batchHandled = [];
    }

    public function markProcessed(Asset|string $asset): void
    {
        $this->batchHandled[self::getAssetId($asset)] = true;
        ++$this->processed;
    }

    public function markSkipped(Asset|string $asset, string $reason): void
    {
        $assetId = self::getAssetId($asset);
        $this->batchHandled[$assetId] = true;
        ++$this->skipped;
        $this->addError($assetId, $reason);
    }

    public function markFailed(Asset|string $asset, string $message): void
    {
        $assetId = self::getAssetId($asset);
        if (isset($this->batchFailures[$assetId])) {
            return;
        }

        $this->batchHandled[$assetId] = true;
        $this->batchFailures[$assetId] = true;
        ++$this->failed;
        $this->addError($assetId, $message);
    }

    public function isHandledInBatch(Asset|string $asset): bool
    {
        return isset($this->batchHandled[self::getAssetId($asset)]);
    }

    /**
     * An error not related to an asset (e.g. raised by finish()).
     */
    public function setError(string $message): void
    {
        $this->error = $message;
        $this->addError(null, $message);
    }

    public function isFailedInBatch(Asset|string $asset): bool
    {
        return isset($this->batchFailures[self::getAssetId($asset)]);
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatus(): string
    {
        if (null !== $this->error) {
            return self::STATUS_FAILED;
        }

        return $this->failed > 0 ? self::STATUS_PARTIAL : self::STATUS_SUCCESS;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'status' => $this->getStatus(),
            'processed' => $this->processed,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    private function addError(?string $assetId, string $message): void
    {
        if (count($this->errors) >= AutomationRun::MAX_ERRORS) {
            return;
        }

        $error = ['message' => $message];
        if (null !== $assetId) {
            $error = ['assetId' => $assetId] + $error;
        }
        $this->errors[] = $error;
    }

    private static function getAssetId(Asset|string $asset): string
    {
        return $asset instanceof Asset ? $asset->getId() : $asset;
    }
}
