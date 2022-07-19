<?php

declare(strict_types=1);

namespace App\Phraseanet;

use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsEnqueueMethodHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class PhraseanetGenerateRenditionsManager
{
    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function generateRenditions(Asset $asset): void
    {
        $workspace = $asset->getWorkspace();

        if (Workspace::PHRASEANET_RENDITION_METHOD_SUBDEF_V3_API === $workspace->getPhraseanetRenditionMethod()) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsHandler::createEvent($asset->getId()));
        } elseif (Workspace::PHRASEANET_RENDITION_METHOD_ENQUEUE === $workspace->getPhraseanetRenditionMethod()) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsEnqueueMethodHandler::createEvent($asset->getId()));
        }
    }
}
