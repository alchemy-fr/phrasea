<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use FOS\OAuthServerBundle\Entity\AccessToken as BaseAccessToken;
use FOS\OAuthServerBundle\Model\ClientInterface;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="Alchemy\OAuthServerBundle\Entity\AccessTokenRepository")
 *
 * @ORM\EntityListeners({"Alchemy\OAuthServerBundle\Doctrine\Listener\AccessTokenListener"})
 */
class AccessToken extends BaseAccessToken
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected $id;

    /**
     * @var OAuthClient
     *
     * @ORM\ManyToOne(targetEntity="OAuthClient")
     *
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected ClientInterface $client;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
