<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\State\ProviderInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait CollectionProviderAwareTrait
{
    protected ProviderInterface $collectionProvider;

    #[Required]
    public function setCollectionProvider(ProviderInterface $collectionProvider): void
    {
        $this->collectionProvider = $collectionProvider;
    }
}
