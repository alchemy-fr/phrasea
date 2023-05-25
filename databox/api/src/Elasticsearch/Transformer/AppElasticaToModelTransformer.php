<?php

declare(strict_types=1);

namespace App\Elasticsearch\Transformer;

use App\Entity\Core\Collection;
use Elastica\Result;
use FOS\ElasticaBundle\Doctrine\ORM\ElasticaToModelTransformer;
use FOS\ElasticaBundle\Transformer\HighlightableModelInterface;
use Psr\Log\LoggerInterface;

class AppElasticaToModelTransformer extends ElasticaToModelTransformer
{
    private LoggerInterface $logger;

    /**
     * @required
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Transforms an array of elastica objects into an array of
     * model objects fetched from the doctrine repository.
     *
     * @param array $elasticaObjects of elastica objects
     *
     * @return array
     **/
    public function transform(array $elasticaObjects)
    {
        $ids = $hasChildren = $highlights = [];
        /** @var Result[] $elasticaObjects */
        foreach ($elasticaObjects as $elasticaObject) {
            $ids[] = $elasticaObject->getId();
            $highlights[$elasticaObject->getId()] = $elasticaObject->getHighlights();

            $source = $elasticaObject->getData();
            if (isset($source['hasChildren'])) {
                $hasChildren[$elasticaObject->getId()] = $source['hasChildren'];
            }
            unset($source);
        }

        $objects = $this->findByIdentifiers($ids, $this->options['hydrate']);
        $objectsCnt = \count($objects);
        $elasticaObjectsCnt = \count($elasticaObjects);
        $propertyAccessor = $this->propertyAccessor;
        $identifier = $this->options['identifier'];
        if ($objectsCnt < $elasticaObjectsCnt) {
            $missingIds = array_diff($ids, array_map(fn ($object) => $propertyAccessor->getValue($object, $identifier), $objects));

            $this->logger->error(sprintf('Cannot find %d corresponding Doctrine objects for all Elastica results (%d). Missing IDs: %s. IDs: %s', $objectsCnt, $elasticaObjectsCnt, implode(', ', $missingIds), implode(', ', $ids)));
        }

        foreach ($objects as $object) {
            if ($object instanceof HighlightableModelInterface) {
                $id = $propertyAccessor->getValue($object, $identifier);
                $object->setElasticHighlights($highlights[(string) $id]);
            }

            if ($object instanceof Collection) {
                $object->setHasChildren($hasChildren[$object->getId()]);
            }
        }

        // sort objects in the order of ids
        $idPos = \array_flip($ids);
        \usort(
            $objects,
            function ($a, $b) use ($idPos, $identifier, $propertyAccessor) {
                if ($this->options['hydrate']) {
                    return $idPos[(string) $propertyAccessor->getValue(
                        $a,
                        $identifier
                    )] <=> $idPos[(string) $propertyAccessor->getValue($b, $identifier)];
                }

                return $idPos[$a[$identifier]] <=> $idPos[$b[$identifier]];
            }
        );

        return $objects;
    }
}
