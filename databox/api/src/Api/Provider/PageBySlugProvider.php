<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Page\Page;
use App\Repository\Page\PageRepository;
use App\Security\Voter\AbstractVoter;

final class PageBySlugProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Page
    {
        $page = $this->pageRepository->findOneBy([
            'slug' => $uriVariables['slug'] ?? null,
            'enabled' => true,
        ]);

        // A page the user may not read answers as an unknown one: slugs can be guessed.
        if (!$page instanceof Page || !$this->isGranted(AbstractVoter::READ, $page)) {
            return null;
        }

        return $page;
    }
}
