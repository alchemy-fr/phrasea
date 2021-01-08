<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait UpdatedAtTrait
{
    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private DateTime $updatedAt;

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
