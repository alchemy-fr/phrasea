<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Model\AutomationScopeEnum;

/**
 * An action whose configuration depends on the scope of its automation
 * (e.g. an option which only makes sense when every result is processed).
 */
interface ScopeAwareActionInterface
{
    /**
     * Checked once both the scope and the actions of the automation are known, when either is written.
     *
     * @param array $config the normalized configuration (see validateConfig())
     *
     * @return array<string, string> messages indexed by configuration key, empty when valid
     */
    public function getScopeViolations(array $config, AutomationScopeEnum $scope): array;
}
