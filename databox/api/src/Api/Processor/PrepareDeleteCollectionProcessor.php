<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PrepareDeleteCollectionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    /**
     * @param Collection $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        /** @var Asset $asset */
        foreach($this->assetRepository->findByStoryCollectionIds([$data->getId()]) as $asset) {
            $this->em->remove($asset);
            $this->em->flush();
        }
    }
}
