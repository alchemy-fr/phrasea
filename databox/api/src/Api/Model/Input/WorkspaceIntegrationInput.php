<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Validator\Constraints\NotNull;

class WorkspaceIntegrationInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public $config;

    #[NotNull]
    public $integration;
    public ?string $configYaml = null;
    public ?bool $enabled = null;
    public ?bool $public = null;

    /**
     * @var WorkspaceIntegration[]
     */
    public ?array $needs = null;
    public ?string $if = null;

    /**
     * Null for an instance-wide integration (only allowed for integrations not requiring a workspace).
     */
    public ?Workspace $workspace = null;
}
