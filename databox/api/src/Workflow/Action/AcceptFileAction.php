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
use App\Entity\Core\File;
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

        $assetData = $this->uploaderClient->getAsset($inputs['baseUrl'], $inputs['assetId'], $inputs['token']);

        $assetId = $assetData['data']['targetAsset'];
        $uploadToken = $assetData['data']['uploadToken'];

        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
        if ($uploadToken !== $asset->getPendingUploadToken()) {
            throw new \InvalidArgumentException('Unexpected upload token');
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
