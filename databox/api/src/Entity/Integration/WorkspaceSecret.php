<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Input\WorkspaceSecretInput;
use App\Api\Model\Output\WorkspaceSecretOutput;
use App\Api\Processor\InputMapperProcessor;
use App\Api\Provider\WorkspaceSecretCollectionProvider;
use App\Audit\AuditableInterface;
use App\Audit\AuditIgnore;
use App\Entity\Traits\NullableWorkspaceTrait;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A value referenced as `${NAME}` in the configuration of the integrations of its
 * workspace (or of the instance-wide ones, without workspace).
 *
 * The value is encrypted with the public key only: the API never returns it, the
 * workers decrypt it.
 */
#[ApiResource(
    shortName: 'workspace-secret',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
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
        new Post(
            extraProperties: [InputMapperProcessor::ENTITY_SECURITY => 'is_granted("'.AbstractVoter::CREATE.'", object)'],
            processor: InputMapperProcessor::class,
        ),
        new Patch(
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            processor: InputMapperProcessor::class,
        ),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
    ],
    normalizationContext: [
        'groups' => ['_', 'dates', self::GROUP_READ],
    ],
    input: WorkspaceSecretInput::class,
    output: WorkspaceSecretOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: WorkspaceSecretCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_key', columns: ['workspace_id', 'name'])]
// The SQL constraint does not apply to the instance secrets (NULL workspace)
#[UniqueEntity(fields: ['workspace', 'name'], errorPath: 'name', ignoreNull: false)]
#[ORM\Entity]
class WorkspaceSecret extends AbstractUuidEntity implements AuditableInterface, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use NullableWorkspaceTrait;
    final public const int OBJECT_INDEX = 11;
    final public const string GROUP_READ = 'workspace-secret:read';

    /**
     * Also matched by the `$NAME` form of the references.
     */
    final public const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: self::NAME_PATTERN, message: 'The name must start with a letter or "_", followed by letters, digits or "_".')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[AuditIgnore(mask: true)]
    private ?string $value = null;

    private ?string $plainValue = null;

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

    public function getPlainValue(): ?string
    {
        return $this->plainValue;
    }

    public function setPlainValue(?string $plainValue): void
    {
        $this->plainValue = $plainValue;
        if (null !== $plainValue) {
            $this->setValue(null); // Make a change to this mapped field trigger Doctrine preUpdate event
        }
    }
}
