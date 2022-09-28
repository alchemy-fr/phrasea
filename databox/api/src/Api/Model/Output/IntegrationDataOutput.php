<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

class IntegrationDataOutput extends AbstractUuidOutput
{
    /**
     * @Groups({"integration:index"})
     */
    private string $name;

    /**
     * @Groups({"integration:index"})
     */
    private ?string $keyId = null;

    /**
     * @Groups({"integration:index"})
     */
    private string $value;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
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
