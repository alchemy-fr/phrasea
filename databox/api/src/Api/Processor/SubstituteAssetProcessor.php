<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetInput;
use App\Attribute\BatchAttributeManager;
use App\Consumer\Handler\File\CopyFileToAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Service\Asset\AssetManager;
use Doctrine\ORM\EntityManagerInterface;

final class SubstituteAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly EntityManagerInterface $em,
        private readonly AssetManager $assetManager,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $file = $this->handleFile($data, $data);
        if (null === $file) {
            throw new \RuntimeException('No file provided for asset substitution');
        }
        $data->setSource($file);
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    private function handleFile(AssetInput $data, Asset $asset): ?File
    {
        if (null !== $file = $this->handleSource($data->sourceFile, $asset->getWorkspace())) {
            return $file;
        } elseif (null !== $file = $this->handleFromFile($data->sourceFileId)) {
            $this->postFlushStackListener->addBusMessage(new CopyFileToAsset($asset->getId(), $file->getId()));

            return $file;
        } elseif (null !== $file = $this->handleUpload($asset->getWorkspace())) {
            return $file;
        }

        return null;
    }
}
