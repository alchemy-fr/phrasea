<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Entity\Automation\SavedSearchAutomation;

/**
 * An action whose configuration depends on other fields of its automation
 * (e.g. on the workspace its results are limited to).
 */
interface AutomationAwareActionInterface
{
    /**
     * Key of a violation on the workspace of the automation, rather than on the configuration:
     * reported on the workspace when it is the field being written.
     */
    public const string WORKSPACE_KEY = 'workspace';

    /**
     * Checked with the rights of the current user once the automation is mapped,
     * when it is created or when its actions, scope or workspace are written.
     *
     * @param array $config the normalized configuration (see validateConfig()), secrets decrypted
     *
     * @return array<string, string> messages indexed by configuration key, empty when valid
     */
    public function getAutomationViolations(array $config, SavedSearchAutomation $automation): array;
}
