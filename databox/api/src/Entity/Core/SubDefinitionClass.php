<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="sdc_uniq",columns={"workspace_id", "name"})}
 * )
 * @ApiResource()
 */
class SubDefinitionClass extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use WorkspaceTrait;

    /**
     * @Groups({"subdefclass:index", "subdefclass:read"})
     * @ORM\Column(type="string", length=80)
     */
    private ?string $name = null;

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
        return $this->getName();
    }
}
