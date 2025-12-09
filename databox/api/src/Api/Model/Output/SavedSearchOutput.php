<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\SavedSearch\SavedSearch;
use Symfony\Component\Serializer\Annotation\Groups;

class SavedSearchOutput extends AbstractUuidOutput
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
    #[Groups([SavedSearch::GROUP_LIST, SavedSearch::GROUP_READ])]
    protected array $capabilities = [];

    #[Groups([SavedSearch::GROUP_LIST, SavedSearch::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?string $title = null;

    #[Groups([SavedSearch::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?bool $public = null;

    #[Groups([SavedSearch::GROUP_READ])]
    public ?UserOutput $owner = null;

    #[Groups([SavedSearch::GROUP_LIST, SavedSearch::GROUP_READ])]
    public ?array $data = null;
}
