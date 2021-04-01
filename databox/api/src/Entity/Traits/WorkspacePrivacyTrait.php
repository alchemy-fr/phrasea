<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\Mapping as ORM;

trait WorkspacePrivacyTrait
{
    /**
     * @ORM\Column(type="smallint", nullable=false)
     */
    private int $privacy = WorkspaceItemPrivacyInterface::SECRET;

    public function getPrivacy(): int
    {
        return $this->privacy;
    }

    public function setPrivacy(int $privacy): void
    {
        $this->privacy = $privacy;
    }
}
