<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Profile\Profile;
use Symfony\Component\Serializer\Annotation\Groups;

class ProfileOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'object',
        'properties' => [
            'canEdit' => 'boolean',
            'canDelete' => 'boolean',
            'canEditPermissions' => 'boolean',
        ],
    ])]
    #[Groups([Profile::GROUP_LIST, Profile::GROUP_READ])]
    protected array $capabilities = [];

    /**
     * @var ProfileItemOutput[]
     */
    #[Groups([Profile::GROUP_READ])]
    public ?array $items = null;

    #[Groups([Profile::GROUP_LIST, Profile::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?string $title = null;

    #[Groups([Profile::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?string $description = null;

    #[Groups([Profile::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?bool $public = null;

    #[Groups([Profile::GROUP_READ])]
    public ?UserOutput $owner = null;
}
