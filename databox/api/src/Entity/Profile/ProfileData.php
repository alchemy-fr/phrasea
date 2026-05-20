<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProfileData extends AbstractUuidEntity
{
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = [];

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
