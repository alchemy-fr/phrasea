<?php

declare(strict_types=1);

namespace App\Entity\Automation;

use App\Entity\Core\Asset;
use App\Repository\Automation\AutomationSeenAssetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The cursor of an automation: an asset handled once (every action succeeded on it)
 * is no longer "new" for this automation, even if it leaves the results and comes back.
 *
 * Written and read in bulk through DBAL (see AutomationSeenAssetRepository).
 */
#[ORM\Entity(repositoryClass: AutomationSeenAssetRepository::class)]
class AutomationSeenAsset
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: SavedSearchAutomation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SavedSearchAutomation $automation;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $firstSeenAt;

    // The run which handled the asset, null when marked by a cursor reset
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $runId = null;

    public function __construct(SavedSearchAutomation $automation, Asset $asset, ?string $runId = null)
    {
        $this->automation = $automation;
        $this->asset = $asset;
        $this->runId = $runId;
        $this->firstSeenAt = new \DateTimeImmutable();
    }

    public function getAutomation(): SavedSearchAutomation
    {
        return $this->automation;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getRunId(): ?string
    {
        return $this->runId;
    }
}
