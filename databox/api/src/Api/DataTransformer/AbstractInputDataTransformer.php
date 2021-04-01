<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\CollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractInputDataTransformer extends AbstractSecurityDataTransformer
{
    /**
     * @param AssetInput|CollectionInput $data
     * @param Asset|Collection $object
     */
    protected function transformPrivacy($data, $object): void
    {
        if (null !== $data->privacy) {
            $object->setPrivacy($data->privacy);
        }
        if (null !== $data->privacyLabel) {
            $constantName = WorkspaceItemPrivacyInterface::class.'::'.strtoupper($data->privacyLabel);
            if (!defined($constantName)) {
                throw new BadRequestHttpException(sprintf('Invalid privacyLabel "%s"', $data->privacyLabel));
            }
            $object->setPrivacy(constant($constantName));
        }
    }
}
