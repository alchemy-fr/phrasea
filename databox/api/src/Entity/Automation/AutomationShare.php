<?php

declare(strict_types=1);

namespace App\Entity\Automation;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Repository\Automation\AutomationShareRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The share link an automation fills with the assets of a workspace, run after run
 * (a share only holds assets of a single workspace).
 *
 * Deleting the share deletes the link: the next run creates a new share.
 */
#[ORM\Entity(repositoryClass: AutomationShareRepository::class)]
#[ORM\UniqueConstraint(name: 'automation_share_ws_uniq', columns: ['automation_id', 'workspace_id'])]
class AutomationShare extends AbstractUuidEntity
{
    #[ORM\ManyToOne(targetEntity: SavedSearchAutomation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SavedSearchAutomation $automation;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: Share::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Share $share;

    public function __construct(SavedSearchAutomation $automation, Workspace $workspace, Share $share)
    {
        parent::__construct();
        $this->automation = $automation;
        $this->workspace = $workspace;
        $this->share = $share;
    }

    public function getAutomation(): SavedSearchAutomation
    {
        return $this->automation;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getShare(): Share
    {
        return $this->share;
    }
}
