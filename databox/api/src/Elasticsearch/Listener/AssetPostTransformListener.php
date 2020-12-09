<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Entity\Core\Asset;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetPostTransformListener implements EventSubscriberInterface
{
    public function hydrateAssetDocument(PostTransformEvent $event): void
    {
        /** @var Asset $asset */
        if (!($asset = $event->getObject()) instanceof Asset) {
            return;
        }

        $document = $event->getDocument();

        $isPublic = $asset->isPublic();

        $users = [];
        $groups = [];
        // Check ACE on asset

        $collectionsPaths = [];
        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            if ($collection->isPublic()) {
                $isPublic = true;
            }

            // Check ACE on collection
//            $users = [];
//            $groups = [];

            $path = $collection->getTitle('en');
            $parent = $collection;
            while ($parent = $parent->getParent()) {
                $path = $parent->getTitle('en').'/'.$path;

                if ($parent->isPublic()) {
                    $isPublic = true;
                }
            }

            $collectionsPaths[] = $path;
        }

        $users = ['alice', 'jack'];
        $groups = ['reporter_sport_tennis'];

        $document->set('public', $isPublic);
        $document->set('users', $users);
        $document->set('groups', $groups);
        $document->set('collectionPaths', $collectionsPaths);
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateAssetDocument',
        ];
    }

}
