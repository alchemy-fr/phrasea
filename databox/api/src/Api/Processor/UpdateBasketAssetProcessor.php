<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Basket\BasketUpdate;
use App\Entity\Basket\BasketAsset;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Messenger\MessageBusInterface;

final class UpdateBasketAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly InputMapperProcessor $inputMapperProcessor,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $target = $context['read_data'] ?? null;
        if (!$target instanceof BasketAsset) {
            throw new \LogicException('The basket item is expected to be read by the provider');
        }
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $target->getBasket());
        $previousHash = $target->getMediaClip()?->getHash();

        $basketAsset = $this->inputMapperProcessor->process($data, $operation, $uriVariables, $context);

        // The integrations (Expose) publish the clipped files: synchronize them when the clip changes
        if ($basketAsset instanceof BasketAsset && $basketAsset->getMediaClip()?->getHash() !== $previousHash) {
            $this->bus->dispatch(new BasketUpdate($basketAsset->getBasket()->getId()));
        }

        return $basketAsset;
    }
}
