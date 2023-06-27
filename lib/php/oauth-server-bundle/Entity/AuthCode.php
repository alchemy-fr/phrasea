<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\OAuthServerBundle\Entity\AuthCode as BaseAuthCode;
use FOS\OAuthServerBundle\Model\ClientInterface;

#[ORM\Entity]
class AuthCode extends BaseAuthCode
{
    /**
     * @var string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
    protected $id;

    /**
     * @var OAuthClient
     */
    #[ORM\ManyToOne(targetEntity: 'OAuthClient')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ClientInterface $client;

    #[ORM\Column(type: 'datetime')]
    private readonly \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
