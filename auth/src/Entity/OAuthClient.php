<?php
declare(strict_types=1);

namespace App\Entity;

use FOS\OAuthServerBundle\Entity\Client as BaseClient;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class OAuthClient extends BaseClient
{
    /**
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=80, unique=true)
     */
    protected $id;

    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
