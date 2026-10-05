<?php

declare(strict_types=1);

namespace App\Elasticsearch\Suggestion;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeInterface;
use App\Entity\Core\Workspace;

/**
 * Locales under which the entity labels of a workspace are indexed for the search suggestions
 * (see AssetPostTransformListener): every locale a user of the workspace can be resolved to
 * (LocaleContext::getBestWorkspaceLocale()), plus the untranslated one.
 */
final readonly class SuggestionLocales
{
    /**
     * @return string[] normalized and unique
     */
    public static function ofWorkspace(Workspace $workspace): array
    {
        $locales = array_map(
            LocaleUtil::normalizeLocale(...),
            [...$workspace->getEnabledLocales(), ...($workspace->getLocaleFallbacks() ?? [])],
        );
        $locales[] = AttributeInterface::NO_LOCALE;

        return array_values(array_unique($locales));
    }
}
