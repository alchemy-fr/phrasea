<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

final readonly class HomeActivityItem
{
    public const string VARIANT_SUCCESS = 'success';
    public const string VARIANT_DANGER = 'danger';
    public const string VARIANT_WARNING = 'warning';
    public const string VARIANT_INFO = 'info';
    public const string VARIANT_SECONDARY = 'secondary';

    /**
     * @param ?string $userName a name already known (e.g. stored with the object), which takes
     *                          precedence over the one resolved from $userId
     */
    public function __construct(
        public \DateTimeInterface $date,
        public string $label,
        public ?string $detail = null,
        public ?string $url = null,
        public ?string $userId = null,
        public ?string $userName = null,
        public ?string $status = null,
        public ?string $statusVariant = null,
    ) {
    }
}
