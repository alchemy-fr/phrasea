<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

/**
 * A key figure of the admin home page: a total and the number created
 * during the last 24 hours, 7 days and 30 days.
 */
final readonly class HomeStat
{
    public const string FORMAT_NUMBER = 'number';
    public const string FORMAT_BYTES = 'bytes';

    public function __construct(
        public string $label,
        public string $icon,
        public ?int $total = null,
        public ?int $last24h = null,
        public ?int $last7d = null,
        public ?int $last30d = null,
        public ?string $url = null,
        public ?string $error = null,
        public string $format = self::FORMAT_NUMBER,
    ) {
    }

    /**
     * @param array{total: int, last24h?: ?int, last7d?: ?int, last30d?: ?int} $counts
     */
    public static function fromCounts(string $label, string $icon, array $counts, ?string $url = null, string $format = self::FORMAT_NUMBER): self
    {
        return new self(
            label: $label,
            icon: $icon,
            total: $counts['total'],
            last24h: $counts['last24h'] ?? null,
            last7d: $counts['last7d'] ?? null,
            last30d: $counts['last30d'] ?? null,
            url: $url,
            format: $format,
        );
    }

    public static function failed(string $label, string $icon, \Throwable $error, ?string $url = null): self
    {
        return new self(
            label: $label,
            icon: $icon,
            url: $url,
            error: $error->getMessage(),
        );
    }

    public function hasPeriods(): bool
    {
        return null !== $this->last24h || null !== $this->last7d || null !== $this->last30d;
    }
}
