<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Border\UploaderClient;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

readonly class AcceptFileAction implements ActionInterface
{
    public function __construct(
        private BorderManager $borderManager,
        private UploaderClient $uploaderClient,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $userId = $inputs['userId'];
        $assetData = $this->uploaderClient->getAsset($inputs['baseUrl'], $inputs['assetId'], $inputs['token']);
        $assetId = $assetData['data']['targetAsset'] ?? null;

        if (null !== $assetId) {
            $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
            $uploadToken = $assetData['data']['uploadToken'];

            if ($uploadToken !== $asset->getPendingUploadToken()) {
                throw new \InvalidArgumentException('Unexpected upload token');
            }
        } else {
            $collection = null;
            $collectionId = $assetData['formData']['collection_destination'] ?? null;
            if ($collectionId) {
                $collection = DoctrineUtil::findStrict($this->em, Collection::class, $collectionId);
                $workspace = $collection->getWorkspace();
            } else {
                $workspaceId = $assetData['data']['workspaceId'] ?? null;
                if (empty($workspaceId)) {
                    throw new \InvalidArgumentException('Missing target asset or workspace ID');
                }
                $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
            }

            $asset = new Asset();
            $asset->setTitle($assetData['originalName']);
            $asset->setOwnerId($userId);
            $asset->setWorkspace($workspace);
            if (null !== $collection) {
                $asset->addToCollection($collection);
            }

            $this->em->persist($asset);
            $this->em->flush();
        }

        $inputFile = new InputFile(
            $assetData['originalName'],
            $assetData['mimeType'],
            $assetData['size'],
            $assetData['url'],
        );

        $file = $this->borderManager->acceptFile($inputFile, $asset->getWorkspace());
        $context->setOutput('fileId', $file->getId());
        $context->setOutput('assetId', $asset->getId());
    }
}
