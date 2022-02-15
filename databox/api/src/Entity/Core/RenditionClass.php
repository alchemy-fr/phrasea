<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="rend_class_uniq",columns={"workspace_id", "name"})}
 * )
 */
class RenditionClass extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use WorkspaceTrait;

    /**
     * @Groups({"rendclass:index", "rendclass:read"})
     * @ORM\Column(type="string", length=80)
     */
    private ?string $name = null;

    /**
     * @var RenditionDefinition[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\RenditionDefinition", mappedBy="class", cascade={"remove"})
     */
    protected ?DoctrineCollection $definitions = null;

    public function __construct()
    {
        parent::__construct();
        $this->definitions = new ArrayCollection();
    }

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
        return sprintf('%s (%s)', $this->getName(), $this->getWorkspace()->getName());
    }
}
