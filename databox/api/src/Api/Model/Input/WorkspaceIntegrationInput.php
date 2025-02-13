<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;
use Symfony\Component\Validator\Constraints\NotNull;

class WorkspaceIntegrationInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public $config;
    public $integration;
    public ?string $configYaml = null;
    public ?bool $enabled = null;

    #[NotNull]
    public ?Workspace $workspace = null;
}
