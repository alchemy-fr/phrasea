<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Entity\Core\Workspace;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait WorkspaceTrait
{
    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
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
