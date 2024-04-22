<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search\Mapping;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateAttributesMappingHandler
{
    public function __construct(
        private IndexMappingUpdater $indexMappingUpdater,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(UpdateAttributesMapping $message): void
    {
        /** @var Workspace $workspace */
        $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $message->getId());

        $this->indexMappingUpdater->synchronizeWorkspace($workspace);
    }
}
