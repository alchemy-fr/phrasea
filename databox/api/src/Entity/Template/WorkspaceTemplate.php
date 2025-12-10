<?php

declare(strict_types=1);

namespace App\Entity\Template;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use App\Repository\Template\WorkspaceTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Entity(repositoryClass: WorkspaceTemplateRepository::class)]
class WorkspaceTemplate extends AbstractUuidEntity implements \Stringable, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const string GROUP_READ = 'workspace_template:read';
    final public const string GROUP_LIST = 'workspace_template:index';

    /**
     * Template name.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->getName() ?? $this->getId();
    }
}
