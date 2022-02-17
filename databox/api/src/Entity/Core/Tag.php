<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use Doctrine\ORM\Mapping as ORM;
use App\Api\Model\Output\TagOutput;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;

/**
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="ws_name_uniq",columns={"workspace_id", "name"})})
 * @ApiResource(
 *  shortName="tag",
 *  normalizationContext={"groups"={"_", "tag:index"}},
 *  output=TagOutput::class,
 *  input=false
 * )
 * @ApiFilter(filterClass=SearchFilter::class, strategy="exact", properties={"workspace"})
 */
class Tag extends AbstractUuidEntity implements TranslatableInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use LocaleTrait;
    use WorkspaceTrait;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private string $name;

    /**
     * Override trait for annotation
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace", inversedBy="tags")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?Workspace $workspace = null;

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

    public function __toString()
    {
        return $this->getName() ?? $this->getId();
    }
}
