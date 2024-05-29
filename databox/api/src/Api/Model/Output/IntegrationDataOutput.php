<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Integration\AbstractIntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Serializer\Annotation\Groups;

class IntegrationDataOutput extends AbstractUuidOutput
{
    #[Groups([WorkspaceIntegration::GROUP_LIST, AbstractIntegrationData::GROUP_LIST])]
    private string $name;

    #[Groups([WorkspaceIntegration::GROUP_LIST, AbstractIntegrationData::GROUP_LIST])]
    private ?string $keyId = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST, AbstractIntegrationData::GROUP_LIST])]
    private $value;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getKeyId(): ?string
    {
        return $this->keyId;
    }

    public function setKeyId(?string $keyId): void
    {
        $this->keyId = $keyId;
    }
}
