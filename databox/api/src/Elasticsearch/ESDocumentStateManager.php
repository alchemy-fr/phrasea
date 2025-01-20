<?php

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Api\Model\Output\ESDocumentStateOutput;
use Elastica\Request;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class ESDocumentStateManager
{
    /**
     * @param ObjectPersisterInterface[] $objectPersisters
     */
    public function __construct(
        #[TaggedIterator(tag: 'fos_elastica.persister')]
        private iterable $objectPersisters,
        private ElasticSearchClient $elasticSearchClient,

    ) {
    }

    public function getObjectState(AbstractUuidEntity $object): ESDocumentStateOutput
    {
        $document = $this->getObjectPersister($object)->transformToElasticaDocument($object);
        $indexName = $this->elasticSearchClient->getIndexName($document->getIndex());
        $response = $this->elasticSearchClient->request($indexName.'/_doc/'.$object->getId(), [], Request::GET);

        $data = $response->getData();
        $synced = $document->getData() == $data['_source'];

        return new ESDocumentStateOutput($data, $synced);
    }

    private function getObjectPersister(object $object): ObjectPersister
    {
        foreach ($this->objectPersisters as $objectPersister) {
            if ($objectPersister instanceof ObjectPersister && $objectPersister->handlesObject($object)) {
                return $objectPersister;
            }
        }

        throw new \RuntimeException(sprintf('No object persister found for object of class %s', get_class($object)));
    }
}
