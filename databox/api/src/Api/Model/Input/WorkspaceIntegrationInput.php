<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Validator\Constraints\NotNull;

class WorkspaceIntegrationInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public $config;

    #[NotNull]
    public $integration;
    public ?string $configYaml = null;
    public ?bool $enabled = null;

    /**
     * @var WorkspaceIntegration[]
     */
    public ?array $needs = null;
    public ?string $if = null;

    #[NotNull(groups: ['create'])]
    public ?Workspace $workspace = null;
}
