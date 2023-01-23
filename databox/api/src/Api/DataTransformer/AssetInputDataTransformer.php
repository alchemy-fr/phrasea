<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\StorageBundle\Upload\UploadManager;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\AssetSourceInput;
use App\Asset\FileCopier;
use App\Asset\OriginalRenditionManager;
use App\Consumer\Handler\Asset\NewAssetIntegrationsHandler;
use App\Consumer\Handler\File\CopyFileStorageToFileHandler;
use App\Consumer\Handler\File\ImportFileHandler;
use App\Doctrine\Listener\PostFlushStack;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Http\FileUploadManager;
use App\Storage\RenditionManager;
use App\Util\FileUtil;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AssetInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    private PostFlushStack $postFlushStackListener;
    private EntityManagerInterface $em;
    private OriginalRenditionManager $originalRenditionManager;
    private AttributeInputDataTransformer $attributeInputDataTransformer;
    private RenditionManager $renditionManager;
    private UploadManager $uploadManager;
    private RequestStack $requestStack;
    private FileUploadManager $fileUploadManager;
    private FileCopier $fileCopier;

    public function __construct(
        PostFlushStack $postFlushStackListener,
        EntityManagerInterface $em,
        OriginalRenditionManager $originalRenditionManager,
        AttributeInputDataTransformer $attributeInputDataTransformer,
        RenditionManager $renditionManager,
        UploadManager $uploadManager,
        FileUploadManager $fileUploadManager,
        RequestStack $requestStack,
        FileCopier $fileCopier
    ) {
        $this->postFlushStackListener = $postFlushStackListener;
        $this->em = $em;
        $this->originalRenditionManager = $originalRenditionManager;
        $this->attributeInputDataTransformer = $attributeInputDataTransformer;
        $this->renditionManager = $renditionManager;
        $this->uploadManager = $uploadManager;
        $this->requestStack = $requestStack;
        $this->fileUploadManager = $fileUploadManager;
        $this->fileCopier = $fileCopier;
    }

    /**
     * @param AssetInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        } elseif (null !== $data->collection) {
            $workspace = $data->collection->getWorkspace();
        }

        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Asset $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Asset();

        if ($isNew) {
            if ($workspace instanceof Workspace && $data->key) {
                $asset = $this->em->getRepository(Asset::class)
                    ->findOneBy([
                        'key' => $data->key,
                        'workspace' => $workspace->getId(),
                    ]);

                if ($asset) {
                    $isNew = false;
                    $object = $asset;
                }
            }
        }

        if ($data->title) {
            $object->setTitle($data->title);
        }

        $this->transformPrivacy($data, $object);

        if ($isNew) {
            $object->setWorkspace($workspace);
            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }

            if ($data->key) {
                $object->setKey($data->key);
            }
            if (null !== $data->collection) {
                if (null === $object->getReferenceCollection()) {
                    $object->setReferenceCollection($data->collection);
                }
                $object->addToCollection($data->collection);
            }

            if ($source = $data->source) {
                $file = $this->handleSource($source, $object, $object->getWorkspace());
                $this->originalRenditionManager->assignFileToOriginalRendition($object, $file);

                $this->postFlushStackListener
                    ->addEvent(ImportFileHandler::createEvent($file->getId()));
            } elseif (null !== $sourceFileId = $data->sourceFileId) {
                $file = $this->handleFromFile($sourceFileId, $workspace);
                $this->originalRenditionManager->assignFileToOriginalRendition($object, $file);
            } elseif (null !== $request = $this->requestStack->getCurrentRequest()) {
                $file = $this->handleUpload($object, $request);
                if (null !== $file) {
                    $this->originalRenditionManager->assignFileToOriginalRendition($object, $file);
                }
            }

            if (null !== $object->getSource()) {
                $this->postFlushStackListener->addEvent(NewAssetIntegrationsHandler::createEvent($object->getId()));
            }

            if (!empty($data->renditions)) {
                foreach ($data->renditions as $renditionInput) {
                    $rendition = new AssetRendition();
                    $rendition->setAsset($object);
                    $rendition->setDefinition($this->renditionManager->getRenditionDefinitionByName(
                        $workspace,
                        $renditionInput->definition
                    ));
                    $rendition->setReady(true);
                    $this->handleSource($renditionInput->source, $rendition, $workspace);
                    $this->em->persist($rendition);
                    $this->em->persist($rendition->getFile());

                    if ($renditionInput->source->importFile) {
                        $this->postFlushStackListener
                            ->addEvent(ImportFileHandler::createEvent($rendition->getId()));
                    }
                }
            }
            if (!empty($data->attributes)) {
                foreach ($data->attributes as $attribute) {
                    $attribute->asset = $object;

                    if (is_array($attribute->value)) {
                        $definition = $attribute->definition;
                        if (!$definition instanceof AttributeDefinition) {
                            $definition = $this->em->getRepository(AttributeDefinition::class)->findOneBy([
                                'name' => $attribute->name,
                                'workspace' => $object->getWorkspaceId(),
                            ]);

                            if (!$definition instanceof AttributeDefinition) {
                                throw new InvalidArgumentException(sprintf('Attribute definition "%s" not found', $attribute->name));
                            }
                        }

                        if ($definition->isMultiple()) {
                            foreach ($attribute->value as $value) {
                                $attr = clone $attribute;
                                $attr->value = $value;
                                $object->addAttribute($this->attributeInputDataTransformer->transform($attr, Attribute::class, array_merge([
                                    AttributeInputDataTransformer::ATTRIBUTE_DEFINITION => $definition,
                                ], $context)));
                            }

                            continue;
                        }
                        // else add single attr below
                    }

                    $object->addAttribute($this->attributeInputDataTransformer->transform($attribute, Attribute::class, $context));
                }
            }
        }

        if (isset($data->tags)) {
            $object->getTags()->clear();
            foreach ($data->tags as $tag) {
                $object->addTag($tag);
            }
        }

        return $this->transformOwnerId($object, $to, $context);
    }

    private function handleUpload(Asset $asset, Request $request): ?File
    {
        if (!$asset->getWorkspace()) {
            // Workspace is not set, validation will reject request.
            return null;
        }

        $file = new File();
        $file->setWorkspace($asset->getWorkspace());
        $file->setStorage(File::STORAGE_S3_MAIN);

        if (null !== $request->request->get('multipart')) {
            $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

            $file->setType($multipartUpload->getType());
            $file->setExtension(FileUtil::guessExtension($multipartUpload->getType(), $multipartUpload->getFilename()));
            $file->setSize($multipartUpload->getSize());
            $file->setOriginalName($multipartUpload->getFilename());
            $file->setPath($multipartUpload->getPath());
            $asset->setSource($file);

            return $file;
        }

        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');
        if (null !== $uploadedFile) {
            $file = $this->fileUploadManager->storeFileUploadFromRequest($asset->getWorkspace(), $uploadedFile);
            $asset->setSource($file);

            return $file;
        }

        return null;
    }

    /**
     * @param Asset|AssetRendition $object
     */
    private function handleSource(AssetSourceInput $source, $object, Workspace $workspace): File
    {
        $src = new File();
        $src->setPath($source->url);
        $src->setOriginalName($source->originalName);
        $src->setExtension(FileUtil::getExtensionFromPath($source->originalName ?: $source->url));
        $src->setPathPublic(!$source->isPrivate);
        $src->setStorage(File::STORAGE_URL);
        $src->setWorkspace($workspace);
        $object->setSource($src);

        if (null !== $source->alternateUrls) {
            foreach ($source->alternateUrls as $altUrl) {
                $src->setAlternateUrl($altUrl->type, $altUrl->url);
            }
        }

        return $src;
    }

    private function handleFromFile(string $fileId, Workspace $workspace): File
    {
        $file = $this->em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new BadRequestHttpException(sprintf('File "%s" does not exist', $fileId));
        }
        if (!$file->isPathPublic()) {
            throw new BadRequestHttpException(sprintf('Copy error: File "%s" has a private path', $fileId));
        }

        $copy = $this->fileCopier->copyFileProperties($file, $workspace);

        $this->postFlushStackListener->addEvent(CopyFileStorageToFileHandler::createEvent($file->getId()));

        return $copy;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && AssetInput::class === ($context['input']['class'] ?? null);
    }
}
