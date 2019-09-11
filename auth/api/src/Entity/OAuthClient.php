<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
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

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new DateTime();
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
