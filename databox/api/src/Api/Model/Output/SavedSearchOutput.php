<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\AttributeList\AttributeList;
use Symfony\Component\Serializer\Annotation\Groups;

class SavedSearchOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    #[Groups([AttributeList::GROUP_LIST, AttributeList::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?string $title = null;

    #[Groups([AttributeList::GROUP_LIST, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?bool $public = null;

    #[Groups([AttributeList::GROUP_READ])]
    public ?UserOutput $owner = null;
}
