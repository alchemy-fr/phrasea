<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\State\ProviderInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait ItemProviderAwareTrait
{
    protected ProviderInterface $itemProvider;

    #[Required]
    public function setItemProvider(ProviderInterface $itemProvider): void
    {
        $this->itemProvider = $itemProvider;
    }
}
