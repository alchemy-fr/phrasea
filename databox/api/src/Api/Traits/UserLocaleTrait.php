<?php

namespace App\Api\Traits;

use App\Entity\Core\Workspace;
use App\Http\LocaleContext;
use Symfony\Contracts\Service\Attribute\Required;

trait UserLocaleTrait
{
    private LocaleContext $localeContext;

    private function getUserLocales(): array
    {
        return $this->localeContext->getUserLocales();
    }

    protected function getPreferredLocales(Workspace $workspace): array
    {
        return $this->localeContext->getPreferredLocales($workspace);
    }

    protected function getBestWorkspaceLocale(Workspace $workspace): ?string
    {
        return $this->localeContext->getBestWorkspaceLocale($workspace);
    }

    #[Required]
    public function setLocaleContext(LocaleContext $localeContext): void
    {
        $this->localeContext = $localeContext;
    }
}
