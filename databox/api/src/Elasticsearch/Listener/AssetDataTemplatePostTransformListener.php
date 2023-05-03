<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Template\AssetDataTemplate;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetDataTemplatePostTransformListener implements EventSubscriberInterface
{
    public function __construct(private readonly PermissionManager $permissionManager)
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
                $users = ['*'];
            } else {
                $groups = $this->permissionManager->getAllowedGroups($assetDataTemplate, PermissionInterface::VIEW);
            }
        }

        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
