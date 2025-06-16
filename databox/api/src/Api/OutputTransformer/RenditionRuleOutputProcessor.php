<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\RenditionRuleOutput;
use App\Entity\Core\RenditionRule;

class RenditionRuleOutputProcessor implements OutputTransformerInterface
{
    use UserOutputTransformerTrait;
    use GroupOutputTransformerTrait;

    public function supports(string $outputClass, object $data): bool
    {
        return RenditionRuleOutput::class === $outputClass && $data instanceof RenditionRule;
    }

    /**
     * @param RenditionRule $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new RenditionRuleOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        if (RenditionRule::TYPE_USER === $data->getUserType()) {
            $output->setUserId($data->getUserId());
            $output->user = $this->transformUser($data->getUserId());
        } elseif (RenditionRule::TYPE_GROUP === $data->getUserType()) {
            $output->setGroupId($data->getUserId());
            $output->group = $this->transformGroup($data->getUserId());
        }

        if (RenditionRule::TYPE_COLLECTION === $data->getObjectType()) {
            $output->setCollectionId($data->getObjectId());
        } elseif (RenditionRule::TYPE_WORKSPACE === $data->getObjectType()) {
            $output->setWorkspaceId($data->getObjectId());
        }

        $output->setAllowed($data->getAllowed()->getValues());

        return $output;
    }
}
