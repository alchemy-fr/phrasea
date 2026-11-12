<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * Measures the memory of the populate page by page, so that an out-of-memory error can be
 * traced back to its page: the page being indexed is kept in the Sentry scope (the error
 * of a dying process is reported with it) and on the PopulatePass (see PopulatePassListener).
 *
 * The peak of a page includes its hydration: it is reset before the page gets fetched.
 *
 * @phpstan-type PageStats array{
 *     memoryLimit: int|null,
 *     perPage: int|null,
 *     page: int|null,
 *     pageIds: list<string>,
 *     memoryAtPageStart: int|null,
 *     lastPageMemoryAfterGc: int|null,
 *     lastPagePeak: int|null,
 *     maxPagePeak: int,
 *     maxPagePeakPage: int|null,
 * }
 */
final class PopulatePageTracker
{
    public const string SENTRY_CONTEXT = 'es_populate';
    public const string SENTRY_TAG = 'es_populate.index';

    /**
     * @var PageStats
     */
    private array $stats;

    private ?string $index = null;

    public function __construct(
        private readonly HubInterface $hub,
    ) {
        $this->stats = self::createStats();
    }

    public function resetPeak(): void
    {
        memory_reset_peak_usage();
    }

    /**
     * @param list<string> $ids
     *
     * @return PageStats
     */
    public function pageStarted(string $index, int $page, int $perPage, array $ids): array
    {
        if ($index !== $this->index) {
            $this->index = $index;
            $this->stats = self::createStats();
        }
        $this->stats['memoryLimit'] = self::getMemoryLimit();
        $this->stats['perPage'] = $perPage;
        $this->stats['page'] = $page;
        $this->stats['pageIds'] = $ids;
        $this->stats['memoryAtPageStart'] = memory_get_usage(true);

        $context = ['index' => $index] + $this->stats;
        $this->hub->configureScope(static function (Scope $scope) use ($index, $context): void {
            $scope->setContext(self::SENTRY_CONTEXT, $context);
            $scope->setTag(self::SENTRY_TAG, $index);
        });

        return $this->stats;
    }

    /**
     * To be called once the page is released (entity manager cleared, cycles collected).
     *
     * @return PageStats
     */
    public function pageEnded(): array
    {
        $this->stats['lastPageMemoryAfterGc'] = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $this->stats['lastPagePeak'] = $peak;
        if ($peak > $this->stats['maxPagePeak']) {
            $this->stats['maxPagePeak'] = $peak;
            $this->stats['maxPagePeakPage'] = $this->stats['page'];
        }

        return $this->stats;
    }

    /**
     * @return PageStats
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Forgets the populated index: its pages must not be reported with the errors that come next.
     */
    public function finish(string $index): void
    {
        if (null !== $this->index && $index !== $this->index) {
            return;
        }

        $this->index = null;
        $this->stats = self::createStats();
        $this->hub->configureScope(static function (Scope $scope): void {
            $scope->removeContext(self::SENTRY_CONTEXT);
            $scope->removeTag(self::SENTRY_TAG);
        });
    }

    /**
     * @return int|null in bytes, null when unlimited
     */
    public static function getMemoryLimit(): ?int
    {
        $limit = ini_get('memory_limit');
        if (false === $limit || '' === $limit) {
            return null;
        }

        $bytes = ini_parse_quantity($limit);

        return $bytes > 0 ? $bytes : null;
    }

    /**
     * @return PageStats
     */
    private static function createStats(): array
    {
        return [
            'memoryLimit' => self::getMemoryLimit(),
            'perPage' => null,
            'page' => null,
            'pageIds' => [],
            'memoryAtPageStart' => null,
            'lastPageMemoryAfterGc' => null,
            'lastPagePeak' => null,
            'maxPagePeak' => 0,
            'maxPagePeakPage' => null,
        ];
    }
}
