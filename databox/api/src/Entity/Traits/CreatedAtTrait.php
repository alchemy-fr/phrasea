<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

trait CreatedAtTrait
{
    /**
     * @ORM\Column(type="datetime")
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @Groups({"dates"})
     */
    protected ?\DateTimeInterface $createdAt = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
