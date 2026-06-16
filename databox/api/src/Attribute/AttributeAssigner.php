<?php

declare(strict_types=1);

namespace App\Attribute;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AbstractExtendedAttributeInput;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AttributeAssigner
{
    public function __construct(
        private AttributeTypeRegistry $attributeTypeRegistry,
        private AttributeRepository $attributeRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function resetAssetAttributesCache(Asset $asset): void
    {
        $this->attributeRepository->resetAssetCache($asset);
    }

    public function normalizeValue(AttributeDefinition $definition, mixed $value): mixed
    {
        $type = $this->attributeTypeRegistry->getStrictType($definition->getType());

        return $type->normalizeValue($value);
    }

    public function assignAttributeFromInput(AbstractBaseAttribute $attribute, AbstractBaseAttributeInput $data, mixed $normalizedValue): void
    {
        if (null === $normalizedValue) {
            throw new \LogicException('NULL value should never be passed here');
        }

        if ($data instanceof AbstractExtendedAttributeInput) {
            assert($attribute instanceof Attribute);
            if ($data->origin) {
                if (false !== $k = array_search($data->origin, Attribute::ORIGIN_LABELS, true)) {
                    $attribute->setOrigin($k);
                } else {
                    throw new \InvalidArgumentException(sprintf('Invalid origin value "%s", allowed ones are: %s', $data->origin, implode(', ', Attribute::ORIGIN_LABELS)));
                }
            } elseif (!$attribute->hasOrigin()) {
                $attribute->setOrigin(Attribute::ORIGIN_MACHINE);
            }

            if ($data->status) {
                if (false !== $k = array_search($data->status, Attribute::STATUS_LABELS, true)) {
                    $attribute->setStatus($k);
                } else {
                    throw new \InvalidArgumentException(sprintf('Invalid status value "%s", allowed ones are: %s', $data->status, implode(', ', Attribute::STATUS_LABELS)));
                }
            }
            $attribute->setOriginUserId($data->originUserId);
            $attribute->setOriginVendor($data->originVendor);
            $attribute->setOriginVendorContext($data->originVendorContext);
            if ($data->confidence) {
                $attribute->setConfidence($data->confidence);
            }
            $attribute->setAssetAnnotations($data->annotations);
        }

        if ($data->locale) {
            $attribute->setLocale(LocaleUtil::normalizeLocale($data->locale));
        }

        $type = $this->attributeTypeRegistry->getStrictType($attribute->getDefinition()->getType());
        $value = $type->convertToDbValue($normalizedValue);

        if (null === $value) {
            throw new InvalidAttributeValueException(sprintf('Normalized "%s" value is NULL (from: "%s"): %s', $type::getName(), get_debug_type($normalizedValue), var_export($normalizedValue, true)));
        }

        $attribute->setValue($value);
        $attribute->setPosition($data->position ?? 0);
    }

    public function upsertOrRemoveAttribute(
        AttributeDefinition $attributeDefinition,
        Asset $asset,
        AttributeInput $data,
        bool $persist = true,
    ): ?Attribute {
        $normalizedValue = $this->normalizeValue($attributeDefinition, $data->value);
        if (null === $normalizedValue) {
            if ($persist && (null !== $attribute = $this->getAttribute($attributeDefinition, $asset))) {
                $this->em->remove($attribute);
            }

            $this->resetAssetAttributesCache($asset);

            return null;
        }

        $attribute = $this->getOrCreateAttribute($attributeDefinition, $asset);

        $this->assignAttributeFromInput($attribute, $data, $normalizedValue);
        if ($persist) {
            $this->em->persist($attribute);
        }

        $this->resetAssetAttributesCache($attribute->getAsset());

        return $attribute;
    }

    private function getOrCreateAttribute(AttributeDefinition $attributeDefinition, Asset $asset): Attribute
    {
        $attribute = $this->getAttribute($attributeDefinition, $asset);
        if ($attribute instanceof Attribute) {
            return $attribute;
        }

        $attribute = new Attribute();
        $attribute->setDefinition($attributeDefinition);
        $attribute->setAsset($asset);
        $asset->addAttribute($attribute);

        return $attribute;
    }

    private function getAttribute(AttributeDefinition $attributeDefinition, Asset $asset): ?Attribute
    {
        if ($attributeDefinition->isMultiple()) {
            throw new \LogicException('Multiple attributes are not supported');
        }

        return $this->attributeRepository->findOneBy([
            'definition' => $attributeDefinition->getId(),
            'asset' => $asset->getId(),
        ]);
    }
}
