<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use FOS\OAuthServerBundle\Entity\AccessToken as BaseAccessToken;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Entity\AccessTokenRepository")
 * @ORM\EntityListeners({"App\Doctrine\Listener\AccessTokenListener"})
 */
class AccessToken extends BaseAccessToken
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected $id;

    /**
     * @var OAuthClient
     *
     * @ORM\ManyToOne(targetEntity="OAuthClient")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $client;

    /**
     * @var User|null
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $user;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
