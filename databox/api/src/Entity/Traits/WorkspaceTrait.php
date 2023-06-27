<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Entity\Core\Workspace;
use Doctrine\ORM\Mapping as ORM;

trait WorkspaceTrait
{
    #[ORM\ManyToOne(targetEntity: \App\Entity\Core\Workspace::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Workspace $workspace = null;

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getWorkspaceId(): string
    {
        return $this->workspace->getId();
    }
}
