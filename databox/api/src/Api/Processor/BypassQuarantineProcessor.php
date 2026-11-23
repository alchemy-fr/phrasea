<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use App\Service\Asset\QuarantineResolver;

/**
 * Accepts a quarantined asset despite its failed file analysis: the asset
 * status is switched to Accepted and its source file analysis is marked as
 * bypassed (the analyzer results are kept for reference).
 */
final class BypassQuarantineProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly QuarantineResolver $quarantineResolver,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $this->denyAccessUnlessGranted(AssetVoter::READ, $data);
        $this->denyAccessUnlessGranted(AssetVoter::QUARANTINE_BYPASS, $data);

        $this->quarantineResolver->bypass($data);

        return $data;
    }
}
