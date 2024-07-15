<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AttributeOutput;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;

class AttributeOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;

    public function __construct(private readonly AttributeTypeRegistry $attributeTypeRegistry)
    {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return AttributeOutput::class === $outputClass && $data instanceof AbstractBaseAttribute;
    }

    /**
     * @param AbstractBaseAttribute $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $type = $this->attributeTypeRegistry->getStrictType($data->getDefinition()->getFieldType());

        $output = new AttributeOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->value = $type->denormalizeValue($data->getValue());

        /** @var AttributeDefinition $definition */
        $definition = $data->getDefinition();
        $output->locale = $definition->isTranslatable() ? $data->getLocale() : IndexMappingUpdater::NO_LOCALE;
        $output->position = $data->getPosition();
        $output->definition = $data->getDefinition();

        if ($data instanceof Attribute) {
            $output->asset = $data->getAsset();
            $output->highlight = $data->getHighlight();
            $output->origin = $data->getOriginLabel();
            $output->originUserId = $data->getOriginUserId();
            $output->originVendor = $data->getOriginVendor();
            $output->originVendorContext = $data->getOriginVendorContext();
            $output->status = $data->getStatusLabel();
            $output->confidence = $data->getConfidence();
            $output->assetAnnotations = $data->getAssetAnnotations();
        }

        return $output;
    }
}
