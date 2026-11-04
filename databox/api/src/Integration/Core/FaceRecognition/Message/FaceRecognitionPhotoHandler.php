<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeEntity;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FacePhotoCropper;
use App\Integration\Core\FaceRecognition\FaceRecognitionConfigResolver;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Repository\Core\AttributeRepository;
use App\Service\Asset\Exception\PrivateFileException;
use App\Service\Image\ImageSetManager;
use App\Service\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Intervention\Image\Exception\NotReadableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Gives a person the photo of its best face (the most confident one, a face named by a user first),
 * cropped from its asset.
 *
 * A photo a user uploaded is never replaced. An automatic photo is kept as long as its face still
 * shows the person, unless it comes from a recognized face and the person now has a face named by
 * a user: a more confident recognized face alone does not replace it.
 */
#[AsMessageHandler]
final readonly class FaceRecognitionPhotoHandler
{
    private const float LOCK_TTL = 120;

    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private FaceRecognitionConfigResolver $configResolver,
        private AttributeRepository $attributeRepository,
        private FacePhotoCropper $cropper,
        private FileManager $fileManager,
        private ImageSetManager $imageSetManager,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FaceRecognitionPhoto $message): void
    {
        $workspaceIntegration = $this->em->find(WorkspaceIntegration::class, $message->getIntegrationId());
        if (!$workspaceIntegration instanceof WorkspaceIntegration || !$workspaceIntegration->isEnabled()) {
            return;
        }
        $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
        if (!$config['autoPhoto']) {
            return;
        }

        $entity = $this->em->find(AttributeEntity::class, $message->getEntityId());
        if (!$entity instanceof AttributeEntity) {
            return;
        }
        try {
            $definition = $this->configResolver->getDefinition($config);
        } catch (\InvalidArgumentException) {
            return;
        }
        if (null === $definition->getEntityList() || $entity->getListId() !== $definition->getEntityList()->getId()) {
            return;
        }

        // The faces of a person are often detected at once (several assets ingested together):
        // only one photo must be created
        $lock = $this->lockFactory->createLock('face-photo-'.$entity->getId(), self::LOCK_TTL);
        $lock->acquire(true);
        try {
            try {
                $this->em->refresh($entity);
            } catch (EntityNotFoundException) {
                return;
            }

            $this->updatePhoto($entity, $definition->getId(), $config);
        } finally {
            $lock->release();
        }
    }

    private function updatePhoto(AttributeEntity $entity, string $definitionId, IntegrationConfig $config): void
    {
        $current = $entity->getImage();
        if (null !== $current && !$current->isAuto()) {
            return;
        }

        $best = $this->attributeRepository->findMostConfidentEmbedding($definitionId, $entity->getId());
        if (!$best instanceof Attribute) {
            return;
        }

        if (null !== $current) {
            if ($best->getId() === $current->getSourceAttributeId()) {
                return;
            }
            $source = $this->em->find(Attribute::class, $current->getSourceAttributeId());
            $stillShowsPerson = $source instanceof Attribute
                && (string) $source->getValue() === $entity->getId()
                && !$source->getAsset()->isDeleted();
            if ($stillShowsPerson && (Attribute::ORIGIN_HUMAN === $source->getOrigin() || Attribute::ORIGIN_HUMAN !== $best->getOrigin())) {
                return;
            }
        }

        try {
            $path = $this->cropper->crop($best, $config);
        } catch (PrivateFileException|NotReadableException|\ImagickException $e) {
            $this->logger->warning('The photo of the person could not be cropped from its face', [
                'exception' => $e,
                'entityId' => $entity->getId(),
                'attributeId' => $best->getId(),
            ]);

            return;
        }
        if (null === $path) {
            return;
        }

        $workspace = $entity->getWorkspace();
        $file = $this->fileManager->createFileFromPath($workspace, $path, 'image/jpeg');
        $file->setNoAnalysisNeeded();

        // The previous automatic photo is an orphan: removed with its files (FileListener)
        $entity->setImage($this->imageSetManager->createFromFile($file, $workspace, $best->getId()));
        $this->em->flush();
    }
}
