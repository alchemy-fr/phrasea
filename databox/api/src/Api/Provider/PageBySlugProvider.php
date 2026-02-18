<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\Page\PageRepository;

final class PageBySlugProvider implements ProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->pageRepository->findOneBy(['slug' => $uriVariables['slug']]);
    }
}
