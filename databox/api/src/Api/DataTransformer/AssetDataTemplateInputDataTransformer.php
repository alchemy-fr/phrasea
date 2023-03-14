<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\Template\AssetDataTemplateInput;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AssetDataTemplateInputDataTransformer extends AbstractInputDataTransformer
{
    use WithOwnerIdDataTransformerTrait;
    use AttributeInputTrait;

    private TemplateAttributeInputDataTransformer $templateAttributeInputDataTransformer;

    public function __construct(TemplateAttributeInputDataTransformer $templateAttributeInputDataTransformer)
    {
        $this->templateAttributeInputDataTransformer = $templateAttributeInputDataTransformer;
    }

    /**
     * @param AssetDataTemplateInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $this->validator->validate($data);

        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var AssetDataTemplate $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new AssetDataTemplate();

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
            $this->assignAttributes($this->templateAttributeInputDataTransformer, $object, $data->attributes, TemplateAttribute::class, $context);
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

        return $this->transformOwnerId($object, $to, $context);
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof AssetDataTemplate) {
            return false;
        }

        return AssetDataTemplate::class === $to && AssetDataTemplateInput::class === ($context['input']['class'] ?? null);
    }
}
