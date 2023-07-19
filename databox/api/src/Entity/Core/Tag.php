<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Api\Model\Output\TagOutput;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(shortName: 'tag', normalizationContext: ['groups' => ['_', Tag::GROUP_LIST]], input: false, output: TagOutput::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'ws_name_uniq', columns: ['workspace_id', 'name'])]
#[ORM\Entity]
#[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: ['workspace'])]
class Tag extends AbstractUuidEntity implements TranslatableInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use LocaleTrait;
    use WorkspaceTrait;
    final public const GROUP_READ = 'tag:read';
    final public const GROUP_LIST = 'tag:index';

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $name;

    #[ORM\Column(type: 'string', length: 6, nullable: true)]
    private ?string $color = null;

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function __toString(): string
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
