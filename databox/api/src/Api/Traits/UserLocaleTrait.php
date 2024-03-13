<?php

namespace App\Api\Traits;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

trait UserLocaleTrait
{
    private RequestStack $requestStack;

    private function getUserLocales(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            return $request->getLanguages();
        }

        return [];
    }

    protected function getPreferredLocales(Workspace $workspace): array
    {
        $userLocales = $this->getUserLocales();

        return array_unique(array_filter(array_merge($userLocales, $workspace->getLocaleFallbacks(), [IndexMappingUpdater::NO_LOCALE])));
    }

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }
}
