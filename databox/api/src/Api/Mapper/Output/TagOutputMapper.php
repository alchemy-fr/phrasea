<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\TagOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Tag;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: TagOutput::class)]
class TagOutputMapper implements OutputMapperInterface
{
    use UserLocaleTrait;

    public function supports(object $data): bool
    {
        return $data instanceof Tag;
    }

    /**
     * @param Tag $data
     */
    public function map(object $data, array $context = []): object
    {
        $preferredLocales = $this->getPreferredLocales($data->getWorkspace());

        $output = new TagOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());

        $output->displayName = $data->getTranslatedField(Tag::TR_FIELD_NAME, $preferredLocales, $data->getName());
        $output->translations = $data->getTranslations();
        $output->setColor($data->getColor());

        return $output;
    }
}
