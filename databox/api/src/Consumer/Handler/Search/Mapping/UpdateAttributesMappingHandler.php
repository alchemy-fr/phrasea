<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search\Mapping;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class UpdateAttributesMappingHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'update_attr_mapping';

    public function __construct(private readonly IndexMappingUpdater $indexMappingUpdater)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        /** @var Workspace $workspace */
        $workspace = $em->find(Workspace::class, $id);

        $this->indexMappingUpdater->synchronizeWorkspace($workspace);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
