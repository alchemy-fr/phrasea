<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Doctrine\Listener;

use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\AuthCode;
use Alchemy\OAuthServerBundle\Entity\RefreshToken;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

class MetadataListener implements EventSubscriber
{
    public function __construct(private readonly string $userClass)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();

        $default = [
            'fieldName' => 'user',
            'targetEntity' => $this->userClass,
            'onDelete' => 'CASCADE',
            'joinColumns' => [
                [
                    'name' => 'user_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                    'nullable' => true,
                ],
            ],
        ];

        $configs = [
            AccessToken::class => [],
            RefreshToken::class => [],
            AuthCode::class => [
                'joinColumns' => [
                    [
                        'name' => 'user_id',
                        'referencedColumnName' => 'id',
                        'onDelete' => 'CASCADE',
                        'nullable' => false,
                    ],
                ],
            ],
        ];

        if (isset($configs[$classMetadata->getName()])) {
            $classMetadata->mapManyToOne(array_merge($default, $configs[$classMetadata->getName()]));
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata => 'loadClassMetadata',
        ];
    }
}
