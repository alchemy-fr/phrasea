<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\Template\AssetDataTemplateInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Template\AssetDataTemplate;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AssetDataTemplateInputTransformer extends AbstractInputTransformer
{
    use WithOwnerIdProcessorTrait;
    use AttributeInputTrait;

    public function __construct(private readonly TemplateAttributeInputTransformer $templateAttributeInputProcessor)
    {
    }

    /**
     * @param AssetDataTemplateInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var AssetDataTemplate $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AssetDataTemplate();

        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        }

        if ($isNew) {
            if (null === $workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }
            $object->setWorkspace($workspace);
        }

        if (!empty($data->attributes)) {
            $object->getAttributes()->clear();
            $this->assignAttributes($this->templateAttributeInputProcessor, $object, $data->attributes, $context);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->privacy) {
            $object->setPrivacy($data->privacy);
        }
        if (null !== $data->public) {
            $object->setPublic($data->public);
        }
        if (null !== $data->tags) {
            $object->setTags(new ArrayCollection($data->tags));
        }
        if (null !== $data->collection) {
            $object->setCollection($data->collection);
        }
        $object->setIncludeCollectionChildren($data->includeCollectionChildren);

        return $this->processOwnerId($object);
    }
}
