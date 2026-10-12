<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\WorkspaceInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Workspace;
use App\Service\Workspace\TermsManager;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: WorkspaceInput::class)]
class WorkspaceInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;

    public function __construct(
        private readonly TermsManager $termsManager,
    ) {
    }

    /**
     * @param WorkspaceInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        /** @var Workspace $object */
        $object = $target ?? new Workspace();
        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->slug) {
            $object->setSlug($data->slug);
        }
        if (null !== $data->public) {
            $object->setPublic($data->public);
        }
        if (null !== $data->enabledLocales) {
            $object->setEnabledLocales(array_values($data->enabledLocales));
        }
        if (null !== $data->localeFallbacks) {
            $object->setLocaleFallbacks(array_values($data->localeFallbacks));
        }
        if (null !== $data->assetDefaultStatus) {
            $object->setAssetDefaultStatus(AssetStatusEnum::tryFrom($data->assetDefaultStatus) ?? AssetStatusEnum::Accepted);
        }
        if (null !== $data->fileAnalysisRequired) {
            $object->setFileAnalysisRequired($data->fileAnalysisRequired);
        }
        if (null !== $data->translations) {
            $object->setTranslations($data->translations);
        }
        if (null !== $data->trashRetentionDelay) {
            $object->setTrashRetentionDelay((int) $data->trashRetentionDelay);
        }
        if (null !== $data->attachTermsToExports) {
            $object->setAttachTermsToExports($data->attachTermsToExports);
        }
        if (null !== $data->terms || null !== $data->termsTranslations) {
            $this->termsManager->updateTerms($object, $data->terms, $data->termsTranslations);
        }

        if ($isNew) {
            if ($data->getOwnerId()) {
                $object->setOwnerId($data->getOwnerId());
            }
        }

        return $this->processOwnerId($object);
    }
}
