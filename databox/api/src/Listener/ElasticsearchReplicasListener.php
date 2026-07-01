<?php

declare(strict_types=1);

namespace App\Listener;

use FOS\ElasticaBundle\Event\PostIndexResetEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostIndexResetEvent::class)]
final readonly class ElasticsearchReplicasListener
{
    public function __construct(
        private IndexManager $indexManager,
        private int $elasticsearchIndexReplicas,
    ) {
    }

    public function __invoke(PostIndexResetEvent $event): void
    {
        $replicas = max(0, $this->elasticsearchIndexReplicas);

        $this->indexManager
            ->getIndex($event->getIndex())
            ->getSettings()
            ->set([
                'index' => [
                    'number_of_replicas' => $replicas,
                ],
            ]);
    }
}