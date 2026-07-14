<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Asset\AssetExportProcess;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetExport;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetExportProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AssetExport $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AssetExport {
        foreach ($data->getAssets() as $assetId) {
            $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
        }

        $user = $this->getStrictUser();
        $data->setOwnerId($user->getId());
        $data->setUserData($this->extractUserData());

        $this->em->persist($data);
        $this->em->flush();

        $this->bus->dispatch(new AssetExportProcess($data->getId()));

        return $data;
    }
}
