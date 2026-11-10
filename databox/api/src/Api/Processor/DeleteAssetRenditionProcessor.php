<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AssetRendition;
use App\Service\Storage\RenditionManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DeleteAssetRenditionProcessor implements ProcessorInterface
{
    public function __construct(
        private RenditionManager $renditionManager,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $processor,
    ) {
    }

    /**
     * @param AssetRendition $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->renditionManager->pushRenditionUpdate($data);

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
