<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\MessengerBundle\Listener\PostFlushStack;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AssetRendition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DeleteAssetRenditionProcessor implements ProcessorInterface
{
    public function __construct(
        private PostFlushStack $postFlushStack,
        private PusherManager $pusherManager,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $processor,
    ) {
    }

    /**
     * @param AssetRendition $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->postFlushStack->addBusMessage($this->pusherManager->createBusMessage(
            'assets',
            'rendition-update',
            [
                'assetId' => $data->getAsset()->getId(),
                'definition' => $data->getDefinition()->getId(),
            ]
        ));

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
