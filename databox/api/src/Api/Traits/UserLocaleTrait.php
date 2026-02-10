<?php

namespace App\Api\Traits;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeInterface;
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
            $languages = $request->getLanguages();
            if ($request->headers->get('X-Data-Locale')) {
                array_unshift($languages, $request->headers->get('X-Data-Locale'));
            }

            return $languages;
        }

        return [];
    }

    protected function getPreferredLocales(Workspace $workspace): array
    {
        $userLocales = $this->getUserLocales();

        return array_unique(array_filter(array_merge($userLocales, $workspace->getLocaleFallbacks(), [AttributeInterface::NO_LOCALE])));
    }

    protected function getBestWorkspaceLocale(Workspace $workspace): ?string
    {
        $userLocales = $this->getUserLocales();

        return LocaleUtil::getBestLocale($workspace->getEnabledLocales(), $userLocales)
            ?? LocaleUtil::getBestLocale($workspace->getLocaleFallbacks(), $userLocales)
            ?? $workspace->getLocaleFallbacks()[0] ?? null;
    }

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }
}
