<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Api\Model\Output\TagOutput;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

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
     * @ORM\Column(type="string", length=6, nullable=true)
     */
    private ?string $color = null;

    /**
     * Override trait for annotation.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace", inversedBy="tags")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"_"})
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

    public function __toString()
    {
        return $this->getName() ?? $this->getId();
    }

    public function getColor(): ?string
    {
        if ($this->color) {
            return '#'.$this->color;
        }

        return null;
    }

    public function setColor(?string $color): void
    {
        if ($color && '#' === $color[0]) {
            $color = substr($color, 1);
        }

        $this->color = $color;
    }
}
