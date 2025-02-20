<?php

declare(strict_types=1);

namespace App\Entity\Template;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\Core\WorkspaceTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Entity(repositoryClass: WorkspaceTemplateRepository::class)]
#[ApiResource(
    shortName: 'workspace-template',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => [
                    WorkspaceTemplate::GROUP_LIST,
                    WorkspaceTemplate::GROUP_READ,
                ],
            ],
            security: 'is_granted("READ", object)'
        ),
        new Put(
            normalizationContext: [
                'groups' => [
                    WorkspaceTemplate::GROUP_LIST,
                    WorkspaceTemplate::GROUP_READ,
                ],
            ],
            security: 'is_granted("EDIT", object)',
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)',
        ),
    ],
    normalizationContext: [
        'groups' => [WorkspaceTemplate::GROUP_LIST],
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['workspace' => 'exact'])]
class WorkspaceTemplate extends AbstractUuidEntity implements \Stringable
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
