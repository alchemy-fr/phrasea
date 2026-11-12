<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\StorageBundle\Upload\ContentTypeDetector;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\State\JobState;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use App\Service\Asset\FileFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Checks the content of the asset source file before any integration processes it:
 * the type detected from the first bytes must belong to the family of the declared
 * type, be allowed (ALLOWED_FILE_TYPES), and not be a forbidden format.
 *
 * A refused file is not served and the asset is quarantined. The job fails,
 * so that the integrations (renditions, analyses…) depending on it never run.
 */
readonly class CheckFileContentAction implements ActionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private FileFetcher $fileFetcher,
        private FileValidator $fileValidator,
        private ContentTypeDetector $contentTypeDetector,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $asset = $this->em->find(Asset::class, $context->getInputs()['assetId']);
        $file = $asset?->getSource();
        if (null === $file || !$this->fileFetcher->isFetchable($file)) {
            // Nothing the platform could read, nothing it could serve or process either
            return;
        }

        $head = $this->fileFetcher->getHead($file, ContentTypeDetector::HEAD_SIZE);

        $refusal = null;
        try {
            $file->setDetectedType($this->contentTypeDetector->detectFromBuffer($head));
            $this->fileValidator->validateHead($head, $file->getFileName(), $file->getType());
        } catch (BadRequestHttpException $e) {
            $refusal = $e->getMessage();
        }

        if (null === $refusal) {
            $context->setOutput('detectedType', $file->getDetectedType());
            $this->accept($file, $asset);
        } elseif (FileAnalysisStateEnum::Bypassed === $file->getAnalysisState()) {
            // An admin accepted the file despite its refusal
            $context->setOutput('refusal', $refusal);
        } else {
            $context->setOutput('refusal', $refusal);
            $this->reject($file, $asset, $refusal);
            $context->setEndStatus(JobState::STATUS_FAILURE);
        }

        $this->em->persist($file);
        $this->em->flush();
    }

    private function accept(File $file, Asset $asset): void
    {
        if (!$file->isContentRejected()) {
            return;
        }

        // Refused by a former check (allowed types changed since)
        $file->clearContentRejection();
        if (AssetStatusEnum::Quarantined === $asset->getStatus()) {
            $asset->setStatus($asset->getWorkspace()->getAssetDefaultStatus());
            $this->em->persist($asset);
        }
    }

    private function reject(File $file, Asset $asset, string $refusal): void
    {
        $file->rejectContent($refusal);
        $asset->setStatus(AssetStatusEnum::Quarantined);
        $this->em->persist($asset);
    }
}
