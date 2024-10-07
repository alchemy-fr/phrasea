<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class PrepareSubstitutionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $data->setPendingUploadToken(Uuid::uuid4()->toString());

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
