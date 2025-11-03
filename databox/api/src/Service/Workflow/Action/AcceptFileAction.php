<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Border\UploaderClient;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

readonly class AcceptFileAction implements ActionInterface
{
    final public const string COLLECTION_DESTINATION = 'collection_destination';

    public function __construct(
        private BorderManager $borderManager,
        private UploaderClient $uploaderClient,
        private EntityManagerInterface $em,
        private RenditionManager $renditionManager,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $userId = $inputs['userId'];
        $assetData = $this->uploaderClient->getAsset($inputs['baseUrl'], $inputs['assetId'], $inputs['token']);
        $data = $assetData['data'];
        $assetId = $data['targetAsset'] ?? null;
        $formData = $assetData['formData'] ?? [];
        $formLocale = $assetData['formLocale'] ?? null;
        $renditionDefId = $data['targetRendition'] ?? null;

        if ($renditionDefId) {
            if (null === $assetId) {
                throw new \InvalidArgumentException('Missing "targetAsset" when "targetRendition" is set');
            }
            $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
            $renditionDefinition = DoctrineUtil::findStrict($this->em, RenditionDefinition::class, $renditionDefId);

            $this->renditionManager->validateSubstitution(
                $asset,
                $renditionDefinition,
                true,
                true,
            );
        } else {
            $collection = null;
            $collectionId = $inputs['storyCollectionId'] ?? $formData[self::COLLECTION_DESTINATION] ?? $inputs['collectionId'] ?? null;
            if ($collectionId) {
                $collection = DoctrineUtil::findStrict($this->em, Collection::class, $collectionId);
                $workspace = $collection->getWorkspace();
            } else {
                $workspaceId = $data['workspaceId'] ?? $inputs['workspaceId'] ?? null;
                if (empty($workspaceId)) {
                    throw new \InvalidArgumentException(sprintf('Missing "%s", "targetAsset", "collectionId" or "workspaceId"', self::COLLECTION_DESTINATION));
                }
                $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
            }

            $asset = new Asset();
            $asset->setTitle($assetData['originalName']);
            $asset->setOwnerId($userId);
            $asset->setWorkspace($workspace);
            if (null !== $collection) {
                $asset->setReferenceCollection($collection);
                $asset->addToCollection($collection);
            }
        }

        $inputFile = new InputFile(
            $assetData['originalName'],
            $assetData['mimeType'],
            $assetData['size'],
            $assetData['url'],
        );

        $this->em->persist($asset);
        $file = $this->borderManager->acceptFile($inputFile, $asset->getWorkspace());

        $context->setOutput('fileId', $file->getId());
        $context->setOutput('assetId', $asset->getId());
        $context->setOutput('formData', $formData);
        $context->setOutput('formLocale', $formLocale);
        $context->setOutput('renditionId', $renditionDefId);
    }
}
