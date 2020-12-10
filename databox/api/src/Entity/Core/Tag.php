<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="ws_name_uniq",columns={"workspace_id", "name"})})
 * @ApiResource()
 */
class Tag extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private string $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    private Workspace $workspace;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getESId(): string
    {
        return $this->getName();
        // TODO remove name
        return sprintf('%s-%s', $this->getName(), $this->getId());
    }
}
