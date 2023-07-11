<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Input\Template\AssetDataTemplateInput;
use App\Entity\Template\AssetDataTemplate;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AssetDataTemplateInputProcessor extends AbstractInputProcessor
{
    use WithOwnerIdProcessorTrait;
    use AttributeInputTrait;

    public function __construct(private readonly TemplateAttributeInputProcessor $templateAttributeInputProcessor)
    {
    }

    /**
     * @param AssetDataTemplateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
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
            $this->assignAttributes($this->templateAttributeInputProcessor, $object, $data->attributes, $operation, $context);
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
