<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Api\Model\Output\ESDocumentStateOutput;
use App\Util\ArrayUtil;
use Elastica\Document;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ESDocumentStateManager
{
    /**
     * @param ObjectPersisterInterface[] $objectPersisters
     */
    public function __construct(
        #[AutowireIterator(tag: 'fos_elastica.persister')]
        private iterable $objectPersisters,
        private ElasticSearchClient $elasticSearchClient,

    ) {
    }

    public function getObjectState(AbstractUuidEntity $object): ESDocumentStateOutput
    {
        $document = $this->createDocument($object);
        $indexName = $this->elasticSearchClient->getIndexName($document->getIndex());
        $response = $this->elasticSearchClient->request($indexName.'/_doc/'.$object->getId(), [], 'GET');

        $data = $response->asArray();
        $synced = $this->documentAreSame($document->getData(), $data['_source'] ?? []);

        return new ESDocumentStateOutput($data, $synced);
    }

    /**
     * The document the indexing would send for this object, built in memory.
     */
    public function createDocument(AbstractUuidEntity $object): Document
    {
        return $this->getObjectPersister($object)->transformToElasticaDocument($object);
    }

    private function documentAreSame(array $a, array $b): bool
    {
        $norm = function (array $data): array {
            unset($data['_version'], $data['_seq_no']);

            return $data;
        };

        return ArrayUtil::arrayAreSame($norm($a), $norm($b));
    }

    private function getObjectPersister(object $object): ObjectPersister
    {
        foreach ($this->objectPersisters as $objectPersister) {
            if ($objectPersister instanceof ObjectPersister && $objectPersister->handlesObject($object)) {
                return $objectPersister;
            }
        }

        throw new \RuntimeException(sprintf('No object persister found for object of class %s', $object::class));
    }
}
