<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Output\WorkspaceEnvOutput;
use App\Api\Provider\WorkspaceEnvCollectionProvider;
use App\Audit\AuditableInterface;
use App\Entity\Traits\NullableWorkspaceTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A plain value referenced as `${NAME}` in the configuration of integrations,
 * managed in the admin. Only the names are exposed by the API: a value may
 * still hold sensitive data.
 */
#[ApiResource(
    shortName: 'workspace-env',
    operations: [
        new GetCollection(
            parameters: [
                'workspace' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Workspace ID or IRI ("workspace" or "global" is required)',
                    castToArray: false,
                ),
                'global' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'The instance ones, of no workspace (administrators only)',
                    castToNativeType: true,
                    castToArray: false,
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    description: 'Page size (default 30, max 100)',
                    castToArray: false,
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    castToArray: false,
                ),
            ],
        ),
    ],
    normalizationContext: [
        'groups' => ['_', self::GROUP_READ],
    ],
    output: WorkspaceEnvOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: WorkspaceEnvCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_env_key', columns: ['workspace_id', 'name'])]
#[ORM\Entity]
class WorkspaceEnv extends AbstractUuidEntity implements AuditableInterface, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use NullableWorkspaceTrait;

    final public const int OBJECT_INDEX = 9;
    final public const string GROUP_READ = 'workspace-env:read';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Assert\NotNull]
    private ?string $value = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
