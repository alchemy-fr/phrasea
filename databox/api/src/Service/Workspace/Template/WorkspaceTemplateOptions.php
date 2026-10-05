<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template;

/**
 * What an export carries besides the portable configuration.
 * Instance-bound data (user/group ids, encrypted secrets) only make sense when the
 * template is imported back into the same instance, e.g. for a workspace duplication.
 */
final readonly class WorkspaceTemplateOptions
{
    public function __construct(
        /**
         * Export ACEs, owners and user/group targets (attribute filter rules, asset policies),
         * and the private data templates.
         */
        public bool $withAccessControl = false,
        /**
         * Export the secret values, encrypted with the instance key.
         */
        public bool $withSecrets = false,
    ) {
    }

    public static function portable(): self
    {
        return new self();
    }

    public static function full(): self
    {
        return new self(withAccessControl: true, withSecrets: true);
    }
}
