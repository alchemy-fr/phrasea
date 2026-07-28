<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accepts a quarantined asset despite its failed file analysis: the asset
 * status is switched to Accepted and its source file analysis is marked as
 * bypassed (the analyzer results are kept for reference).
 */
final class BypassQuarantineProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $this->denyAccessUnlessGranted(AssetVoter::READ, $data);
        $this->denyAccessUnlessGranted(AssetVoter::QUARANTINE_BYPASS, $data);

        $source = $data->getSource();
        if (null !== $source) {
            $source->bypassAnalysis();
            $this->em->persist($source);
        }

        $data->setStatus(AssetStatusEnum::Accepted);
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
