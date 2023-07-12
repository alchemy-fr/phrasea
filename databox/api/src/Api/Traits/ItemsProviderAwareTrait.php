<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\State\ProviderInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait ItemsProviderAwareTrait
{
    protected ProviderInterface $itemsProvider;

    #[Required]
    public function setItemsProvider(ProviderInterface $itemsProvider): void
    {
        $this->itemsProvider = $itemsProvider;
    }
}
