<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\Template\AssetDataTemplateInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Template\AssetDataTemplate;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: AssetDataTemplateInput::class)]
class AssetDataTemplateInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;
    use AttributeInputTrait;

    public function __construct(private readonly TemplateAttributeInputMapper $templateAttributeInputProcessor)
    {
    }

    /**
     * @param AssetDataTemplateInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        /** @var AssetDataTemplate $object */
        $object = $target ?? new AssetDataTemplate();

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
            $this->assignAttributes($object->getWorkspaceId(), $this->templateAttributeInputProcessor, $object, $data->attributes, $context);
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
