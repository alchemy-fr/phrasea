<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Template\AssetDataTemplate;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class AssetDataTemplatePostTransformListener implements EventSubscriberInterface
{
    /**
     * Indexed in "users" when an ACE grants the template to all users.
     */
    public const string ALL_USERS = '*';

    public function __construct(private PermissionManager $permissionManager)
    {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var AssetDataTemplate $assetDataTemplate */
        if (!($assetDataTemplate = $event->getObject()) instanceof AssetDataTemplate) {
            return;
        }

        $document = $event->getDocument();

        $users = [];
        $groups = [];
        if (!$assetDataTemplate->isPublic()) {
            $users = $this->permissionManager->getAllowedUsers($assetDataTemplate, PermissionInterface::VIEW);
            if (in_array(null, $users, true)) {
                $users = [self::ALL_USERS];
            } else {
                $groups = $this->permissionManager->getAllowedGroups($assetDataTemplate, PermissionInterface::VIEW);
            }
        }

        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
