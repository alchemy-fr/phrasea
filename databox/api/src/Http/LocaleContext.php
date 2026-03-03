<?php

namespace App\Http;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeInterface;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocaleContext
{
    private bool $isLocaleLess;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
        $this->isLocaleLess = '1' === getenv('IS_WORKER');
    }

    public function wrapLocaleLess(callable $callback): mixed
    {
        $previous = $this->isLocaleLess;
        $this->isLocaleLess = true;

        try {
            return $callback();
        } finally {
            $this->isLocaleLess = $previous;
        }
    }

    public function getUserLocales(): array
    {
        if ($this->isLocaleLess) {
            return [];
        }

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

    public function getPreferredLocales(Workspace $workspace): array
    {
        $userLocales = $this->getUserLocales();

        return array_unique(array_filter(array_merge($userLocales, $workspace->getLocaleFallbacks(), [AttributeInterface::NO_LOCALE])));
    }

    public function getBestWorkspaceLocale(Workspace $workspace): ?string
    {
        $userLocales = $this->getUserLocales();

        return LocaleUtil::getBestLocale($workspace->getEnabledLocales(), $userLocales)
            ?? LocaleUtil::getBestLocale($workspace->getLocaleFallbacks(), $userLocales)
            ?? $workspace->getLocaleFallbacks()[0] ?? null;
    }
}
