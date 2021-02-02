<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use App\Api\Model\Output\WorkspaceOutput;

/**
 * @ORM\Entity()
 *
 * @ApiResource(
 *  shortName="workspace",
 *  normalizationContext={"groups"={"_", "workspace:index"}},
 *  output=WorkspaceOutput::class,
 *  input=false,
 * )
 */
class Workspace extends AbstractUuidEntity implements AclObjectInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $ownerId = null;

    /**
     * @var Collection[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Collection", mappedBy="workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?DoctrineCollection $collections = null;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCollections(): DoctrineCollection
    {
        return $this->collections;
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }
}
