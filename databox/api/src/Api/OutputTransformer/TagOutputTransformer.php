<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Model\Output\TagOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Tag;

class TagOutputTransformer implements OutputTransformerInterface
{
    use UserLocaleTrait;

    public function supports(string $outputClass, object $data): bool
    {
        return TagOutput::class === $outputClass && $data instanceof Tag;
    }

    /**
     * @param Tag $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $preferredLocales = $this->getPreferredLocales($data->getWorkspace());

        $output = new TagOutput();
        $output->setId($data->getId());
        $output->setName($data->getName());

        $translations = $data->getTranslations()['name'] ?? [];
        $key = LocaleUtil::getBestLocale(array_keys($translations), $preferredLocales);
        if (null !== $key) {
            $output->setNameTranslated($translations[$key]);
        } else {
            $output->setNameTranslated($data->getName());
        }
        $output->setTranslations($data->getTranslations());
        $output->setColor($data->getColor());

        return $output;
    }
}
