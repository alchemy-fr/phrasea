<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

/**
 * A table of the latest objects of a type on the admin home page.
 */
final readonly class HomeActivityBlock
{
    /**
     * @param list<HomeActivityItem> $items
     */
    public function __construct(
        public string $title,
        public string $icon,
        public array $items = [],
        public ?string $seeAllUrl = null,
        public ?string $error = null,
    ) {
    }

    public static function failed(string $title, string $icon, \Throwable $error, ?string $seeAllUrl = null): self
    {
        return new self(
            title: $title,
            icon: $icon,
            seeAllUrl: $seeAllUrl,
            error: $error->getMessage(),
        );
    }
}
