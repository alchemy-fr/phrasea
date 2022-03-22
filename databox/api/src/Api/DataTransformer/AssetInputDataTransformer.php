<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\AssetSourceInput;
use App\Asset\OriginalRenditionManager;
use App\Consumer\Handler\File\GenerateAssetRenditionsHandler;
use App\Consumer\Handler\File\ImportRenditionHandler;
use App\Doctrine\Listener\PostFlushStackListener;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Util\ExtensionUtil;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AssetInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;

    private PostFlushStackListener $postFlushStackListener;
    private EntityManagerInterface $em;
    private OriginalRenditionManager $originalRenditionManager;
    private AttributeInputDataTransformer $attributeInputDataTransformer;

    public function __construct(
        PostFlushStackListener $postFlushStackListener, 
        EntityManagerInterface $em,
        OriginalRenditionManager $originalRenditionManager,
        AttributeInputDataTransformer $attributeInputDataTransformer
    )
    {
        $this->postFlushStackListener = $postFlushStackListener;
        $this->em = $em;
        $this->originalRenditionManager = $originalRenditionManager;
        $this->attributeInputDataTransformer = $attributeInputDataTransformer;
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
            if (!$workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->key) {
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
                $origRenditions = $this->originalRenditionManager->assignFileToOriginalRendition($object, $file);

                if ($data->generateRenditions) {
                    $this->postFlushStackListener->addEvent(GenerateAssetRenditionsHandler::createEvent($object->getId()));
                } elseif ($source->importFile) {
                    foreach ($origRenditions as $origRendition) {
                        $this->postFlushStackListener
                            ->addEvent(ImportRenditionHandler::createEvent($origRendition->getId(), ExtensionUtil::getExtension($data->source->url)));
                        // One import is sufficient as it is the same File
                        break;
                    }
                }
            }

            if (!empty($data->renditions)) {
                foreach ($data->renditions as $renditionInput) {
                    $rendition = new AssetRendition();
                    $rendition->setAsset($object);
                    $rendition->setDefinition($this->getRenditionDefinitionByName(
                        $renditionInput->definition,
                        $workspace
                    ));
                    $rendition->setReady(true);
                    $this->handleSource($renditionInput->source, $rendition, $workspace);
                    $this->em->persist($rendition);
                    $this->em->persist($rendition->getFile());

                    if ($renditionInput->source->importFile) {
                        $this->postFlushStackListener
                            ->addEvent(ImportRenditionHandler::createEvent($rendition->getId(), ExtensionUtil::getExtension($renditionInput->source->url)));
                    }
                }
            }
            if (!empty($data->attributes)) {
                foreach ($data->attributes as $attribute) {
                    if (is_array($attribute->values)) {
                        foreach ($attribute->values as $value) {
                            $attr = clone $attribute;
                            unset($attr->values);
                            $attr->value = $value;
                            $object->addAttribute($this->attributeInputDataTransformer->transform($attr, Attribute::class, $context));
                        }
                    } else {
                        $object->addAttribute($this->attributeInputDataTransformer->transform($attribute, Attribute::class, $context));
                    }
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

    private function getRenditionDefinitionByName(string $name, Workspace $workspace): RenditionDefinition
    {
        $definition = $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'name' => $name,
                'workspace' => $workspace->getId(),
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new \InvalidArgumentException(sprintf('Rendition definition "%s" not found', $name));
        }

        return $definition;
    }

    /**
     * @param Asset|AssetRendition $object
     */
    private function handleSource(AssetSourceInput $source, $object, Workspace $workspace): File
    {
        $src = new File();
        $src->setPath($source->url);
        $src->setPathPublic(!$source->isPrivate);
        $src->setStorage(File::STORAGE_URL);
        $src->setWorkspace($workspace);
        $object->setFile($src);

        if (null !== $source->alternateUrls) {
            foreach ($source->alternateUrls as $altUrl) {
                $src->setAlternateUrl($altUrl->type, $altUrl->url);
            }
        }

        return $src;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Asset) {
            return false;
        }

        return Asset::class === $to && AssetInput::class === ($context['input']['class'] ?? null);
    }
}
